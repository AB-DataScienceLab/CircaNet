<?php
// =========================================================================
// Dual Database Connections:
// 1. MySQL ($conn) for gene_annotation, Disease_tb
// 2. PostgreSQL ($dbconn) for master_variants (rs, clnsig, evo2, am_class)
// =========================================================================
@include_once 'conn.php';
@require_once 'db_connect.php';

// Helper function: Sanitize user input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data ?? '')));
}

// Helper function: Truncate long sequence cells with [more]/[less] toggle
function format_truncated_cell($text, $length = 22) {
    $str = (string)($text ?? '');
    if ($str === '' || $str === 'N/A' || strtolower($str) === 'null') return '<span class="text-muted">N/A</span>';
    if (strlen($str) <= $length) return htmlspecialchars($str);
    
    $short = htmlspecialchars(substr($str, 0, $length));
    $full  = htmlspecialchars($str);
    return '<span class="cell-short">' . $short . '... <a href="javascript:void(0)" onclick="toggleCellText(this)" class="text-primary fw-semibold text-decoration-none">[more]</a></span>'
         . '<span class="cell-full d-none" style="word-break: break-all;">' . $full . ' <a href="javascript:void(0)" onclick="toggleCellText(this)" class="text-primary fw-semibold text-decoration-none">[less]</a></span>';
}

// -------------------------------------------------------------------------
// 1. FAST MULTI-CATEGORY AUTO-SUGGESTION ENDPOINT (< 2ms)
// -------------------------------------------------------------------------
if (isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=utf-8');
    $term = trim($_GET['suggest']);
    $suggestions = [];

    if (strlen($term) >= 2) {
        $like_mysql = '%' . $term . '%';
        $prefix_mysql = $term . '%';
        $numRs = preg_replace('/^rs/i', '', $term);
        $withRs = 'rs' . $numRs;

        // 1. Suggest Genes from MySQL (gene_annotation)
        if (isset($conn) && $conn instanceof mysqli) {
            $sql_g = "SELECT symbol, name, entrez_id FROM gene_annotation 
                      WHERE symbol LIKE ? OR entrez_id LIKE ? OR hgnc_id LIKE ? OR ensembl_gene_id LIKE ? 
                      ORDER BY symbol ASC LIMIT 5";
            if ($stmt_g = $conn->prepare($sql_g)) {
                $stmt_g->bind_param("ssss", $prefix_mysql, $prefix_mysql, $prefix_mysql, $prefix_mysql);
                $stmt_g->execute();
                $res_g = $stmt_g->get_result();
                while ($row = $res_g->fetch_assoc()) {
                    $suggestions[] = [
                        'type'  => 'Gene',
                        'label' => $row['symbol'],
                        'sub'   => $row['name'] ? substr($row['name'], 0, 38) . '...' : 'Gene Symbol',
                        'url'   => 'search.php?search=' . urlencode($row['symbol'])
                    ];
                }
                $stmt_g->close();
            }

            // 2. Suggest Diseases from MySQL (Disease_tb)
            $sql_d = "SELECT DISTINCT diseaseName, MONDO, Approved_symbol FROM Disease_tb 
                      WHERE diseaseName LIKE ? OR MONDO LIKE ? 
                      LIMIT 3";
            if ($stmt_d = $conn->prepare($sql_d)) {
                $stmt_d->bind_param("ss", $like_mysql, $like_mysql);
                $stmt_d->execute();
                $res_d = $stmt_d->get_result();
                while ($row = $res_d->fetch_assoc()) {
                    $suggestions[] = [
                        'type'  => 'Disease',
                        'label' => $row['diseaseName'],
                        'sub'   => ($row['MONDO'] ? $row['MONDO'] . ' ' : '') . '(Gene: ' . ($row['Approved_symbol'] ?? 'N/A') . ')',
                        'url'   => 'disease.php?keyword=' . urlencode($row['diseaseName'])
                    ];
                }
                $stmt_d->close();
            }

            // 3. Suggest Pathways (Reactome) from MySQL
            $sql_p = "SELECT symbol, reactome_id FROM gene_annotation 
                      WHERE reactome_id LIKE ? LIMIT 3";
            if ($stmt_p = $conn->prepare($sql_p)) {
                $stmt_p->bind_param("s", $like_mysql);
                $stmt_p->execute();
                $res_p = $stmt_p->get_result();
                while ($row = $res_p->fetch_assoc()) {
                    $suggestions[] = [
                        'type'  => 'Pathway',
                        'label' => 'Reactome Pathway',
                        'sub'   => 'Gene: ' . $row['symbol'],
                        'url'   => 'search.php?search=' . urlencode($term)
                    ];
                }
                $stmt_p->close();
            }
        }

        // 4. Suggest Variants from PostgreSQL (master_variants)
        if (isset($dbconn) && $dbconn) {
            @pg_query($dbconn, "SET statement_timeout = 2500");
            $sql_v = "SELECT DISTINCT rs, entrez_gene_symbol, chrom, pos FROM master_variants 
                      WHERE rs IN ($1, $2) OR rs LIKE $3 OR rs LIKE $4 LIMIT 4";
            $res_v = @pg_query_params($dbconn, $sql_v, [$numRs, $withRs, $numRs . '%', $withRs . '%']);
            if ($res_v) {
                while ($row = pg_fetch_assoc($res_v)) {
                    $rsVal = !preg_match('/^rs/i', $row['rs']) ? 'rs' . $row['rs'] : $row['rs'];
                    $suggestions[] = [
                        'type'  => 'Variant',
                        'label' => $rsVal,
                        'sub'   => 'Gene: ' . $row['entrez_gene_symbol'] . ' (' . $row['chrom'] . ':' . $row['pos'] . ')',
                        'url'   => 'search.php?search=' . urlencode($rsVal)
                    ];
                }
            }
        }
    }

    echo json_encode($suggestions);
    exit;
}

