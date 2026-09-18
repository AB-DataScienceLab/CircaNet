
<?php
// gene.php

// --- AJAX ENDPOINT FOR AUTO-SUGGEST ---
if (isset($_GET['ajax_suggest'])) {
    include 'conn.php';
    $term = trim($_GET['ajax_suggest']) . '%'; 
    $sql = "SELECT symbol FROM gene_annotation 
            WHERE symbol LIKE ? 
            ORDER BY symbol ASC 
            LIMIT 10";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $term);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row['symbol'];
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
// -----------------------------------------

include 'conn.php';
include 'header.php'; 

// --- BACKEND LOGIC ---
$searchKeyword = '';
$keyword = '';
$geneData = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['keyword'])) {
    $searchKeyword = trim($_POST['keyword']);
    $keyword = $searchKeyword;
} elseif (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
    $searchKeyword = trim($_GET['keyword']);
    $keyword = $searchKeyword;
} else {
    $searchKeyword = 'clock'; 
    $keyword = 'clock';
}

function getGeneData($conn, $keyword) {
    // 1. Direct match in gene_annotation by symbol, entrez_id, or hgnc_id
    $sql = "SELECT * FROM gene_annotation 
            WHERE (symbol = ? OR entrez_id = ? OR hgnc_id = ?) 
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $keyword, $keyword, $keyword);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($row) return $row;
    }

    // 2. Lookup alias_tb if searched keyword is an alias_symbol or prev_symbol
    $pattern = '%|' . $keyword . '|%';
    $patternStart = $keyword . '|%';
    $patternEnd = '%|' . $keyword;
    
    $sqlAlias = "SELECT symbol FROM alias_tb 
                 WHERE symbol = ? 
                    OR alias_symbol = ? 
                    OR prev_symbol = ? 
                    OR CONCAT('|', alias_symbol, '|') LIKE ? 
                    OR CONCAT('|', prev_symbol, '|') LIKE ? 
                    OR alias_symbol LIKE ? 
                    OR alias_symbol LIKE ?
                    OR prev_symbol LIKE ? 
                    OR prev_symbol LIKE ?
                 LIMIT 1";
    $stmtAlias = mysqli_prepare($conn, $sqlAlias);
    if ($stmtAlias) {
        mysqli_stmt_bind_param($stmtAlias, "sssssssss", 
            $keyword, $keyword, $keyword, 
            $pattern, $pattern, 
            $patternStart, $patternEnd, 
            $patternStart, $patternEnd
        );
        mysqli_stmt_execute($stmtAlias);
        $resAlias = mysqli_stmt_get_result($stmtAlias);
        if ($rowAlias = mysqli_fetch_assoc($resAlias)) {
            $mainSymbol = $rowAlias['symbol'];
            mysqli_stmt_close($stmtAlias);
            
            // Retrieve full gene annotation using canonical symbol
            $stmtMain = mysqli_prepare($conn, "SELECT * FROM gene_annotation WHERE symbol = ? LIMIT 1");
            if ($stmtMain) {
                mysqli_stmt_bind_param($stmtMain, "s", $mainSymbol);
                mysqli_stmt_execute($stmtMain);
                $resMain = mysqli_stmt_get_result($stmtMain);
                $geneRow = mysqli_fetch_assoc($resMain);
                mysqli_stmt_close($stmtMain);
                return $geneRow;
            }
        } else {
            mysqli_stmt_close($stmtAlias);
        }
    }

    return null;
}