// -------------------------------------------------------------------------
// 2. QUERY PARSING & EXECUTIONS
// -------------------------------------------------------------------------
$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
if (empty($search_query) && isset($_POST['search'])) {
    $search_query = sanitize_input($_POST['search']);
}

// Default initial query
if (empty($search_query)) {
    $search_query = 'PER3';
}

$like_term = '%' . $search_query . '%';
$clean_rs = preg_replace('/^rs/i', '', $search_query);
$with_rs_lower = 'rs' . $clean_rs;
$with_rs_upper = 'RS' . $clean_rs;
$is_rs_query = preg_match('/^(rs)?\d+/i', $search_query);

// --- PostgreSQL Variants Query ---
$variant_results = [];
$matched_variant_genes = [];

if (isset($dbconn) && $dbconn) {
    @pg_query($dbconn, "SET statement_timeout = 5000");
    if ($is_rs_query) {
        $v_sql = "SELECT rs, entrez_gene_symbol, chrom, pos, ref, alt, 
                         clnsig, evo2_prediction, am_class, acmgclassification, consequence 
                  FROM master_variants 
                  WHERE rs IN ($1, $2, $3, $4) OR rs LIKE $5 OR rs LIKE $6
                  LIMIT 200";
        $v_res = @pg_query_params($dbconn, $v_sql, [
            $clean_rs, $with_rs_lower, $with_rs_upper, $search_query,
            $clean_rs . '%', $with_rs_lower . '%'
        ]);
    } else {
        $v_sql = "SELECT rs, entrez_gene_symbol, chrom, pos, ref, alt, 
                         clnsig, evo2_prediction, am_class, acmgclassification, consequence 
                  FROM master_variants 
                  WHERE entrez_gene_symbol = $1 OR entrez_gene_symbol = $2
                  LIMIT 200";
        $v_res = @pg_query_params($dbconn, $v_sql, [strtoupper($search_query), $search_query]);
    }

    if ($v_res) {
        while ($vrow = pg_fetch_assoc($v_res)) {
            $variant_results[] = $vrow;
            if (!empty($vrow['entrez_gene_symbol'])) {
                $matched_variant_genes[] = $vrow['entrez_gene_symbol'];
            }
        }
    }
}