function getAliasData($conn, $hgncId, $symbol) {
    $hgncClean = str_replace('HGNC:', '', $hgncId);
    $hgncPrefixed = 'HGNC:' . $hgncClean;
    
    $sql = "SELECT alias_symbol, prev_symbol FROM alias_tb 
            WHERE symbol = ? OR hgnc_id = ? OR hgnc_id = ? 
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return null;
    
    mysqli_stmt_bind_param($stmt, "sss", $symbol, $hgncId, $hgncPrefixed);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

function getDiseaseData($conn, $symbol) {
    $sql = "SELECT diseaseName, MONDO, Source FROM Disease_tb WHERE Approved_symbol = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return null;
    mysqli_stmt_bind_param($stmt, "s", $symbol);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function getTissueData($conn, $hgncId, $geneId) {
    $hgncClean = str_replace('HGNC:', '', $hgncId);
    $hgncPrefixed = 'HGNC:' . $hgncClean;
    
    $sql = "SELECT * FROM gene_wise_tb WHERE HGNC_ID = ? OR HGNC_ID = ? OR Gene_ID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return null;
    
    mysqli_stmt_bind_param($stmt, "sss", $hgncId, $hgncPrefixed, $geneId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function getPtmData($conn, $hgncId) {
    $hgncClean = str_replace('HGNC:', '', $hgncId);
    $hgncPrefixed = 'HGNC:' . $hgncClean;
    
    $sql = "SELECT uniprot, hgnc_id, ptm, site, source 
            FROM ptm_data 
            WHERE hgnc_id = ? OR hgnc_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return null;
    
    mysqli_stmt_bind_param($stmt, "ss", $hgncId, $hgncPrefixed);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

function getPpiData($conn, $symbol, $aliases = []) {
    // Collect all candidate symbols/aliases for matching
    $searchSymbols = array_values(array_unique(array_filter(array_merge([$symbol], $aliases))));
    if (empty($searchSymbols)) {
        $searchSymbols = [$symbol];
    }
    
    $placeholders = implode(',', array_fill(0, count($searchSymbols), '?'));
    $types = str_repeat('s', count($searchSymbols) * 4);
    $params = array_merge($searchSymbols, $searchSymbols, $searchSymbols, $searchSymbols);

    // Bidirectional query across preferredName and hgnc_symbol for both A and B
    $sql = "SELECT preferredName_A, hgnc_symbol_A, preferredName_B, hgnc_symbol_B, score 
            FROM Prot_string_with_HGNC 
            WHERE preferredName_A IN ($placeholders) 
               OR hgnc_symbol_A IN ($placeholders)
               OR preferredName_B IN ($placeholders)
               OR hgnc_symbol_B IN ($placeholders)
            ORDER BY score DESC 
            LIMIT 50";
            
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) return [];
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $elements = [];
    $seenPartners = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Determine whether side A or side B is the query gene
        $isSideA = false;
        foreach ($searchSymbols as $s) {
            if ((!empty($row['hgnc_symbol_A']) && strcasecmp($row['hgnc_symbol_A'], $s) === 0) ||
                (!empty($row['preferredName_A']) && strcasecmp($row['preferredName_A'], $s) === 0)) {
                $isSideA = true;
                break;
            }
        }

        // Partner gene is the opposite side
        if ($isSideA) {
            $partner = !empty($row['hgnc_symbol_B']) ? trim($row['hgnc_symbol_B']) : trim($row['preferredName_B']);
        } else {
            $partner = !empty($row['hgnc_symbol_A']) ? trim($row['hgnc_symbol_A']) : trim($row['preferredName_A']);
        }

        if (empty($partner) || strcasecmp($partner, $symbol) === 0 || isset($seenPartners[strtolower($partner)])) {
            continue;
        }

        // Exclude self-aliases
        $isSelf = false;
        foreach ($searchSymbols as $s) {
            if (strcasecmp($partner, $s) === 0) {
                $isSelf = true;
                break;
            }
        }
        if ($isSelf) continue;

        $seenPartners[strtolower($partner)] = true;
        $score = (float)$row['score'];
        
        $elements[] = [
            'group' => 'nodes',
            'data' => [
                'id' => $partner, 
                'label' => $partner, 
                'nodeType' => 'partner',
                'score' => $score 
            ]
        ];
        
        $elements[] = [
            'group' => 'edges',
            'data' => [
                'source' => $symbol, 
                'target' => $partner, 
                'score' => $score
            ]
        ];
    }
    
    // Add central input gene node if partner interactions were found
    if (!empty($elements)) {
        array_unshift($elements, [
            'group' => 'nodes',
            'data' => [
                'id' => $symbol, 
                'label' => $symbol, 
                'nodeType' => 'main',
                'score' => 1.0
            ]
        ]);
    }
    
    return $elements;
}

function parseIdList($rawString) {
    if (empty($rawString) || trim((string)$rawString) === '') return [];
    $items = preg_split('/[;,|]+/', trim((string)$rawString), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_filter(array_map('trim', $items))));
}

function getSourceInfo($sourceName) {
    $clean = trim((string)$sourceName);
    $lower = strtolower($clean);
    
    $map = [
        'cgdb'        => ['label' => 'CGDB',        'url' => 'https://doi.org/10.1093/nar/gkw1028'],
        'circadb'     => ['label' => 'CircaDB',     'url' => 'https://doi.org/10.1093/nar/gks1161'],
        'rhythmicdb'  => ['label' => 'RhythmicDB',  'url' => 'https://doi.org/10.3389/fgene.2022.882044'],
        'publication' => ['label' => 'GTEx',        'url' => 'https://doi.org/10.1371/journal.pbio.3001986'],
        'gtex'        => ['label' => 'GTEx',        'url' => 'https://doi.org/10.1371/journal.pbio.3001986'],
        'circakb'     => ['label' => 'CircaKB',     'url' => 'https://doi.org/10.1093/nar/gkae817'],
    ];
    
    if (isset($map[$lower])) {
        return $map[$lower];
    }
    
    return ['label' => $clean, 'url' => ''];
}

function formatCoordValue($val) {
    $val = trim((string)$val);
    if ($val === '' || strtolower($val) === 'null') return '';
    return htmlspecialchars($val);
}

function displayValue($value) { 
    return (isset($value) && trim((string)$value) !== '' && $value !== null) 
        ? htmlspecialchars($value) : 'Null'; 
}

$geneData = getGeneData($conn, $keyword);
$diseaseResult = null;
$ptmResult = null; 
$ppiJson = "[]"; 
$tissueJson = "null";
$goList = [];
$pathwayList = [];
$sourceList = [];
$aliasList = [];

$domainPlotUrl = "";
$domainPlotExists = false;

if ($geneData) {
    $symbol = $geneData['symbol'];
    $diseaseResult = getDiseaseData($conn, $symbol);
    
    // Parse GO & Pathways from gene_annotation table
    $goList = parseIdList($geneData['GO_ID'] ?? '');
    $pathwayList = parseIdList($geneData['reactome_id'] ?? '');
    
    // Parse Source column specific to this gene
    $rawSource = $geneData['source'] ?? $geneData['Source'] ?? $geneData['SOURCE'] ?? '';
    $sourceList = parseIdList($rawSource);

    $hgncRaw = trim((string)($geneData['hgnc_id'] ?? '')); 
    $geneIdRaw = trim((string)($geneData['entrez_id'] ?? ''));

    // Fetch Alias / Previous Symbols from alias_tb
    $aliasData = getAliasData($conn, $hgncRaw, $symbol);
    if ($aliasData) {
        if (!empty($aliasData['alias_symbol'])) {
            $aliasList = array_merge($aliasList, parseIdList($aliasData['alias_symbol']));
        }
        if (!empty($aliasData['prev_symbol'])) {
            $aliasList = array_merge($aliasList, parseIdList($aliasData['prev_symbol']));
        }
        $aliasList = array_values(array_unique(array_filter(array_map('trim', $aliasList))));
    }

    // Fetch PPI Network Elements (using symbol and aliases bidirectionally)
    $ppiElements = getPpiData($conn, $symbol, $aliasList);
    $ppiJson = json_encode($ppiElements);

    // Fetch PTM Data
    $ptmResult = getPtmData($conn, $hgncRaw);

    $tissueData = getTissueData($conn, $hgncRaw, $geneIdRaw);
    if($tissueData) {
        $tissueJson = json_encode($tissueData);
    }

    // --- Dynamic UniProt ID Extraction Fallback ---
    $uniprotId = '';
    
    if (!empty($geneData['uniprot_ids'])) {
        $parsedUnis = parseIdList($geneData['uniprot_ids']);
        if (!empty($parsedUnis)) {
            $uniprotId = $parsedUnis[0];
        }
    }
    
    if (empty($uniprotId) && !empty($hgncRaw)) {
        $hgncClean = str_replace('HGNC:', '', $hgncRaw);
        $hgncPrefixed = 'HGNC:' . $hgncClean;
        $ptmSql = "SELECT uniprot FROM ptm_data WHERE hgnc_id = ? OR hgnc_id = ? LIMIT 1";
        $ptmStmt = mysqli_prepare($conn, $ptmSql);
        if ($ptmStmt) {
            mysqli_stmt_bind_param($ptmStmt, "ss", $hgncRaw, $hgncPrefixed);
            mysqli_stmt_execute($ptmStmt);
            $ptmRes = mysqli_stmt_get_result($ptmStmt);
            if ($ptmRow = mysqli_fetch_assoc($ptmRes)) {
                if (!empty($ptmRow['uniprot'])) {
                    $uniprotId = trim($ptmRow['uniprot']);
                }
            }
            mysqli_stmt_close($ptmStmt);
        }
    }
    
    if (empty($uniprotId) && !empty($tissueData)) {
        foreach (['UniProt_ID', 'uniprot', 'UniProt', 'Uniprot', 'uniprot_id'] as $key) {
            if (isset($tissueData[$key]) && !empty(trim((string)$tissueData[$key]))) {
                $uniprotId = trim((string)$tissueData[$key]);
                break;
            }
        }
    }
    
    if (empty($uniprotId)) {
        $uniprotId = $symbol;
    }

    $organs = [
        "adipose"    => ["label"=>"Adipose Tissue",       "tissues"=>["Adipose_Subcutaneous","Adipose_Visceral_Omentum"],                                        "color"=>"#fb923c","side"=>"left"],
        "heart"      => ["label"=>"Cardiovascular",        "tissues"=>["Heart_Atrial_Appendage","Heart_Left_Ventricle","Artery_Aorta","Artery_Coronary","Artery_Tibial"], "color"=>"#ef4444","side"=>"left"],
        "endocrine"  => ["label"=>"Endocrine Glands",      "tissues"=>["Adrenal_Gland","Pituitary","Thyroid"],                                                   "color"=>"#a78bfa","side"=>"left"],
        "lung"       => ["label"=>"Lung",                  "tissues"=>["Lung"],                                                                                   "color"=>"#34d399","side"=>"left"],
        "immune"     => ["label"=>"Immune / Blood",        "tissues"=>["Spleen","Whole_Blood","Cells_EBV_transformed_lymphocytes"],                               "color"=>"#f87171","side"=>"left"],
        "liver"      => ["label"=>"Liver",                 "tissues"=>["Liver","Liver_Hepatocyte","Liver_Mixed_Cell","Liver_Portal_Tract"],                       "color"=>"#10b981","side"=>"left"],
        "male_repro" => ["label"=>"Male Reproductive",     "tissues"=>["Testis","Prostate"],                                                                      "color"=>"#60a5fa","side"=>"left"],
        "muscle"     => ["label"=>"Muscle / Nerve",        "tissues"=>["Muscle_Skeletal","Nerve_Tibial"],                                                         "color"=>"#f59e0b","side"=>"left"],
        "brain"      => ["label"=>"Brain",                 "tissues"=>["Brain_Cortex","Brain_Cerebellum","Brain_Hippocampus","Brain_Amygdala","Brain_Hypothalamus","Brain_Frontal_Cortex_BA9","Brain_Caudate_basal_ganglia","Brain_Putamen_basal_ganglia","Brain_Substantia_nigra","Brain_Spinal_cord_cervical_c_1","Brain_Nucleus_accumbens_basal_ganglia","Brain_Anterior_cingulate_cortex_BA24","Brain_Cerebellar_Hemisphere"], "color"=>"#818cf8","side"=>"right"],
        "digest_up"  => ["label"=>"Upper Digestive",       "tissues"=>["Minor_Salivary_Gland","Esophagus_Mucosa","Esophagus_Muscularis","Esophagus_Gastroesophageal_Junction"], "color"=>"#f472b6","side"=>"right"],
        "digest_lo"  => ["label"=>"GI Tract",              "tissues"=>["Stomach","Stomach_Mucosa","Stomach_Mucosa","Stomach_Muscularis","Colon_Sigmoid","Colon_Transverse","Colon_Transverse_Mucosa","Small_Intestine_Terminal_Ileum"], "color"=>"#fb923c","side"=>"right"],
        "pancreas"   => ["label"=>"Pancreas",              "tissues"=>["Pancreas","Pancreas_Acini","Pancreas_Islets","Pancreas_Mixed_Cell"],                       "color"=>"#fbbf24","side"=>"right"],
        "kidney"     => ["label"=>"Kidney / Bladder",      "tissues"=>["Kidney_Cortex","Kidney_Medulla","Bladder"],                                               "color"=>"#38bdf8","side"=>"right"],
        "female_repro"=>["label"=>"Female Reproductive",   "tissues"=>["Breast_Mammary_Tissue","Vagina","Cervix_Ectocervix","Cervix_Endocervix","Fallopian_Tube","Ovary","Uterus"], "color"=>"#f9a8d4","side"=>"right"],
        "skin"       => ["label"=>"Skin",                  "tissues"=>["Skin_Not_Sun_Exposed_Suprapubic","Skin_Sun_Exposed_Lower_leg"],                           "color"=>"#fcd34d","side"=>"right"],
    ];

    function resolvePathSmart($baseFolder, $hgncID, $suffix) {
        $dir = __DIR__ . '/' . $baseFolder;
        $exactName = $hgncID . $suffix;
        if (file_exists($dir . $exactName)) {
            $urlSafeName = str_replace(':', '%3A', $hgncID) . $suffix;
            return [true, $baseFolder . $urlSafeName];
        }
        $underscoreID = str_replace(':', '_', $hgncID);
        $underscoreName = $underscoreID . $suffix;
        if (file_exists($dir . $underscoreName)) {
            return [true, $baseFolder . $underscoreName];
        }
        return [false, ""];
    }

    list($domainPlotExists, $domainPlotUrl) = resolvePathSmart(
        "eXPRESSION_PROFILE_DATA/Lollipop/", $hgncRaw, ".html");
}
?>

<head>
    <title>Gene Profile</title>
</head>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.26.0/cytoscape.min.js"></script>

<style>
    /* --- SCOPED STYLES & LAYOUT --- */
    .gene-page-wrapper {
        font-family: 'Poppins', sans-serif;
        color: #334155;
        --primary-color: #1e40af;
        --primary-dark: #10428d;
        --secondary-color: #f8fafc;
        --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --shadow-hover: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
    }

    .gene-page-wrapper .content-area { width: 100%; margin: 0 auto; }

    /* BLUE GRADIENT HERO SECTION */
    .gene-page-wrapper .hero-section {
        background: linear-gradient(135deg, #10428d 0%, #1e40af 100%);
        border-radius: 12px; padding: 30px; color: white;
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 25px; box-shadow: var(--shadow); flex-wrap: wrap;
        gap: 20px;
    }

    .gene-page-wrapper .hero-text { flex: 1; min-width: 300px; padding-right: 20px; }
    .gene-page-wrapper .hero-text h1 { 
        margin: 0; font-size: 2.5rem; font-weight: 700; color: white; line-height: 1.2; 
    }
    .gene-page-wrapper .hero-text .description { 
        display: block; margin-top: 8px; opacity: 0.9; font-weight: 300; 
        font-size: 1.05rem; color: #e0e7ff; line-height: 1.5;
    }

    .gene-page-wrapper .db-tag {
        display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px;
        border-radius: 20px; font-size: 0.85rem; font-weight: 500;
        border: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.6);
        transition: all 0.3s ease; background: rgba(0,0,0,0.1);
        text-decoration: none; 
    }
    .gene-page-wrapper .db-tag.active {
        background: white; border-color: white; color: var(--tag-color, #333);
        font-weight: 700; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transform: translateY(-2px);
    }
    .gene-page-wrapper .db-tag.active i { color: inherit; }

    /* Search Box Styling */
    .gene-page-wrapper .search-wrapper-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
    }

    .gene-page-wrapper .search-container { 
        position: relative; margin-top: 0; display: flex; 
    }
    .gene-page-wrapper .search-input {
        padding: 12px 20px 12px 45px; border-radius: 50px; border: none;
        width: 300px; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: width 0.3s ease;
    }
    .gene-page-wrapper .search-input:focus { outline: none; width: 350px; }
    .gene-page-wrapper .search-icon { 
        position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; 
    }
    .gene-page-wrapper .search-btn {
        background: #10b981; color: white; border: none; padding: 12px 25px;
        border-radius: 50px; margin-left: 10px; cursor: pointer; font-weight: 600;
        transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .gene-page-wrapper .search-btn:hover { background: #059669; }

    /* VIDEO TUTORIAL BUTTON */
    .gene-page-wrapper .video-tutorial-hero-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 8px 24px !important;
        border-radius: 50px !important;
        background-color: #ffffff !important;
        border: 2px solid #0d6efd !important;
        color: #0d6efd !important;
        font-weight: 600 !important;
        font-size: 0.95rem !important;
        text-decoration: none !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .gene-page-wrapper .video-tutorial-hero-btn:hover {
        background-color: #f0f7ff !important;
        border-color: #0d6efd !important;
        color: #0a58ca !important;
        transform: translateY(-1px);
    }
    .gene-page-wrapper .video-tutorial-hero-btn i {
        font-size: 1.05rem;
        color: #0d6efd !important;
    }

    /* Auto-suggest */
    .gene-page-wrapper .suggestion-box {
        position: absolute; top: 100%; left: 0; width: 350px; 
        background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px;
        z-index: 1050; max-height: 250px; overflow-y: auto; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: none; margin-top: 8px;
    }
    .gene-page-wrapper .suggestion-item {
        padding: 12px 20px; cursor: pointer; color: #334155; 
        border-bottom: 1px solid #f1f5f9; text-align: left; 
        font-weight: 500; transition: background 0.2s;
    }
    .gene-page-wrapper .suggestion-item:last-child { border-bottom: none; }
    .gene-page-wrapper .suggestion-item:hover { background-color: #eff6ff; color: #1e40af; }

    /* SECTION CONTAINERS */
    .gene-page-wrapper .page-section {
        margin-bottom: 40px;
    }

    /* DASHBOARD GRID SYSTEM */
    .gene-page-wrapper .dashboard-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 25px; 
        align-items: start;
    }
    .gene-page-wrapper .left-section, 
    .gene-page-wrapper .right-section {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    /* CARD SYSTEM */
    .gene-page-wrapper .card {
        background: #ffffff; 
        border-radius: 8px; 
        padding: 0; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.04); 
        overflow: hidden; 
        border: 1px solid #cbd5e1; 
        margin-bottom: 0;
    }
    .gene-page-wrapper .card h3 {
        font-size: 18px;
        font-weight: bold;
        text-align: left;
        background: #f1f1f1;
        padding: 12px 18px;
        border-bottom: 2px solid #ccc;
        margin: 0;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .gene-page-wrapper .card h3 i { 
        color: #475569; 
    }
    .gene-page-wrapper .card-content { 
        padding: 20px; 
    }

    /* TABULAR DATA */
    .gene-page-wrapper .tabular-data { 
        width: 100%; 
        border-collapse: collapse; 
    }
    .gene-page-wrapper .tabular-data td { 
        padding: 12px 10px; 
        border-bottom: 1px solid #f1f5f9; 
        font-size: 0.92rem;
        color: #334155;
    }
    .gene-page-wrapper .tabular-data tr:last-child td { 
        border-bottom: none; 
    }
    .gene-page-wrapper .tabular-data td:first-child { 
        font-weight: bold; 
        color: #475569; 
        width: 35%; 
    }
    .gene-page-wrapper .tabular-data a { 
        color: var(--primary-color); 
        text-decoration: none; 
        font-weight: 500; 
    }
    .gene-page-wrapper .tabular-data a:hover { 
        text-decoration: underline; 
    }

    /* GENERAL STYLES */
    .gene-page-wrapper .styled-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    .gene-page-wrapper .styled-table thead { background: #f1f5f9; }
    .gene-page-wrapper .styled-table th { 
        padding: 15px; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem; 
    }
    .gene-page-wrapper .styled-table td { 
        padding: 12px 15px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 0.9rem; 
    }
    .gene-page-wrapper .styled-table tr:hover { background-color: #f8fafc; }

    .gene-page-wrapper .empty-state {
        height: 300px; display: flex; flex-direction: column; align-items: center; 
        justify-content: center; background: #f8fafc; border: 2px dashed #cbd5e1; 
        border-radius: 8px; color: #64748b;
    }
    .gene-page-wrapper .empty-state i { font-size: 40px; margin-bottom: 15px; opacity: 0.5; }

    .gene-page-wrapper .pagination { 
        display: flex; justify-content: flex-end; gap: 5px; padding-top: 15px; 
    }
    .gene-page-wrapper .page-link {
        padding: 8px 14px; border: 1px solid #e2e8f0; background: white; 
        color: var(--primary-color); border-radius: 6px; cursor: pointer; 
        font-size: 0.9rem; transition: 0.2s; text-decoration: none;
    }
    .gene-page-wrapper .page-link:hover { background: #eff6ff; }
    .gene-page-wrapper .page-link.active { 
        background: var(--primary-color); color: white; border-color: var(--primary-color); 
    }
    .gene-page-wrapper .page-link.disabled { opacity: 0.5; cursor: not-allowed; }

    #cy { 
        width: 100%; height: 500px; display: block; background-color: #fdfdfd; 
        border-radius: 8px; border: 1px solid #e5e7eb; 
    }
    .ppi-legend {
        display: flex; gap: 20px; justify-content: center; padding: 10px; 
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; 
        margin-bottom: 15px; font-size: 0.9rem;
    }
    .ppi-legend-item { display: flex; align-items: center; gap: 8px; }
    .dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

    /* PPI SPLIT GRID */
    .gene-page-wrapper .ppi-split-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        align-items: stretch; 
    }
    .gene-page-wrapper .ppi-graph-col {
        display: flex;
        flex-direction: column;
        gap: 15px;
        width: 100%;
        min-width: 0; 
    }
    .gene-page-wrapper .ppi-tools-col {
        display: flex;
        flex-direction: column;
        gap: 15px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 20px;
        min-width: 0; 
        box-sizing: border-box;
    }

    .gene-page-wrapper .ppi-reset-btn {
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background-color: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: 'Poppins', sans-serif;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .gene-page-wrapper .ppi-reset-btn:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #1e293b;
    }

    /* TISSUE MAP STYLES */
    .gene-page-wrapper .tissue-grid { 
        display: grid; grid-template-columns: 280px 1fr; gap: 30px; 
    }
    .gene-page-wrapper .anatomy-section {
        background: #f8fafc; border-radius: 12px; padding: 20px; 
        border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center;
    }
    .gene-page-wrapper .chart-section {
        background: #fff; padding: 15px; overflow-y: auto; max-height: 600px;
        border-radius: 8px; border: 1px solid #e2e8f0;
    }
    .gene-page-wrapper .css-bar-row {
        display: flex; align-items: center; margin-bottom: 10px; font-size: 0.82rem;
    }
    .gene-page-wrapper .css-bar-label {
        width: 280px; flex-shrink: 0; padding-right: 12px; color: #475569; 
        text-align: right; font-weight: 500; white-space: nowrap; 
        overflow: hidden; text-overflow: ellipsis;
    }
    .gene-page-wrapper .css-bar-track {
        flex-grow: 1; background: #f1f5f9; height: 13px; 
        border-radius: 7px; overflow: hidden; position: relative;
    }
    .gene-page-wrapper .css-bar-fill {
        height: 100%; border-radius: 7px; transition: width 1s ease-out;
    }
    .gene-page-wrapper .css-bar-value {
        width: 65px; flex-shrink: 0; padding-left: 10px; 
        color: #334155; font-weight: 600; text-align: left;
    }

    .gene-page-wrapper .level-filter-btn {
        padding: 4px 12px; border-radius: 12px; border: 1.5px solid #cbd5e1;
        background: white; font-size: 11px; cursor: pointer; font-weight: 600;
        transition: all 0.2s ease;
    }
    .gene-page-wrapper .level-filter-btn:hover { opacity: 0.85; }

    .gene-page-wrapper .organ-info-panel {
        margin-top: 12px; padding: 10px 14px; background: white;
        border-radius: 8px; border: 1px solid #e2e8f0;
        font-size: 12px; color: #334155; display: none; text-align: left;
        width: 100%; box-sizing: border-box; border-left: 4px solid #1e40af;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    @keyframes tissueMarkerPulse {
        0%   { box-shadow: 0 0 0 0   rgba(239,68,68,0.55), 0 2px 8px rgba(0,0,0,0.3); }
        70%  { box-shadow: 0 0 0 9px rgba(239,68,68,0),    0 2px 8px rgba(0,0,0,0.3); }
        100% { box-shadow: 0 0 0 0   rgba(239,68,68,0),    0 2px 8px rgba(0,0,0,0.3); }
    }

    .gene-page-wrapper .tissue-grid-cols {
        display: grid;
        grid-template-columns: 240px 1fr 240px;
        gap: 20px;
        align-items: start;
        padding: 10px 5px;
    }
    .gene-page-wrapper .side-col {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .gene-page-wrapper .organ-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        width: 100%;
        font-family: 'Poppins', sans-serif;
    }
    .gene-page-wrapper .organ-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .gene-page-wrapper .organ-btn.active {
        border-color: var(--btn-color, #1e40af);
        background: rgba(30, 64, 175, 0.05);
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.08);
    }
    .gene-page-wrapper .btn-label {
        font-weight: 600;
        font-size: 0.82rem;
        line-height: 1.2;
        color: #1e293b;
    }
    .gene-page-wrapper .btn-sub {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 2px;
    }

    /* CENTER COLUMN */
    .gene-page-wrapper .center-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
        position: relative;
    }
    .gene-page-wrapper .instruction {
        font-size: 0.82rem;
        color: #64748b;
        text-align: center;
        max-width: 450px;
        line-height: 1.5;
        background: #f1f5f9;
        padding: 8px 14px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .gene-page-wrapper .body-svg-wrap {
        position: relative;
        width: 220px;
        background: #ffffff;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: var(--shadow);
    }
    .gene-page-wrapper .body-svg-wrap svg {
        width: 100%;
        height: auto;
    }
    .gene-page-wrapper .body-region {
        fill: #f1f5f9;
        stroke: #cbd5e1;
        stroke-width: 1.2;
        transition: fill .35s, filter .35s, stroke .35s;
        cursor: pointer;
    }
    .gene-page-wrapper .body-region:hover {
        fill: #e2e8f0;
    }
    .gene-page-wrapper .body-region.highlighted {
        fill: var(--region-color, #1e40af);
        stroke: var(--region-color, #1e40af);
        filter: drop-shadow(0 0 8px var(--region-color, #1e40af));
        animation: pulse-region-glow 2s ease-in-out infinite;
    }
    .gene-page-wrapper .scan-line {
        position: absolute;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(30, 64, 175, 0.4), transparent);
        top: 0;
        animation: scan-body 4s linear infinite;
        pointer-events: none;
        opacity: 0;
        transition: opacity .5s;
    }
    .gene-page-wrapper .scan-line.active {
        opacity: 1;
    }

    /* QUANTITATIVE EXPRESSION CARD */
    .gene-page-wrapper .expr-card {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        box-shadow: var(--shadow);
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .gene-page-wrapper .expr-card.lit {
        border-color: var(--active-color, #1e40af);
        box-shadow: 0 4px 20px rgba(30, 64, 175, 0.08);
    }
    .gene-page-wrapper .expr-card h3 {
        margin: 0 0 12px 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
    }
    .gene-page-wrapper .bars-wrap {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 250px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .gene-page-wrapper .bars-wrap::-webkit-scrollbar {
        width: 4px;
    }
    .gene-page-wrapper .bars-wrap::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .gene-page-wrapper .bar-row {
        display: grid;
        grid-template-columns: 140px 1fr 55px;
        align-items: center;
        gap: 10px;
    }
    .gene-page-wrapper .bar-name {
        font-size: 0.75rem;
        color: #475569;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: right;
        font-weight: 500;
    }
    .gene-page-wrapper .bar-track {
        height: 8px;
        background: #f1f5f9;
        border-radius: 4px;
        overflow: hidden;
    }
    .gene-page-wrapper .bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.6s ease;
    }
    .gene-page-wrapper .bar-val {
        font-size: 0.75rem;
        color: #475569;
        font-weight: 600;
        text-align: left;
    }

    @media (max-width: 1024px) {
        .gene-page-wrapper .tissue-grid-cols {
            grid-template-columns: 1fr;
        }
        .gene-page-wrapper .side-col.left,
        .gene-page-wrapper .side-col.right {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
    }
</style>

<!-- WRAPPER -->
<div class="gene-page-wrapper">
<div class="content-area">

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="hero-text">
            <h1 style="font-size: 2.0rem; font-weight: 700; margin: 0; color: #ffffff;">
                Gene Profile: <?php echo $geneData ? displayValue($geneData['symbol']) : 'Gene Not Found'; ?>
            </h1>
            <span class="description" style="font-size: 1.15rem;">
                <?php echo $geneData 
                    ? displayValue($geneData['name']) 
                    : 'Please search for a valid gene symbol'; ?>
            </span>
        </div>

        <div class="search-wrapper-right">
            <form method="POST" action="" class="search-container" autocomplete="off">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="keyword" class="search-input" 
                       placeholder="Search Gene (e.g. PER3)..." 
                       value="<?php echo htmlspecialchars($searchKeyword); ?>" required>
                <button type="submit" class="search-btn">Search</button>
            </form>
            
            <!-- WATCH VIDEO TUTORIAL BUTTON -->
            <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Gene_Profile.mp4" 
               target="_blank" 
               class="video-tutorial-hero-btn mt-3">
                <i class="fas fa-video me-1"></i>
                <span>Watch Video Tutorial</span>
            </a>
        </div>
    </div>

    <?php if (!$geneData): ?>
        <div class="empty-state">
            <i class="fas fa-dna"></i>
            <p>No data found for '<strong><?php echo htmlspecialchars($searchKeyword); ?></strong>'. 
               Try searching for 'PER3'.</p>
        </div>
    <?php else: ?>

    <!-- ==================== SECTION 1: OVERVIEW ==================== -->
    <div class="page-section">
        <div class="dashboard-grid">
            
            <!-- LEFT SECTION -->
            <div class="left-section">
                
                <!-- Card 1: Gene Overview -->
                <div class="card tabular-style">
                    <h3><i class="fas fa-dna"></i> Gene Overview</h3>
                    <div class="card-content">
                        <table class="tabular-data">
                            <tr>
                                <td>Symbol:</td>
                                <td><em><?php echo displayValue($geneData['symbol']); ?></em></td>
                            </tr>
                            <tr>
                                <td>Entrez Gene ID:</td>
                                <td>
                                    <?php if (displayValue($geneData['entrez_id']) !== 'Null'): ?>
                                        <a href="https://www.ncbi.nlm.nih.gov/gene/<?php echo $geneData['entrez_id']; ?>" target="_blank">
                                            <?php echo displayValue($geneData['entrez_id']); ?>
                                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                        </a>
                                    <?php else: ?>
                                        Null
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>HGNC ID:</td>
                                <td>
                                    <?php if (displayValue($geneData['hgnc_id']) !== 'Null'): 
                                        $hgncVal = trim($geneData['hgnc_id']);
                                    ?>
                                        <a href="https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/<?php echo urlencode($hgncVal); ?>" target="_blank">
                                            <?php echo htmlspecialchars($hgncVal); ?>
                                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                        </a>
                                    <?php else: ?>
                                        Null
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Source Database(s):</td>
                                <td>
                                    <?php if (!empty($sourceList)): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php foreach ($sourceList as $src): 
                                                $srcInfo = getSourceInfo($src);
                                            ?>
                                                <?php if (!empty($srcInfo['url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($srcInfo['url']); ?>" 
                                                       target="_blank" 
                                                       rel="noopener noreferrer"
                                                       style="display: inline-flex; align-items: center; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; text-decoration: none;"
                                                       title="View Publication DOI for <?php echo htmlspecialchars($srcInfo['label']); ?>">
                                                        <?php echo htmlspecialchars($srcInfo['label']); ?>
                                                        <i class="fas fa-external-link-alt" style="font-size: 9px; margin-left: 4px;"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">
                                                        <?php echo htmlspecialchars($srcInfo['label']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        Null
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Alias Symbol:</td>
                                <td>
                                    <?php if (!empty($aliasList)): ?>
                                        <span style="color: #334155; font-weight: 500;">
                                            <?php echo htmlspecialchars(implode(', ', $aliasList)); ?>
                                        </span>
                                    <?php else: ?>
                                        Null
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card 2: Genomic Mapping -->
                <div class="card tabular-style">
                    <h3><i class="fas fa-location-crosshairs"></i> Genomic Mapping</h3>
                    <div class="card-content">
                        <table class="tabular-data">
                            <tr>
                                <td>Ensembl ID:</td>
                                <td>
                                    <?php if (displayValue($geneData['ensembl_gene_id']) !== 'Null'): ?>
                                        <a href="https://www.ensembl.org/Homo_sapiens/Gene/Summary?g=<?php echo urlencode($geneData['ensembl_gene_id']); ?>" target="_blank">
                                            <?php echo displayValue($geneData['ensembl_gene_id']); ?>
                                            <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                                        </a>
                                    <?php else: ?>
                                        Null
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Locus Type:</td>
                                <td>
                                    <span style="background:#e0f2fe; color:#0284c7; padding:2px 8px; border-radius:4px; font-size:12px; font-weight: 500;">
                                        <?php echo displayValue($geneData['locus_type']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Chromosome:</td>
                                <td>Chr <?php echo displayValue($geneData['Chromosome']); ?></td>
                            </tr>
                            <tr>
                                <td>GRCh38 (hg38):</td>
                                <td>
                                    <?php 
                                    $chr = !empty($geneData['Chromosome']) ? 'Chr ' . htmlspecialchars($geneData['Chromosome']) . ': ' : '';
                                    $gStart = formatCoordValue($geneData['grch38_start'] ?? '');
                                    $gEnd   = formatCoordValue($geneData['grch38_end'] ?? '');
                                    if ($gStart !== '' && $gEnd !== '') {
                                        echo $chr . $gStart . " &ndash; " . $gEnd;
                                    } elseif ($gStart !== '') {
                                        echo $chr . $gStart;
                                    } elseif ($gEnd !== '') {
                                        echo $chr . $gEnd;
                                    } else {
                                        echo 'Null';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td>T2T-CHM13:</td>
                                <td>
                                    <?php 
                                    $chr = !empty($geneData['Chromosome']) ? 'Chr ' . htmlspecialchars($geneData['Chromosome']) . ': ' : '';
                                    $tStart = formatCoordValue($geneData['t2t_start'] ?? '');
                                    $tEnd   = formatCoordValue($geneData['t2t_end'] ?? '');
                                    if ($tStart !== '' && $tEnd !== '') {
                                        echo $chr . $tStart . " &ndash; " . $tEnd;
                                    } elseif ($tStart !== '') {
                                        echo $chr . $tStart;
                                    } elseif ($tEnd !== '') {
                                        echo $chr . $tEnd;
                                    } else {
                                        echo 'Null';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>

            <!-- RIGHT SECTION -->
            <div class="right-section">
                
                <!-- Card 3: Summary -->
                <div class="card">
                    <h3><i class="fas fa-align-left"></i> Summary</h3>
                    <div class="card-content">
                        <p style="line-height:1.6; color:#475569; margin: 0; font-size:0.95rem;">
                            <?php echo (displayValue($geneData['RefSeq_Summary']) !== 'Null') 
                                ? displayValue($geneData['RefSeq_Summary']) 
                                : "No detailed summary available for this gene."; ?>
                        </p>
                    </div>
                </div>

                <!-- Card 4: Gene Ontology & Pathways -->
                <div class="card">
                    <h3><i class="fas fa-tags"></i> Gene Ontology & Pathways</h3>
                    <div class="card-content" style="padding: 18px;">
                        <div style="display: flex; gap: 20px; flex-direction: column;">
                            
                            <!-- Gene Ontology -->
                            <div>
                                <h4 style="margin: 0 0 8px 0; font-size: 0.85rem; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 4px;">Gene Ontology (GO)</h4>
                                <div style="max-height: 180px; overflow-y: auto; display: flex; flex-wrap: wrap; gap: 6px; padding: 4px 4px 4px 0;">
                                    <?php if (!empty($goList)): ?>
                                        <?php foreach ($goList as $goId): 
                                            $goLink = "https://www.ebi.ac.uk/QuickGO/GTerm?id=" . urlencode($goId);
                                        ?>
                                            <a href="<?php echo $goLink; ?>" 
                                               target="_blank" 
                                               style="display: inline-flex; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 11px; text-decoration: none; font-weight: 500; transition: all 0.2s ease;"
                                               onmouseenter="this.style.backgroundColor='#eff6ff'; this.style.borderColor='#1e40af'; this.style.transform='translateY(-1px)';"
                                               onmouseleave="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#cbd5e1'; this.style.transform='translateY(0)';"
                                            >
                                                <i class="fas fa-external-link-alt" style="font-size: 8px; margin-right: 6px; color: #94a3b8;"></i>
                                                <?php echo htmlspecialchars($goId); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #64748b; font-style: italic;">No GO assignment records.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pathways -->
                            <div>
                                <h4 style="margin: 0 0 8px 0; font-size: 0.85rem; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 4px;">Biological Pathways (Reactome)</h4>
                                <div style="max-height: 180px; overflow-y: auto; display: flex; flex-wrap: wrap; gap: 6px; padding: 4px 4px 4px 0;">
                                    <?php if (!empty($pathwayList)): ?>
                                        <?php foreach ($pathwayList as $pId): 
                                            $pLink = "https://reactome.org/PathwayBrowser/#/" . urlencode($pId);
                                        ?>
                                            <a href="<?php echo $pLink; ?>" 
                                               target="_blank" 
                                               style="display: inline-flex; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 11px; text-decoration: none; font-weight: 500; transition: all 0.2s ease;"
                                               onmouseenter="this.style.backgroundColor='#eff6ff'; this.style.borderColor='#1e40af'; this.style.transform='translateY(-1px)';"
                                               onmouseleave="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#cbd5e1'; this.style.transform='translateY(0)';"
                                            >
                                                <i class="fas fa-project-diagram" style="font-size: 8px; margin-right: 6px; color: #94a3b8;"></i>
                                                <?php echo htmlspecialchars($pId); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #64748b; font-style: italic;">No pathway correlations found in database.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- ==================== SECTION 2: TISSUE MAP ==================== -->
    <div class="page-section">
        <div class="card">
            <h3><i class="fas fa-child"></i> Human Tissue Expression</h3>
            <div class="card-content">
                <?php if ($tissueData): ?>
                    <div class="tissue-grid">

                        <!-- ===== LEFT: SVG Human Body ===== -->
                        <div class="anatomy-section">
                            <h4 style="margin-top:0;color:#334155;font-size:15px;margin-bottom:12px;">
                                Expression Hotspots
                            </h4>

                            <!-- Filter Buttons -->
                            <div style="display:flex;gap:6px;flex-wrap:wrap; justify-content:center;margin-bottom:14px;">
                                <button onclick="filterByLevel('all')" 
                                        class="level-filter-btn" data-level="all"
                                        style="background:#1e40af;color:white;border-color:#1e40af;">
                                    All
                                </button>
                                <button onclick="filterByLevel('high')" 
                                        class="level-filter-btn" data-level="high"
                                        style="color:#ef4444;border-color:#ef4444;">
                                    High
                                </button>
                                <button onclick="filterByLevel('medium')" 
                                        class="level-filter-btn" data-level="medium"
                                        style="color:#f59e0b;border-color:#f59e0b;">
                                    Medium
                                </button>
                                <button onclick="filterByLevel('low')" 
                                        class="level-filter-btn" data-level="low"
                                        style="color:#3b82f6;border-color:#3b82f6;">
                                    Low
                                </button>
                            </div>

                            <!-- SVG Body Container -->
                            <div style="position:relative;width:100%;display:flex; justify-content:center;">
                                <div style="position:relative;width:220px;height:480px;">
                                    <svg viewBox="0 0 220 480" 
                                         xmlns="http://www.w3.org/2000/svg"
                                         style="width:220px;height:480px; position:absolute;top:0;left:0;">
                                        <defs>
                                            <radialGradient id="skinGrad" cx="50%" cy="30%" r="70%">
                                                <stop offset="0%"   style="stop-color:#f5d5c0;stop-opacity:1"/>
                                                <stop offset="100%" style="stop-color:#e8b89a;stop-opacity:1"/>
                                            </radialGradient>
                                            <filter id="bodyShadow" x="-20%" y="-20%" width="140%" height="140%">
                                                <feDropShadow dx="2" dy="3" stdDeviation="4" flood-color="#00000022"/>
                                            </filter>
                                        </defs>

                                        <ellipse cx="110" cy="38" rx="28" ry="34" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <ellipse cx="82"  cy="40" rx="5" ry="8" fill="#e8b89a" stroke="#c9956e" stroke-width="1"/>
                                        <ellipse cx="138" cy="40" rx="5" ry="8" fill="#e8b89a" stroke="#c9956e" stroke-width="1"/>
                                        <ellipse cx="110" cy="36" rx="4" ry="3" fill="white" stroke="#c9956e" stroke-width="0.5"/>
                                        <ellipse cx="120" cy="36" rx="4" ry="3" fill="white" stroke="#c9956e" stroke-width="0.5"/>
                                        <circle  cx="100" cy="36" r="1.6" fill="#3d2b1f"/>
                                        <circle  cx="120" cy="36" r="1.6" fill="#3d2b1f"/>
                                        <path d="M96 31 Q100 29 104 31" fill="none" stroke="#4a3728" stroke-width="1.2" stroke-linecap="round"/>
                                        <path d="M116 31 Q120 29 124 31" fill="none" stroke="#4a3728" stroke-width="1.2" stroke-linecap="round"/>
                                        <path d="M110 40 Q107 46 105 48 Q110 49.5 115 48 Q113 46 110 40" fill="#d4a082" stroke="none"/>
                                        <path d="M104 53 Q110 57 116 53" fill="none" stroke="#c9956e" stroke-width="1.3" stroke-linecap="round"/>
                                        <rect x="103" y="70" width="14" height="22" rx="4" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1"/>
                                        <path d="M70 92 Q64 88 61 96 L57 176 Q59 186 70 188 L150 188 Q161 186 163 176 L159 96 Q156 88 150 92 Q135 86 110 85 Q85 86 70 92Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <ellipse cx="96"  cy="118" rx="14" ry="10" fill="#e0a882" opacity="0.25"/>
                                        <ellipse cx="124" cy="118" rx="14" ry="10" fill="#e0a882" opacity="0.25"/>
                                        <path d="M103 91 Q90 94 74 91" fill="none" stroke="#cbd5e1" stroke-width="0.7" stroke-dasharray="2,2"/>
                                        <path d="M117 91 Q130 94 146 91" fill="none" stroke="#cbd5e1" stroke-width="0.7" stroke-dasharray="2,2"/>
                                        <ellipse cx="110" cy="176" rx="3" ry="2" fill="#c9956e" opacity="0.45"/>
                                        <path d="M70 188 Q57 192 54 206 Q59 221 75 223 L145 223 Q161 221 166 206 Q163 192 150 188Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2"/>
                                        <path d="M62 96 Q47 101 43 116 L41 159 Q43 169 54 169 L65 169 Q69 161 69 151 L70 101Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <path d="M41 159 Q39 177 40 196 L50 201 Q57 201 59 196 L61 171 Q56 166 54 159Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2"/>
                                        <ellipse cx="46" cy="207" rx="9" ry="11" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1"/>
                                        <ellipse cx="174" cy="207" rx="9" ry="11" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1"/>
                                        <path d="M158 96 Q173 101 177 116 L179 159 Q177 169 166 169 L155 169 Q151 161 151 151 L150 101Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <path d="M179 159 Q181 177 180 196 L170 201 Q163 201 161 196 L159 171 Q164 166 166 159Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2"/>
                                        <path d="M75 223 Q64 226 62 246 L61 306 Q63 316 76 317 L88 317 Q97 311 97 301 L96 236 Q90 226 82 223Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <path d="M61 306 Q59 331 59 362 L61 379 Q67 386 78 385 L85 385 Q91 380 91 371 L91 317 Q82 316 76 309Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2"/>
                                        <ellipse cx="74" cy="317" rx="10" ry="8" fill="#dba880" stroke="#c9956e" stroke-width="0.9"/>
                                        <path d="M59 379 Q54 387 54 394 L86 396 Q93 392 91 383 Q85 387 72 385Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1"/>
                                        <path d="M145 223 Q156 226 158 246 L159 306 Q157 316 144 317 L132 317 Q123 311 123 301 L124 236 Q130 226 138 223Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2" filter="url(#bodyShadow)"/>
                                        <path d="M159 306 Q161 331 161 362 L159 379 Q153 386 142 385 L135 385 Q129 380 129 371 L129 317 Q138 316 144 309Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1.2"/>
                                        <ellipse cx="146" cy="317" rx="10" ry="8" fill="#dba880" stroke="#c9956e" stroke-width="0.9"/>
                                        <path d="M161 379 Q166 387 166 394 L134 396 Q127 392 129 383 Q135 387 148 385Z" fill="url(#skinGrad)" stroke="#c9956e" stroke-width="1"/>
                                    </svg>

                                    <!-- Organ Markers Layer -->
                                    <div id="anatomyWrapper" style="position:absolute;top:0;left:0; width:220px;height:480px; pointer-events:none;"></div>
                                </div>
                            </div>

                            <!-- Gradient Legend -->
                            <div style="margin-top:18px;text-align:center;">
                                <div style="display:inline-flex;align-items:center;gap:8px; background:white;padding:7px 16px;border-radius:20px; border:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                                    <span>Low</span>
                                    <div style="width:80px;height:10px;border-radius:5px; background:linear-gradient(to right, #3b82f6,#10b981,#f59e0b,#ef4444);"></div>
                                    <span>High</span>
                                </div>

                                <!-- Selected Organ Info Panel -->
                                <div id="selectedOrganInfo" class="organ-info-panel">
                                    <strong id="selectedOrganName" style="font-size:13px;color:#1e293b;"></strong>
                                    <br>
                                    <span id="selectedOrganValue" style="color:#64748b;font-size:11px;"></span>
                                </div>
                            </div>
                        </div>
                        <!-- ===== END LEFT PANEL ===== -->

                        <!-- ===== RIGHT: CSS Bar Chart ===== -->
                        <div class="chart-section" id="cssBarChartContainer">
                            <h4 style="margin-top:0;margin-bottom:15px; color:#334155;font-size:15px;">
                                Detailed Tissue Levels
                            </h4>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-microscope"></i>
                        <p>No tissue expression data mapped in database for <strong><?php echo htmlspecialchars($symbol); ?></strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 3: TISSUE DISTRIBUTION ==================== -->
    <div class="page-section">
        <div class="card">
            <h3><i class="fas fa-cubes"></i> Interactive System-level Tissue Distribution</h3>
            <div class="card-content" style="background: #fafafa;">
                <?php if ($tissueData): ?>
                    <div class="tissue-grid-cols">
                        
                        <!-- LEFT SIDE -->
                        <div class="side-col left">
                            <?php foreach($organs as $id=>$o): if($o['side']!=='left') continue; ?>
                            <button class="organ-btn" data-id="<?=$id?>" style="--btn-color:<?=$o['color']?>" onclick="selectOrgan('<?=$id?>','<?=$o['color']?>')">
                                <div>
                                    <div class="btn-label"><?=$o['label']?></div>
                                    <div class="btn-sub"><?=count($o['tissues'])?> tissues</div>
                                </div>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- CENTER: SVG Body View -->
                        <div class="center-col">
                            <div class="instruction">
                                Select an organ system from either side panel to visualize quantitative expression levels (TPM) across tissues.
                            </div>

                            <div class="body-svg-wrap">
                                <div class="scan-line" id="scan-line"></div>
                                <svg viewBox="0 0 200 480" xmlns="http://www.w3.org/2000/svg" id="body-svg">
                                    <ellipse class="body-region" id="region-head"       cx="100" cy="38" rx="28" ry="34"/>
                                    <rect    class="body-region" id="region-neck"       x="88" y="70" width="24" height="22" rx="6"/>
                                    <path    class="body-region" id="region-torso-up"   d="M55,92 Q48,110 46,145 L154,145 Q152,110 145,92 Q128,84 100,82 Q72,84 55,92Z"/>
                                    <ellipse class="body-region" id="region-chest"      cx="100" cy="115" rx="30" ry="22"/>
                                    <path    class="body-region" id="region-abdomen"    d="M50,145 L46,195 Q46,210 100,212 Q154,210 154,195 L150,145Z"/>
                                    <path    class="body-region" id="region-pelvis"     d="M52,212 Q46,235 55,250 Q76,258 100,260 Q124,258 145,250 Q154,235 148,212Z"/>
                                    <path    class="body-region" id="region-arm-l"      d="M46,95 Q30,105 22,140 Q18,165 24,190 Q30,205 40,205 Q50,200 52,185 Q50,165 52,140 Q54,118 55,95Z"/>
                                    <path    class="body-region" id="region-arm-r"      d="M154,95 Q170,105 178,140 Q182,165 176,190 Q170,205 160,205 Q150,200 148,185 Q150,165 148,140 Q146,118 145,95Z"/>
                                    <path    class="body-region" id="region-forearm-l"  d="M24,193 Q18,220 20,250 Q22,265 32,268 Q42,268 44,255 Q46,240 40,210Z"/>
                                    <path    class="body-region" id="region-forearm-r"  d="M176,193 Q182,220 180,250 Q178,265 168,268 Q158,268 156,255 Q154,240 160,210Z"/>
                                    <ellipse class="body-region" id="region-hand-l"     cx="28" cy="278" rx="14" ry="16"/>
                                    <ellipse class="body-region" id="region-hand-r"     cx="172" cy="278" rx="14" ry="16"/>
                                    <path    class="body-region" id="region-thigh-l"    d="M55,258 Q46,270 44,310 Q44,335 52,345 Q64,350 72,345 Q80,338 80,310 Q80,278 76,258Z"/>
                                    <path    class="body-region" id="region-thigh-r"    d="M145,258 Q154,270 156,310 Q156,335 148,345 Q136,350 128,345 Q120,338 120,310 Q120,278 124,258Z"/>
                                    <path    class="body-region" id="region-shin-l"     d="M44,345 Q40,375 42,410 Q44,428 54,432 Q66,434 70,428 Q74,412 72,385 Q70,360 72,345Z"/>
                                    <path    class="body-region" id="region-shin-r"     d="M156,345 Q160,375 158,410 Q156,428 146,432 Q134,434 130,428 Q126,412 128,385 Q130,360 128,345Z"/>
                                    <path    class="body-region" id="region-foot-l"     d="M40,430 Q36,444 40,452 Q48,458 62,456 Q70,452 68,442 Q66,434 54,432Z"/>
                                    <path    class="body-region" id="region-foot-r"     d="M160,430 Q164,444 160,452 Q152,458 138,456 Q130,452 132,442 Q134,434 146,432Z"/>
                                    
                                    <!-- Organ overlays -->
                                    <ellipse class="body-region organ-overlay" id="overlay-brain"     cx="100" cy="32" rx="22" ry="26" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-heart"     cx="93"  cy="112" rx="12" ry="11" style="display:none"/>
                                    <path    class="body-region organ-overlay" id="overlay-lung-l"    d="M62,100 Q56,108 56,128 Q58,142 68,144 Q78,140 78,125 Q78,108 72,100Z" style="display:none"/>
                                    <path    class="body-region organ-overlay" id="overlay-lung-r"    d="M138,100 Q144,108 144,128 Q142,142 132,144 Q122,140 122,125 Q122,108 128,100Z" style="display:none"/>
                                    <path    class="body-region organ-overlay" id="overlay-liver"     d="M68,150 Q62,156 62,170 Q66,178 80,178 Q96,176 102,168 Q106,156 98,150Z" style="display:none"/>
                                    <path    class="body-region organ-overlay" id="overlay-digest"    d="M78,160 Q72,168 74,184 Q80,196 100,198 Q120,194 124,180 Q124,165 116,158 Q100,152 78,160Z" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-pancreas"  cx="96" cy="174" rx="20" ry="8" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-kidney-l"  cx="76" cy="180" rx="10" ry="14" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-kidney-r"  cx="124" cy="180" rx="10" ry="14" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-bladder"   cx="100" cy="222" rx="13" ry="11" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-thyroid"   cx="100" cy="80" rx="11" ry="7" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-adrenal-l" cx="76" cy="160" rx="6" ry="5" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-adrenal-r" cx="124" cy="160" rx="6" ry="5" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-uterus"    cx="100" cy="232" rx="14" ry="13" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-testes"    cx="100" cy="262" rx="13" ry="9" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-breast-l"  cx="82" cy="116" rx="12" ry="11" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-breast-r"  cx="118" cy="116" rx="12" ry="11" style="display:none"/>
                                    <ellipse class="body-region organ-overlay" id="overlay-salivary"  cx="100" cy="55" rx="9" ry="6" style="display:none"/>
                                    <rect    class="body-region organ-overlay" id="overlay-marrow-l"  x="56" y="276" width="16" height="64" rx="6" style="display:none"/>
                                    <rect    class="body-region organ-overlay" id="overlay-marrow-r"  x="128" y="276" width="16" height="64" rx="6" style="display:none"/>
                                </svg>
                            </div>

                            <!-- Quantitative Expression Card -->
                            <div class="expr-card" id="expr-card">
                                <h3 id="expr-title" style="color: var(--primary-color)">No system selected</h3>
                                <div class="bars-wrap" id="expr-bars">
                                    <span style="font-size:0.85rem;color:#64748b">Select an organ system from either panel.</span>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT SIDE -->
                        <div class="side-col right">
                            <?php foreach($organs as $id=>$o): if($o['side']!=='right') continue; ?>
                            <button class="organ-btn" data-id="<?=$id?>" style="--btn-color:<?=$o['color']?>" onclick="selectOrgan('<?=$id?>','<?=$o['color']?>')">
                                <div>
                                    <div class="btn-label"><?=$o['label']?></div>
                                    <div class="btn-sub"><?=count($o['tissues'])?> tissues</div>
                                </div>
                            </button>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-microscope"></i>
                        <p>No tissue expression data mapped in database for <strong><?php echo htmlspecialchars($symbol); ?></strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 4: DOMAINS ==================== -->
    <div class="page-section">
        <div class="card">
            <h3>
                <i class="fas fa-shapes"></i> Domain Architecture
            </h3>
            <div class="card-content">
                <?php if ($domainPlotExists): ?>
                    <div class="plot-container" style="height:500px; overflow-x: auto;">
                        <iframe src="<?php echo htmlspecialchars($domainPlotUrl); ?>" 
                                style="height:500px;width:1800px; border:none;" loading="lazy"></iframe>
                    </div>
                    
                    <div style="margin-top: 15px; text-align: center; display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <a href="https://datascience.imtech.res.in/anshu/circanet/protein_page_2.php?id=<?php echo urlencode($uniprotId); ?>" 
                           target="_blank" 
                           class="db-tag active" 
                           style="--tag-color: #1e40af; border-color: #1e40af; font-size: 14px; padding: 8px 20px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                            <i class="fas fa-cube"></i>
                            <span>View 3D Protein Structure for <?php echo htmlspecialchars($symbol); ?> (LLPs, IDRs, Variant)</span>
                        </a>

                        <a href="population.php?gene=<?php echo urlencode($symbol); ?>" 
                           target="_blank" 
                           class="db-tag active" 
                           style="--tag-color: #059669; border-color: #059669; font-size: 14px; padding: 8px 20px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                            <i class="fas fa-users"></i>
                            <span>Explore Population-wise Variant Distribution</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-puzzle-piece"></i>
                        <p>Domain Architecture not available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 5: PPI NETWORK ==================== -->
    <div class="page-section">
        <div class="card">
            <h3><i class="fas fa-project-diagram"></i> Interaction Network </h3>
            <div class="card-content">
                <?php if ($ppiJson != "[]"): ?>
                    
                    <!-- SPLIT GRID LAYOUT -->
                    <div class="ppi-split-grid">
                        
                        <!-- LEFT SIDE: Network Graph -->
                        <div class="ppi-graph-col">
                            <div class="ppi-legend">
                                <div class="ppi-legend-item">
                                    <span class="dot" style="background:#1e40af;width:16px;height:16px;"></span>
                                    <span>Input Gene</span>
                                </div>
                                <div class="ppi-legend-item">
                                    <span class="dot" style="background:#94a3b8;"></span>
                                    <span>Partner Gene</span>
                                </div>
                            </div>
                            <div id="cy"></div>
                            
                            <button type="button" id="reset-cy-btn" class="ppi-reset-btn">
                                <i class="fas fa-sync-alt"></i> Reset Graph View
                            </button>
                        </div>

                        <!-- RIGHT SIDE: Advanced Network Tools -->
                        <div class="ppi-tools-col" style="display: flex; flex-direction: column; padding: 15px; height: 100%; box-sizing: border-box;">
                            <h4 style="margin: 0 0 15px 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-tools" style="color: #64748b;"></i>
                                Advanced Network Tools
                            </h4>
                            
                            <div style="display: flex; flex-direction: column; gap: 12px; flex-grow: 1;">
                                <!-- Inter-Tissue Communication -->
                                <a href="https://datascience.imtech.res.in/anshu/circanet/multicens.php?keyword=<?php echo urlencode($symbol); ?>" 
                                   target="_blank" 
                                   style="flex: 1; margin: 0; padding: 15px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; justify-content: center; transition: all 0.2s ease;"
                                   onmouseenter="this.style.background='#f1f5f9'; this.style.borderColor='#94a3b8';"
                                   onmouseleave="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1';">
                                    <div style="font-weight: 700; font-size: 0.92rem; color: #1e40af; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-chart-line" style="font-size: 0.95rem;"></i> Inter-Tissue Communication
                                    </div>
                                    <div style="font-size: 0.78rem; color: #475569; line-height: 1.4; text-align: left;">
                                        Analyse inter-tissue communication by calculating multiple global centrality measures across tissue-specific co-expression networks. Identify top-ranked genes with high global centrality as candidate inter-tissue regulators.
                                    </div>
                                </a>

                                <!-- Transcription Factors -->
                                <a href="https://datascience.imtech.res.in/anshu/circanet/tf_new.php?keyword=<?php echo urlencode($symbol); ?>" 
                                   target="_blank" 
                                   style="flex: 1; margin: 0; padding: 15px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; justify-content: center; transition: all 0.2s ease;"
                                   onmouseenter="this.style.background='#f1f5f9'; this.style.borderColor='#94a3b8';"
                                   onmouseleave="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1';">
                                    <div style="font-weight: 700; font-size: 0.92rem; color: #1e40af; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-fingerprint" style="font-size: 0.95rem;"></i>  Transcription factors (TF) &amp; Transcription factor binding sites (TFBSs)
                                    </div>
                                    <div style="font-size: 0.78rem; color: #475569; line-height: 1.4; text-align: left;">
                                        Investigate the transcriptional regulation of circadian genes. Browse predicted transcription factor binding sites, visualize sequence logos, explore regulatory networks, and compare transcription factor profiles between genes.
                                    </div>
                                </a>

                                <!-- Interaction & Module -->
                                <a href="https://datascience.imtech.res.in/anshu/circanet/ppi_network.php?keyword=<?php echo urlencode($symbol); ?>" 
                                   target="_blank" 
                                   style="flex: 1; margin: 0; padding: 15px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; justify-content: center; transition: all 0.2s ease;"
                                   onmouseenter="this.style.background='#f1f5f9'; this.style.borderColor='#94a3b8';"
                                   onmouseleave="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1';">
                                    <div style="font-weight: 700; font-size: 0.92rem; color: #1e40af; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-network-wired" style="font-size: 0.95rem;"></i> Interaction &amp; Module
                                    </div>
                                    <div style="font-size: 0.78rem; color: #475569; line-height: 1.4; text-align: left;">
                                        Explore hierarchical protein communities derived from protein–protein interaction networks and compare functional modules across multiple organizational levels. Interactively visualize protein communities, compare functional modules, and examine the underlying protein interaction dataset.
                                    </div>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-network-wired"></i>
                        <p>No protein interaction data found for <strong><?php echo $symbol; ?></strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 6: DISEASES ==================== -->
    <div class="page-section">
        <div class="card">
            <h3><i class="fas fa-heart-pulse"></i> Disease Associations</h3>
            <div class="card-content">
                <?php if ($diseaseResult && mysqli_num_rows($diseaseResult) > 0): ?>
                    <div class="table-responsive">
                        <table id="assocDiseaseTable" class="styled-table" data-current-page="1">
                            <thead>
                                <tr>
                                    <th>S.No.</th>
                                    <th>Disease Name</th>
                                    <th>Ontology ID</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; 
                                while ($row = mysqli_fetch_assoc($diseaseResult)): ?>
                                <tr>
                                    <td class="serial"><?php echo $count++; ?></td>
                                    <td style="font-weight:500;color:#1e293b;">
                                        <?php echo displayValue($row['diseaseName']); ?>
                                    </td>
                                    <td>
                                        <?php if (displayValue($row['MONDO']) !== 'Null'): 
                                            $mondoVal = trim($row['MONDO']);
                                        ?>
                                            <a href="https://monarchinitiative.org/disease/<?php echo urlencode($mondoVal); ?>" 
                                               target="_blank" 
                                               style="color:var(--primary-color); text-decoration:underline; font-weight:500;">
                                                <?php echo htmlspecialchars($mondoVal); ?>
                                                <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:4px;"></i>
                                            </a>
                                        <?php else: ?>
                                            Null
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="background:#f1f5f9;padding:4px 8px; border-radius:4px;font-size:12px;">
                                            <?php echo displayValue($row['Source']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="assocDiseasePagination" class="pagination"></div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No disease associations found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 7: POST-TRANSLATIONAL MODIFICATIONS ==================== -->
    <div class="page-section">
        <div class="card">
            <h3>
                <i class="fas fa-fingerprint"></i> Post-Translational Modifications (PTM)
                <span style="margin-left:auto; font-size:12px; background:#e2e8f0; padding:4px 10px; border-radius:10px; font-weight:normal;">
                    Total: <?php echo $ptmResult ? mysqli_num_rows($ptmResult) : 0; ?>
                </span>
            </h3>
            <div class="card-content">
                <?php if ($ptmResult && mysqli_num_rows($ptmResult) > 0): ?>
                    <div class="table-responsive">
                        <table id="ptmTable" class="styled-table" data-current-page="1">
                            <thead>
                                <tr>
                                    <th>S.No.</th>
                                    <th>UniProt ID</th>
                                    <th>HGNC ID</th>
                                    <th>Modification (PTM)</th>
                                    <th>Site</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; 
                                while ($row = mysqli_fetch_assoc($ptmResult)): ?>
                                <tr>
                                    <td class="serial"><?php echo $count++; ?></td>
                                    <td>
                                        <a href="https://www.uniprot.org/uniprotkb/<?php echo urlencode($row['uniprot']); ?>" 
                                           target="_blank" 
                                           style="color:var(--primary-color); font-weight:600; text-decoration:none;">
                                            <?php echo displayValue($row['uniprot']); ?>
                                            <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:4px;"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        $ptmHgnc = isset($row['hgnc_id']) ? trim((string)$row['hgnc_id']) : '';
                                        if ($ptmHgnc !== ''):
                                            $ptmHgncUrl = ($ptmHgnc === 'HGNC:8847') 
                                                ? 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:5' 
                                                : 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/' . urlencode($ptmHgnc);
                                        ?>
                                            <a href="<?php echo $ptmHgncUrl; ?>" target="_blank" style="color:var(--primary-color); font-weight:600; text-decoration:none;">
                                                <?php echo htmlspecialchars($ptmHgnc); ?>
                                                <i class="fas fa-external-link-alt" style="font-size:10px; margin-left:4px;"></i>
                                            </a>
                                        <?php else: ?>
                                            Null
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="background:#fef3c7; color:#d97706; padding:4px 10px; border-radius:6px; font-size:12px; font-weight: 500; border: 1px solid #fde68a;">
                                            <?php echo displayValue($row['ptm']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo displayValue($row['site']); ?></strong></td>
                                    <td>
                                        <span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px;">
                                            <?php echo displayValue($row['source']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="ptmPagination" class="pagination"></div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-fingerprint" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No post-translational modifications recorded in the database for this gene.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>
</div>

<!-- ==========================================
     JAVASCRIPT
     ========================================== -->
<script>
// ---- AUTO-SUGGEST ----
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    const form = searchInput.closest('form');
    const suggestionBox = document.createElement('div');
    suggestionBox.className = 'suggestion-box';
    form.appendChild(suggestionBox);

    let debounceTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 1) { suggestionBox.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            fetch('gene.php?ajax_suggest=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => {
                    if (data.length > 0) {
                        suggestionBox.innerHTML = '';
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            const regex = new RegExp(`^(${query})`, 'gi');
                            div.innerHTML = item.replace(regex, '<strong>$1</strong>');
                            div.addEventListener('click', function () {
                                searchInput.value = item;
                                suggestionBox.style.display = 'none';
                                form.submit();
                            });
                            suggestionBox.appendChild(div);
                        });
                        suggestionBox.style.display = 'block';
                    } else {
                        suggestionBox.style.display = 'none';
                    }
                })
                .catch(e => console.error('Suggest error:', e));
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) suggestionBox.style.display = 'none';
    });
});
</script>

<script>
// ==========================================
// TISSUE MAP & BAR CHART
// ==========================================
const rawTissueData = <?php echo $tissueJson; ?>;

const organMapping = {
    'Brain':           {
        cols: ['Brain_Amygdala','Brain_Anterior_cingulate_cortex_BA24',
               'Brain_Caudate_basal_ganglia','Brain_Cerebellar_Hemisphere',
               'Brain_Cerebellum','Brain_Cortex','Brain_Frontal_Cortex_BA9',
               'Brain_Hippocampus','Brain_Hypothalamus',
               'Brain_Nucleus_accumbens_basal_ganglia',
               'Brain_Putamen_basal_ganglia',
               'Brain_Spinal_cord_cervical_c_1','Brain_Substantia_nigra'],
        x: 50, y: 7.5
    },
    'Pituitary':       { cols: ['Pituitary'],                                  x: 43,  y: 13   },
    'Thyroid':         { cols: ['Thyroid'],                                    x: 50,  y: 19   },
    'Salivary Gland':  { cols: ['Minor_Salivary_Gland'],                       x: 38,  y: 16   },
    'Lung':            { cols: ['Lung'],                                       x: 37,  y: 27   },
    'Heart':           { cols: ['Heart_Atrial_Appendage','Heart_Left_Ventricle'], x: 56, y: 27 },
    'Breast':          { cols: ['Breast_Mammary_Tissue'],                      x: 65,  y: 26   },
    'Post-Esophagus':  { cols: ['Esophagus_Gastroesophageal_Junction',
                                'Esophagus_Mucosa','Esophagus_Muscularis'],    x: 50,  y: 22   },
    'Liver':           { cols: ['Liver','Liver_Hepatocyte',
                                'Liver_Mixed_Cell','Liver_Portal_Tract'],      x: 40,  y: 34   },
    'Stomach':         { cols: ['Stomach','Stomach_Mixed_Cell',
                                'Stomach_Mucosa','Stomach_Muscularis'],        x: 56,  y: 35   },
    'Spleen':          { cols: ['Spleen'],                                     x: 65,  y: 34   },
    'Pancreas':        { cols: ['Pancreas','Pancreas_Acini',
                                'Pancreas_Islets','Pancreas_Mixed_Cell'],      x: 50,  y: 38   },
    'Kidney':          { cols: ['Kidney_Cortex','Kidney_Medulla'],             x: 38,  y: 40.5 },
    'Adrenal Gland':   { cols: ['Adrenal_Gland'],                             x: 63,  y: 40.5 },
    'Small Intestine': { cols: ['Small_Intestine_Terminal_Ileum',
                                'Small_Intestine_Terminal_Ileum_Lymphoid_Aggregate',
                                'Small_Intestine_Terminal_Ileum_Mixed_Cell'], x: 50,  y: 44   },
    'Colon':           { cols: ['Colon_Sigmoid','Colon_Transverse',
                                'Colon_Transverse_Mixed_Cell',
                                'Colon_Transverse_Mucosa',
                                'Colon_Transverse_Muscularis'],               x: 44,  y: 47   },
    'Bladder':         { cols: ['Bladder'],                                    x: 50,  y: 50   },
    'Prostate/Testis': { cols: ['Prostate','Testis'],                          x: 57,  y: 53   },
    'Ovary/Uterus':    { cols: ['Ovary','Uterus','Vagina',
                                'Cervix_Ectocervix','Cervix_Endocervix',
                                'Fallopian_Tube'],                             x: 50,  y: 52   },
    'Adipose':         { cols: ['Adipose_Subcutaneous',
                                'Adipose_Visceral_Omentum'],                  x: 28,  y: 38   },
    'Muscle':          { cols: ['Muscle_Skeletal'],                            x: 73,  y: 60   },
    'Artery':          { cols: ['Artery_Aorta','Artery_Coronary',
                                'Artery_Tibial'],                              x: 50,  y: 30   },
    'Nerve':           { cols: ['Nerve_Tibial'],                               x: 73,  y: 72   },
    'Skin':            { cols: ['Skin_Not_Sun_Exposed_Suprapubic',
                                'Skin_Sun_Exposed_Lower_leg'],                 x: 22,  y: 44   },
    'Blood':           { cols: ['Whole_Blood',
                                'Cells_EBV_transformed_lymphocytes'],          x: 24,  y: 26   },
    'Fibroblasts':     { cols: ['Cells_Cultured_fibroblasts'],                 x: 77,  y: 44   }
};

function getHeatColor(intensity) {
    let r, g, b;
    if (intensity < 0.33) {
        const t = intensity / 0.33;
        r = Math.round(59  + t * (16  - 59));
        g = Math.round(130 + t * (185 - 130));
        b = Math.round(246 + t * (129 - 246));
    } else if (intensity < 0.66) {
        const t = (intensity - 0.33) / 0.33;
        r = Math.round(16  + t * (245 - 16));
        g = Math.round(185 + t * (185 - 185));
        b = Math.round(129 + t * (11  - 129));
    } else {
        const t = (intensity - 0.66) / 0.34;
        r = Math.round(245 + t * (239 - 245));
        g = Math.round(158 + t * (68  - 158));
        b = Math.round(11  + t * (68  - 11));
    }
    return `rgb(${r},${g},${b})`;
}

function getLevel(intensity) {
    if (intensity >= 0.66) return 'high';
    if (intensity >= 0.33) return 'medium';
    return 'low';
}

let allMappedData  = [];
let allBarRows     = [];
let globalMaxMapVal = 1;
let currentFilter  = 'all';

function initializeTissueUI() {
    if (!rawTissueData) return;

    const ignoreKeys = ['id', 'HGNC_ID', 'Gene_ID'];

    const sortedEntries = Object.entries(rawTissueData)
        .filter(([k, v]) => !ignoreKeys.includes(k) && v !== null && v !== '')
        .map(([k, v]) => [k.replace(/_/g, ' '), parseFloat(v)])
        .filter(([, v]) => !isNaN(v))
        .sort((a, b) => b[1] - a[1]);

    const maxChartVal = sortedEntries.length > 0 ? sortedEntries[0][1] : 1;

    const chartContainer = document.getElementById('cssBarChartContainer');
    if (chartContainer && sortedEntries.length > 0) {
        const title = chartContainer.querySelector('h4');
        chartContainer.innerHTML = '';
        if (title) chartContainer.appendChild(title);

        sortedEntries.forEach(([labelName, val]) => {
            const intensity  = maxChartVal > 0 ? val / maxChartVal : 0;
            const percentage = intensity * 100;
            const color      = getHeatColor(intensity);
            const level      = getLevel(intensity);

            const row = document.createElement('div');
            row.className      = 'css-bar-row';
            row.dataset.level  = level;
            row.innerHTML = `
                <div class="css-bar-label" title="${labelName}">${labelName}</div>
                <div class="css-bar-track">
                    <div class="css-bar-fill"
                         style="width:0%;background-color:${color};"
                         data-target="${percentage}"></div>
                </div>
                <div class="css-bar-value">${val.toFixed(2)}</div>`;
            chartContainer.appendChild(row);
            allBarRows.push(row);
        });

        requestAnimationFrame(() => {
            setTimeout(() => {
                document.querySelectorAll('.css-bar-fill').forEach(fill => {
                    fill.style.width = fill.dataset.target + '%';
                });
            }, 120);
        });
    }

    let maxMapVal = 0;
    allMappedData = [];

    for (const [organ, info] of Object.entries(organMapping)) {
        let sum = 0, count = 0;
        info.cols.forEach(col => {
            const raw = rawTissueData[col];
            if (raw !== undefined && raw !== null && raw !== '') {
                const v = parseFloat(raw);
                if (!isNaN(v)) { sum += v; count++; }
            }
        });
        if (count > 0) {
            const avg = sum / count;
            if (avg > maxMapVal) maxMapVal = avg;
            allMappedData.push({ organ, x: info.x, y: info.y, value: avg });
        }
    }

    globalMaxMapVal = maxMapVal || 1;
    renderMarkers(allMappedData, globalMaxMapVal);
}

function renderMarkers(data, maxMapVal) {
    const wrapper = document.getElementById('anatomyWrapper');
    if (!wrapper) return;
    wrapper.innerHTML = '';

    data.forEach(item => {
        const intensity = maxMapVal > 0 ? item.value / maxMapVal : 0;

        if (currentFilter !== 'all' && getLevel(intensity) !== currentFilter) return;

        const color  = getHeatColor(intensity);
        const size   = Math.round(11 + intensity * 13); 

        const marker = document.createElement('div');
        marker.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background-color: ${color};
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.32);
            cursor: pointer;
            pointer-events: all;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            left: calc(${item.x}% - ${size / 2}px);
            top:  calc(${item.y}% - ${size / 2}px);
            z-index: 5;
        `;

        if (intensity >= 0.66) {
            marker.style.animation = 'tissueMarkerPulse 1.8s ease-in-out infinite';
        }

        marker.title = item.organ;

        marker.addEventListener('mouseenter', () => {
            marker.style.transform = 'scale(1.55)';
            marker.style.zIndex    = '20';
            showOrganInfo(item.organ, item.value, color);
        });
        marker.addEventListener('mouseleave', () => {
            marker.style.transform = 'scale(1)';
            marker.style.zIndex    = '5';
        });
        marker.addEventListener('click', () => {
            highlightBarRow(item.organ);
            showOrganInfo(item.organ, item.value, color);
        });

        wrapper.appendChild(marker);
    });
}

function showOrganInfo(organName, value, color) {
    const panel   = document.getElementById('selectedOrganInfo');
    const nameEl  = document.getElementById('selectedOrganName');
    const valueEl = document.getElementById('selectedOrganValue');
    if (!panel) return;
    panel.style.display    = 'block';
    panel.style.borderColor = color;
    nameEl.textContent  = organName;
    valueEl.textContent = 'Avg. Expression Value: ' + value.toFixed(4);
}

function highlightBarRow(organName) {
    const searchTerm = organName.toLowerCase().replace('post-', '').split('/')[0].trim();
    allBarRows.forEach(row => {
        const label = row.querySelector('.css-bar-label');
        if (!label) return;
        const isMatch = label.title.toLowerCase().includes(searchTerm);
        row.style.background    = isMatch ? '#eff6ff' : '';
        row.style.borderRadius  = isMatch ? '6px'     : '';
        row.style.outline       = isMatch ? '2px solid #bfdbfe' : '';
        if (isMatch) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

function filterByLevel(level) {
    currentFilter = level;

    const colorMap = { all: '#1e40af', high: '#ef4444', medium: '#f59e0b', low: '#3b82f6' };

    document.querySelectorAll('.level-filter-btn').forEach(btn => {
        const lvl = btn.dataset.level;
        if (lvl === level) {
            btn.style.background   = colorMap[lvl] || '#1e40af';
            btn.style.color        = 'white';
            btn.style.borderColor  = colorMap[lvl] || '#1e40af';
        } else {
            btn.style.background   = 'white';
            btn.style.color        = colorMap[lvl] || '#334155';
            btn.style.borderColor  = colorMap[lvl] || '#cbd5e1';
        }
    });

    allBarRows.forEach(row => {
        row.style.display = (level === 'all' || row.dataset.level === level) ? 'flex' : 'none';
        row.style.background = '';
        row.style.outline    = '';
    });

    renderMarkers(allMappedData, globalMaxMapVal);
}

document.addEventListener('DOMContentLoaded', initializeTissueUI);
</script>

<script>
// ==========================================
// PAGINATION & PPI NETWORK
// ==========================================
const ROWS_PER_PAGE = 8;

function createPageItem(text, page, tableId, changeFunc, isDisabled = false, isActive = false) {
    const dCls  = isDisabled ? 'disabled' : '';
    const aCls  = isActive   ? 'active'   : '';
    const click = isDisabled ? '' : `onclick="${changeFunc}('${tableId}',${page})"`;
    return `<div class="page-link ${dCls} ${aCls}" ${click}>${text}</div>`;
}

function generatePaginationLinks(currentPage, totalPages, tableId, changeFunc) {
    if (totalPages <= 1) return '';
    let html = '';
    html += createPageItem('<i class="fas fa-chevron-left"></i>',
                           currentPage - 1, tableId, changeFunc, currentPage === 1);

    const pages = new Set([1, totalPages]);
    for (let i = 0; i <= 1; i++) {
        pages.add(Math.max(1, currentPage - i));
        pages.add(Math.min(totalPages, currentPage + i));
    }
    let lastPage = 0;
    Array.from(pages).sort((a, b) => a - b).forEach(page => {
        if (lastPage !== 0 && page - lastPage > 1)
            html += `<div class="page-link disabled">...</div>`;
        html += createPageItem(page, page, tableId, changeFunc, false, page === currentPage);
        lastPage = page;
    });
    html += createPageItem('<i class="fas fa-chevron-right"></i>',
                           currentPage + 1, tableId, changeFunc, currentPage === totalPages);
    return html;
}

function updateSimpleTableView(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const paginationId = tableId.replace('Table', 'Pagination');
    const paginationControls = document.getElementById(paginationId);
    const visibleRows  = Array.from(table.tBodies[0].rows);
    let   currentPage  = parseInt(table.dataset.currentPage, 10);
    const totalPages   = Math.ceil(visibleRows.length / ROWS_PER_PAGE);

    currentPage = Math.max(1, Math.min(currentPage, totalPages || 1));
    table.dataset.currentPage = currentPage;

    visibleRows.forEach(row => (row.style.display = 'none'));
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    visibleRows.slice(start, start + ROWS_PER_PAGE).forEach((row, idx) => {
        row.style.display = 'table-row';
        const sc = row.querySelector('.serial');
        if (sc) sc.textContent = start + idx + 1;
    });

    if (paginationControls)
        paginationControls.innerHTML =
            generatePaginationLinks(currentPage, totalPages, tableId, 'changeSimplePage');
}

function changeSimplePage(tableId, newPage) {
    const table = document.getElementById(tableId);
    if (!table) return;
    table.dataset.currentPage = newPage;
    updateSimpleTableView(tableId);
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('assocDiseaseTable')) updateSimpleTableView('assocDiseaseTable');
    if (document.getElementById('ptmTable'))          updateSimpleTableView('ptmTable'); 

    const elementsData = <?php echo $ppiJson; ?>;

    if (elementsData.length > 0) {
        const cy = cytoscape({
            container: document.getElementById('cy'),
            elements: elementsData,
            style: [
                {
                    selector: 'node',
                    style: {
                        'background-color': '#94a3b8',
                        'label':            'data(label)',
                        'color':            '#334155',
                        'font-size':        '12px',
                        'text-valign':      'center',
                        'text-halign':      'center',
                        'width':            30,
                        'height':           30
                    }
                },
                {
                    selector: 'node[nodeType="main"]',
                    style: {
                        'background-color': '#1e40af',
                        'width':            50,
                        'height':           50,
                        'font-weight':      'bold',
                        'color':            '#fff',
                        'font-size':        '14px',
                        'border-width':     2,
                        'border-color':     '#fff'
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width':        'mapData(score, 0, 1, 1, 5)',
                        'line-color':   '#cbd5e1',
                        'curve-style':  'bezier'
                    }
                }
            ],
            layout: { name: 'concentric' }
        });

        setTimeout(() => {
            cy.resize();
            cy.layout({
                name: 'concentric', fit: true, padding: 30, minNodeSpacing: 50,
                concentric: node => node.data('nodeType') === 'main' ? 2 : 1,
                levelWidth: () => 1
            }).run();
        }, 100);

        const resetBtn = document.getElementById('reset-cy-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                cy.reset();
                cy.layout({
                    name: 'concentric', fit: true, padding: 30, minNodeSpacing: 50,
                    concentric: node => node.data('nodeType') === 'main' ? 2 : 1,
                    levelWidth: () => 1
                }).run();
            });
        }
    }
});

// ==========================================
// INTERACTIVE SYSTEM TISSUE MAP (HPA PORT)
// ==========================================
const organDef = <?php echo $tissueData ? json_encode($organs) : "{}"; ?>;
const activeGeneData = <?php echo $tissueJson ? $tissueJson : "null"; ?>;

const organMap = {
  adipose:     {overlays:[],                                                        regions:['region-thigh-l','region-thigh-r','region-abdomen','region-forearm-l','region-forearm-r']},
  heart:       {overlays:['overlay-heart'],                                         regions:['region-chest','region-torso-up','region-arm-l','region-arm-r']},
  endocrine:   {overlays:['overlay-thyroid','overlay-adrenal-l','overlay-adrenal-r'],regions:['region-neck','region-abdomen']},
  lung:        {overlays:['overlay-lung-l','overlay-lung-r'],                       regions:['region-torso-up','region-chest']},
  immune:      {overlays:['overlay-marrow-l','overlay-marrow-r'],                   regions:['region-thigh-l','region-thigh-r','region-arm-l','region-arm-r','region-shin-l','region-shin-r']},
  liver:       {overlays:['overlay-liver'],                                         regions:['region-abdomen']},
  male_repro:  {overlays:['overlay-testes'],                                        regions:['region-pelvis']},
  muscle:      {overlays:[],                                                        regions:['region-thigh-l','region-thigh-r','region-abdomen','region-forearm-l','region-forearm-r']},
  brain:       {overlays:['overlay-brain'],                                         regions:['region-head']},
  digest_up:   {overlays:['overlay-salivary'],                                      regions:['region-head','region-neck']},
  digest_lo:   {overlays:['overlay-digest'],                                        regions:['region-abdomen']},
  pancreas:    {overlays:['overlay-pancreas'],                                      regions:['region-abdomen']},
  kidney:      {overlays:['overlay-kidney-l','overlay-kidney-r','overlay-bladder'], regions:['region-abdomen','region-pelvis']},
  female_repro:{overlays:['overlay-uterus','overlay-breast-l','overlay-breast-r'],  regions:['region-pelvis','region-chest']},
  skin:        {overlays:[],                                                        regions:['region-head','region-torso-up','region-chest','region-abdomen','region-pelvis','region-arm-l','region-arm-r','region-forearm-l','region-forearm-r','region-hand-l','region-hand-r','region-thigh-l','region-thigh-r','region-shin-l','region-shin-r','region-foot-l','region-foot-r']},
};

let activeOrgan = null;

function clearAll() {
  document.querySelectorAll('.body-region').forEach(el => { 
    el.classList.remove('highlighted'); 
    el.style.removeProperty('--region-color'); 
  });
  document.querySelectorAll('.organ-overlay').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.organ-btn').forEach(b => b.classList.remove('active'));
  const scanLine = document.getElementById('scan-line');
  if (scanLine) scanLine.classList.remove('active');
}

function selectOrgan(id, color) {
  if (activeOrgan === id) { 
    clearAll(); 
    activeOrgan = null; 
    resetCard(); 
    return; 
  }
  clearAll();
  activeOrgan = id;
  const map = organMap[id];

  if (map) {
    map.regions.forEach(rid => {
      const el = document.getElementById(rid);
      if (el) { 
        el.style.setProperty('--region-color', color); 
        el.classList.add('highlighted'); 
      }
    });
    map.overlays.forEach(oid => {
      const el = document.getElementById(oid);
      if (el) { 
        el.style.display = ''; 
        el.style.setProperty('--region-color', color); 
        el.classList.add('highlighted'); 
      }
    });
  }

  const activeBtn = document.querySelector(`[data-id="${id}"]`);
  if (activeBtn) activeBtn.classList.add('active');
  
  const scanLine = document.getElementById('scan-line');
  if (scanLine) scanLine.classList.add('active');
  
  renderBars(id, color);
}

function renderBars(id, color) {
  const def   = organDef[id];
  const card  = document.getElementById('expr-card');
  const title = document.getElementById('expr-title');
  const bars  = document.getElementById('expr-bars');

  if (!card || !def) return;

  card.style.setProperty('--active-color', color);
  card.classList.add('lit');
  title.style.color = color;
  title.textContent = def.label;

  if (!activeGeneData) {
    bars.innerHTML = '<span style="font-size:0.8rem;color:#64748b;">No quantitative measurements mapped in tissue database.</span>';
    return;
  }

  const rows = def.tissues.map(t => {
    let val = 0;
    const dashKey = t.replace(/_/g, '-');
    if (activeGeneData[t] !== undefined && activeGeneData[t] !== null) {
      val = parseFloat(activeGeneData[t]);
    } else if (activeGeneData[dashKey] !== undefined && activeGeneData[dashKey] !== null) {
      val = parseFloat(activeGeneData[dashKey]);
    }
    return {
      name: t.replace(/_/g,' '),
      val:  isNaN(val) ? 0 : val
    };
  }).sort((a,b) => b.val - a.val);

  const maxVal = Math.max(...rows.map(r => r.val), 1);

  function barColor(v, mx) {
    const pct = v / mx;
    if (pct > 0.66) return color;
    if (pct > 0.33) return color + 'dd';
    return color + '77';
  }

  bars.innerHTML = rows.map(r => {
    const pct = (r.val / maxVal * 100).toFixed(1);
    const disp = r.val >= 1000 ? (r.val/1000).toFixed(1)+'k' : r.val.toFixed(2);
    return `<div class="bar-row">
      <div class="bar-name" title="${r.name}">${r.name}</div>
      <div class="bar-track"><div class="bar-fill" style="width:${pct}%;background:${barColor(r.val,maxVal)}"></div></div>
      <div class="bar-val">${disp}</div>
    </div>`;
  }).join('');
}

function resetCard() {
  const card = document.getElementById('expr-card');
  const title = document.getElementById('expr-title');
  const bars  = document.getElementById('expr-bars');
  if (!card) return;
  card.classList.remove('lit');
  title.style.color = 'var(--primary-color)';
  title.textContent = 'No system selected';
  bars.innerHTML = '<span style="font-size:0.85rem;color:#64748b">Select an organ system from either panel.</span>';
}
</script>

<?php
$conn->close();
include 'footer.php';
?>