// --- MySQL Gene Query ---
$genes_results = [];
if (isset($conn) && $conn instanceof mysqli) {
    $extra_gene_clause = "";
    if (!empty($matched_variant_genes)) {
        $unique_g = array_unique($matched_variant_genes);
        $escaped = array_map(function($g) use ($conn) { return "'" . $conn->real_escape_string($g) . "'"; }, $unique_g);
        $extra_gene_clause = " OR symbol IN (" . implode(',', $escaped) . ") ";
    }

    $g_sql = "SELECT symbol, entrez_id, hgnc_id, ensembl_gene_id, name, Chromosome, locus_type 
              FROM gene_annotation 
              WHERE symbol LIKE ? OR entrez_id = ? OR hgnc_id = ? OR ensembl_gene_id = ? OR name LIKE ? 
              $extra_gene_clause
              LIMIT 50";
              
    if ($stmt = $conn->prepare($g_sql)) {
        $prefix = $search_query . '%';
        $stmt->bind_param("sssss", $prefix, $search_query, $search_query, $search_query, $like_term);
        $stmt->execute();
        $genes_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// --- MySQL Disease Query ---
$disease_results = [];
if (isset($conn) && $conn instanceof mysqli) {
    $d_sql = "SELECT DISTINCT diseaseName, MONDO, Approved_symbol 
              FROM Disease_tb 
              WHERE diseaseName LIKE ? OR MONDO = ? OR Approved_symbol = ? 
              LIMIT 50";
    if ($stmt = $conn->prepare($d_sql)) {
        $stmt->bind_param("sss", $like_term, $search_query, $search_query);
        $stmt->execute();
        $disease_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// --- MySQL Pathway Query ---
$pathway_results = [];
if (isset($conn) && $conn instanceof mysqli) {
    $p_sql = "SELECT symbol, reactome_id, GO_ID, name 
              FROM gene_annotation 
              WHERE reactome_id LIKE ? OR GO_ID LIKE ? 
              LIMIT 50";
    if ($stmt = $conn->prepare($p_sql)) {
        $stmt->bind_param("ss", $like_term, $like_term);
        $stmt->execute();
        $pathway_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$total_found = count($genes_results) + count($disease_results) + count($pathway_results) + count($variant_results);

// Include Standard Header
if (file_exists('header.php')) {
    include 'header.php';
}
?>

<style>
    /* Search Hero Box */
    .search-hero-card { 
        background: linear-gradient(135deg, #1A365D 0%, #2A4B7C 100%); 
        padding: 30px; 
        border-radius: 12px; 
        color: #ffffff; 
        margin-bottom: 25px; 
        box-shadow: 0 4px 14px rgba(26, 54, 93, 0.15); 
    }
    .search-hero-card h2 { margin: 0 0 8px 0; font-size: 1.85rem; font-weight: 700; color: #ffffff; }
    .search-hero-card p { margin: 0 0 20px 0; font-size: 0.95rem; color: #e2e8f0; }

    .autocomplete-box { position: relative; width: 100%; }
    .search-input-custom { 
        width: 100%; 
        padding: 13px 22px; 
        border-radius: 50px; 
        border: 2px solid transparent; 
        font-size: 0.98rem; 
        box-sizing: border-box; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.12); 
        color: #1e293b;
    }
    .search-input-custom:focus { outline: none; border-color: #E89D6C; }
    
    .btn-search-main { 
        background: #E89D6C; 
        color: #1A365D; 
        border: none; 
        padding: 12px 28px; 
        border-radius: 50px; 
        cursor: pointer; 
        font-weight: 700; 
        font-size: 0.95rem;
        transition: all 0.2s ease; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
    }
    .btn-search-main:hover { background: #d07a44; color: #ffffff; }

    .suggestions-dropdown {
        position: absolute; top: 100%; left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1;
        border-top: none; z-index: 1050; max-height: 320px; overflow-y: auto; border-radius: 0 0 10px 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none; margin-top: 5px;
    }
    .suggestion-item { padding: 12px 18px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .suggestion-item:hover { background-color: #f8fafc; color: #1A365D; }

    /* Category Badges */
    .badge-type { font-size: 0.72rem; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; font-weight: 700; margin-right: 8px; }
    .badge-Gene { background: #e0f2fe; color: #0284c7; }
    .badge-Disease { background: #fee2e2; color: #dc2626; }
    .badge-Pathway { background: #dcfce7; color: #16a34a; }
    .badge-Variant { background: #ffedd5; color: #ea580c; }
    .badge-count { background: #1A365D; color: #fff; font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; margin-left: 8px; font-weight: 600; }

    /* Result Cards & Tables */
    .card-custom { border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 25px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.04); overflow: hidden; }
    .card-header-custom { background-color: #f8fafc; padding: 14px 20px; border-bottom: 2px solid #e2e8f0; font-weight: 700; font-size: 1.05rem; display: flex; justify-content: space-between; align-items: center; color: #1A365D; }
    .card-body-custom { padding: 20px; }

    .styled-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .styled-table th, .styled-table td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.88rem; vertical-align: middle; }
    .styled-table th { background-color: #f8fafc; font-weight: 600; color: #475569; }
    .styled-table tbody tr:hover { background-color: #f8fafc; }

    .btn-action-sm {
        display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px;
        border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600; transition: all 0.15s;
    }
    .btn-action-primary { background: #1A365D; color: #fff; }
    .btn-action-primary:hover { background: #2D4A6E; color: #fff; }
    .btn-action-danger { background: #dc2626; color: #fff; }
    .btn-action-danger:hover { background: #b91c1c; color: #fff; }
    .btn-action-success { background: #059669; color: #fff; }
    .btn-action-success:hover { background: #047857; color: #fff; }

    /* Pagination controls */
    .table-pagination-wrapper {
        display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;
        background: #f8fafc; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px;
    }
    .page-btn {
        border: 1px solid #cbd5e1; background: #ffffff; color: #334155; padding: 5px 10px;
        border-radius: 5px; font-size: 0.82rem; cursor: pointer; transition: all 0.15s ease-in-out;
    }
    .page-btn:hover:not(:disabled) { background: #e2e8f0; }
    .page-btn.active { background: #1A365D; border-color: #1A365D; color: #ffffff; font-weight: 600; }
    .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

<!-- --------------------------------------------------------------------- -->
<!-- 3. SEARCH HERO BAR                                                    -->
<!-- --------------------------------------------------------------------- -->
<div class="search-hero-card">
    <h2><i class="fas fa-search me-2"></i>Simple Search</h2>
    <p>Unified search across Gene Annotations, Diseases, Biological Pathways (Reactome/GO), and Genetic Variants.</p>

    <form method="GET" action="search.php" id="searchForm" class="d-flex gap-2 position-relative">
        <div class="autocomplete-box">
            <input type="text" id="mainSearchInput" name="search" autocomplete="off" class="search-input-custom"
                   placeholder="Enter Gene Symbol (e.g. PER3, CLOCK), Disease, Reactome Pathway ID, or RSID (e.g. rs1685338070)..." 
                   value="<?php echo htmlspecialchars($search_query); ?>" required>
            <div id="autocompleteDropdown" class="suggestions-dropdown"></div>
        </div>
        <button type="submit" class="btn-search-main">Search</button>
    </form>

    <div class="mt-3 text-light" style="font-size: 0.88rem;">
        <strong>Quick Examples:</strong> 
        <a href="search.php?search=PER3" class="text-white text-decoration-underline me-3">PER3</a>
        <a href="search.php?search=CLOCK" class="text-white text-decoration-underline me-3">CLOCK</a>
        <a href="search.php?search=Sleep" class="text-white text-decoration-underline me-3">Sleep Disorder</a>
        <a href="search.php?search=R-HSA-382551" class="text-white text-decoration-underline">Reactome (R-HSA-382551)</a>
    </div>
</div>

<div class="mb-4">
    <h3 class="h5 text-dark fw-bold mb-1">
        Search Results for <span style="color: #1A365D;">"<?php echo htmlspecialchars($search_query); ?>"</span>
    </h3>
    <p class="text-muted small mb-0">
        Found <strong><?php echo number_format($total_found); ?></strong> matching records across categories.
    </p>
</div>

<!-- --------------------------------------------------------------------- -->
<!-- SECTION 1: GENES                                                      -->
<!-- --------------------------------------------------------------------- -->
<?php if (!empty($genes_results)): ?>
    <div class="card-custom">
        <div class="card-header-custom">
            <div>
                <span class="badge-type badge-Gene">Genes</span>
                <span>Matching Gene Entities</span>
            </div>
            <span class="badge-count"><?php echo count($genes_results); ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Symbol</th>
                            <th>Gene Name</th>
                            <th>Location</th>
                            <th>Entrez ID</th>
                            <th>HGNC ID</th>
                            <th>Ensembl ID</th>
                            <th>Locus Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($genes_results as $g): ?>
                            <tr>
                                <td><strong style="color: #1A365D;"><?php echo htmlspecialchars($g['symbol']); ?></strong></td>
                                <td><?php echo htmlspecialchars($g['name'] ?? 'N/A'); ?></td>
                                <td>Chr <?php echo htmlspecialchars($g['Chromosome'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($g['entrez_id'])): ?>
                                        <a href="https://www.ncbi.nlm.nih.gov/gene/<?php echo urlencode($g['entrez_id']); ?>" target="_blank" class="text-decoration-none fw-medium" style="color: #1A365D;">
                                            <?php echo htmlspecialchars($g['entrez_id']); ?> <i class="fas fa-external-link-alt" style="font-size: 10px;"></i>
                                        </a>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($g['hgnc_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($g['ensembl_gene_id'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($g['locus_type'] ?? 'Gene'); ?></span></td>
                                <td>
                                    <a href="gene.php?keyword=<?php echo urlencode($g['symbol']); ?>" class="btn-action-sm btn-action-primary">
                                        <i class="fas fa-dna"></i> View Profile
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- --------------------------------------------------------------------- -->
<!-- SECTION 2: DISEASES                                                   -->
<!-- --------------------------------------------------------------------- -->
<?php if (!empty($disease_results)): ?>
    <div class="card-custom">
        <div class="card-header-custom">
            <div>
                <span class="badge-type badge-Disease">Diseases</span>
                <span>Associated Disease Phenotypes</span>
            </div>
            <span class="badge-count" style="background:#dc2626;"><?php echo count($disease_results); ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Disease Name</th>
                            <th>MONDO Ontology</th>
                            <th>Associated Gene</th>
                            <th>Disease Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disease_results as $d): ?>
                            <tr>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($d['diseaseName']); ?></strong></td>
                                <td>
                                    <?php if (!empty($d['MONDO'])): ?>
                                        <a href="https://monarchinitiative.org/disease/<?php echo urlencode($d['MONDO']); ?>" target="_blank" style="color: #1A365D;">
                                            <?php echo htmlspecialchars($d['MONDO']); ?> <i class="fas fa-external-link-alt" style="font-size: 10px;"></i>
                                        </a>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['Approved_symbol'])): ?>
                                        <a href="gene.php?keyword=<?php echo urlencode($d['Approved_symbol']); ?>" class="fw-semibold" style="color: #1A365D;">
                                            <?php echo htmlspecialchars($d['Approved_symbol']); ?>
                                        </a>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <a href="disease.php?keyword=<?php echo urlencode($d['diseaseName']); ?>" class="btn-action-sm btn-action-danger">
                                        <i class="fas fa-heartbeat"></i> View Disease Profile &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- --------------------------------------------------------------------- -->
<!-- SECTION 3: PATHWAYS & GO                                              -->
<!-- --------------------------------------------------------------------- -->
<?php if (!empty($pathway_results)): ?>
    <div class="card-custom">
        <div class="card-header-custom">
            <div>
                <span class="badge-type badge-Pathway">Pathways</span>
                <span>Biological Pathways &amp; Gene Ontology</span>
            </div>
            <span class="badge-count" style="background:#16a34a;"><?php echo count($pathway_results); ?></span>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Gene Symbol</th>
                            <th>Gene Name</th>
                            <th>Reactome Pathways</th>
                            <th>Gene Ontology (GO)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pathway_results as $p): ?>
                            <tr>
                                <td><strong style="color: #1A365D;"><?php echo htmlspecialchars($p['symbol']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $rList = array_filter(preg_split('/[\s,;|]+/', trim($p['reactome_id'] ?? '')));
                                    if (!empty($rList)):
                                        foreach ($rList as $rId): ?>
                                            <a href="https://reactome.org/PathwayBrowser/#/<?php echo urlencode($rId); ?>" target="_blank" class="badge bg-light text-success border text-decoration-none me-1">
                                                <i class="fas fa-project-diagram" style="font-size:9px;"></i> <?php echo htmlspecialchars($rId); ?>
                                            </a>
                                        <?php endforeach;
                                    else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $gList = array_filter(preg_split('/[\s,;|]+/', trim($p['GO_ID'] ?? '')));
                                    if (!empty($gList)):
                                        foreach ($gList as $goId): ?>
                                            <a href="https://www.ebi.ac.uk/QuickGO/GTerm?id=<?php echo urlencode($goId); ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none me-1">
                                                <i class="fas fa-tag" style="font-size:9px;"></i> <?php echo htmlspecialchars($goId); ?>
                                            </a>
                                        <?php endforeach;
                                    else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <a href="gene.php?keyword=<?php echo urlencode($p['symbol']); ?>" class="btn-action-sm btn-action-primary">
                                        View Gene &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- --------------------------------------------------------------------- -->
<!-- SECTION 4: GENETIC VARIANTS                                           -->
<!-- --------------------------------------------------------------------- -->
<?php if (!empty($variant_results)): ?>
    <div class="card-custom">
        <div class="card-header-custom">
            <div>
                <span class="badge-type badge-Variant">Variants</span>
                <span>Genetic Variants &amp; Pathogenicity Predictions</span>
            </div>
            <div>
                <span class="badge-count" style="background:#ea580c;"><?php echo count($variant_results); ?></span>
                <a href="advanced_search.php" class="btn-action-sm btn-action-success ms-2">
                    <i class="fas fa-sliders-h"></i> Query Builder
                </a>
            </div>
        </div>
        <div class="card-body-custom p-0">
            <div class="table-responsive">
                <table class="styled-table" id="variantsTable">
                    <thead>
                        <tr>
                            <th>RSID</th>
                            <th>Gene Symbol</th>
                            <th>Location</th>
                            <th>Ref &gt; Alt</th>
                            <th>ClinVar</th>
                            <th>EVO2</th>
                            <th>AlphaMissense</th>
                            <th>BIAS-ACMG</th>
                            <th>Consequence</th>
                        </tr>
                    </thead>
                    <tbody id="variantsTableBody">
                        <?php foreach ($variant_results as $v): 
                            $raw_rs = $v['rs'] ?? '';
                            $rs = !empty($raw_rs) ? (!preg_match('/^rs/i', $raw_rs) ? 'rs' . $raw_rs : $raw_rs) : 'N/A';
                            $clnsig_val = (!empty($v['clnsig']) && $v['clnsig'] !== 'null') ? htmlspecialchars($v['clnsig']) : 'N/A';
                            $evo2_val   = (!empty($v['evo2_prediction']) && $v['evo2_prediction'] !== 'null') ? htmlspecialchars($v['evo2_prediction']) : 'N/A';
                            $am_val     = (!empty($v['am_class']) && $v['am_class'] !== 'null') ? htmlspecialchars($v['am_class']) : 'N/A';
                            $acmg_val   = (!empty($v['acmgclassification']) && $v['acmgclassification'] !== 'null') ? htmlspecialchars($v['acmgclassification']) : 'N/A';
                            $conseq_val = (!empty($v['consequence']) && $v['consequence'] !== 'null') ? htmlspecialchars($v['consequence']) : 'N/A';
                        ?>
                            <tr class="variant-row">
                                <td>
                                    <?php if ($rs !== 'N/A'): ?>
                                        <a href="https://www.ncbi.nlm.nih.gov/snp/<?php echo urlencode($rs); ?>" target="_blank" class="fw-bold text-decoration-none" style="color: #1A365D;">
                                            <?php echo htmlspecialchars($rs); ?> <i class="fas fa-external-link-alt" style="font-size: 10px;"></i>
                                        </a>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <a href="gene.php?keyword=<?php echo urlencode($v['entrez_gene_symbol']); ?>" class="fw-semibold text-decoration-underline" style="color: #1A365D;">
                                        <?php echo htmlspecialchars($v['entrez_gene_symbol'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars(($v['chrom'] ?? '') . ':' . ($v['pos'] ?? '')); ?></td>
                                <td><?php echo format_truncated_cell(($v['ref'] ?? '') . ' > ' . ($v['alt'] ?? '')); ?></td>
                                <td><?php echo ($clnsig_val !== 'N/A') ? '<span class="fw-medium">' . $clnsig_val . '</span>' : '<span class="text-muted">N/A</span>'; ?></td>
                                <td>
                                    <?php if ($evo2_val !== 'N/A'): ?>
                                        <span class="badge bg-light text-primary border"><?php echo $evo2_val; ?></span>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td>
                                    <?php if ($am_val !== 'N/A'): ?>
                                        <span class="badge bg-light text-dark border"><?php echo $am_val; ?></span>
                                    <?php else: echo '<span class="text-muted">N/A</span>'; endif; ?>
                                </td>
                                <td><?php echo $acmg_val; ?></td>
                                <td><?php echo $conseq_val; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-pagination-wrapper">
            <div class="d-flex align-items-center gap-2 small text-muted">
                <span>Show</span>
                <select id="variantPageSize" class="form-select form-select-sm w-auto" onchange="changeVariantPageSize(this.value)">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span>entries</span>
                <span id="variantShowingInfo" class="ms-2 fw-medium text-secondary"></span>
            </div>
            <div id="variantPaginationBtns" class="d-flex gap-1"></div>
        </div>
    </div>
<?php endif; ?>

<!-- --------------------------------------------------------------------- -->
<!-- EMPTY STATE                                                           -->
<!-- --------------------------------------------------------------------- -->
<?php if ($total_found === 0): ?>
    <div class="alert alert-warning text-center py-4 my-4">
        <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
        <h5 class="alert-heading fw-bold">No Records Found</h5>
        <p class="mb-0 small">
            No matching results found for <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong> across Gene Annotations, Diseases, Pathways, or Master Variants.
        </p>
    </div>
<?php endif; ?>

<script>
// --- Variants Pagination ---
let currentVariantPage = 1;
let variantPageSize = 10;

function changeVariantPageSize(newSize) {
    variantPageSize = parseInt(newSize, 10);
    currentVariantPage = 1;
    renderVariantPagination();
}

function goToVariantPage(page) {
    currentVariantPage = page;
    renderVariantPagination();
}

function renderVariantPagination() {
    const tableBody = document.getElementById('variantsTableBody');
    if (!tableBody) return;

    const rows = Array.from(tableBody.querySelectorAll('tr.variant-row'));
    const totalRows = rows.length;
    if (totalRows === 0) return;

    const totalPages = Math.ceil(totalRows / variantPageSize) || 1;
    if (currentVariantPage > totalPages) currentVariantPage = totalPages;
    if (currentVariantPage < 1) currentVariantPage = 1;

    const startIdx = (currentVariantPage - 1) * variantPageSize;
    const endIdx = startIdx + variantPageSize;

    rows.forEach((row, index) => {
        row.style.display = (index >= startIdx && index < endIdx) ? '' : 'none';
    });

    const showingInfo = document.getElementById('variantShowingInfo');
    if (showingInfo) {
        showingInfo.textContent = `(Showing ${startIdx + 1}–${Math.min(endIdx, totalRows)} of ${totalRows})`;
    }

    const btnContainer = document.getElementById('variantPaginationBtns');
    if (!btnContainer) return;
    btnContainer.innerHTML = '';
    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '&laquo;';
    prevBtn.disabled = (currentVariantPage === 1);
    prevBtn.onclick = () => goToVariantPage(currentVariantPage - 1);
    btnContainer.appendChild(prevBtn);

    let startPage = Math.max(1, currentVariantPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    for (let p = startPage; p <= endPage; p++) {
        const pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.className = `page-btn ${p === currentVariantPage ? 'active' : ''}`;
        pageBtn.textContent = p;
        pageBtn.onclick = () => goToVariantPage(p);
        btnContainer.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = '&raquo;';
    nextBtn.disabled = (currentVariantPage === totalPages);
    nextBtn.onclick = () => goToVariantPage(currentVariantPage + 1);
    btnContainer.appendChild(nextBtn);
}

// --- Autocomplete & Text Toggles ---
document.addEventListener('DOMContentLoaded', function () {
    renderVariantPagination();

    const searchInput = document.getElementById('mainSearchInput');
    const dropdown = document.getElementById('autocompleteDropdown');
    let debounceTimer;

    if (searchInput && dropdown) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();
            if (query.length < 2) {
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`search.php?suggest=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data || data.length === 0) {
                            dropdown.innerHTML = '';
                            dropdown.style.display = 'none';
                            return;
                        }
                        dropdown.innerHTML = '';
                        data.forEach(item => {
                            const row = document.createElement('div');
                            row.className = 'suggestion-item';
                            row.innerHTML = `
                                <div>
                                    <span class="badge-type badge-${item.type}">${item.type}</span>
                                    <strong>${item.label}</strong>
                                </div>
                                <span class="small text-muted">${item.sub || ''}</span>
                            `;
                            row.addEventListener('click', function () {
                                window.location.href = item.url;
                            });
                            dropdown.appendChild(row);
                        });
                        dropdown.style.display = 'block';
                    })
                    .catch(err => console.error('Suggestion Error:', err));
            }, 150);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== searchInput && e.target !== dropdown) {
                dropdown.style.display = 'none';
            }
        });
    }
});

function toggleCellText(el) {
    const cell = el.closest('td');
    const shortSpan = cell.querySelector('.cell-short');
    const fullSpan = cell.querySelector('.cell-full');
    if (shortSpan && fullSpan) {
        shortSpan.classList.toggle('d-none');
        fullSpan.classList.toggle('d-none');
    }
}
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>