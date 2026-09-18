<?php
// 1. Include primary database connection (circanet_db)
require_once 'db_connect.php';

// Validate primary connection established in db_connect.php
if (!isset($dbconn) || !$dbconn) {
    die("Primary database connection failed. Please check db_connect.php connection parameters.");
}

// 2. Dynamically extract credentials from existing db_connect.php / $dbconn to connect to circanet_db_new
$new_db_name = 'circanet_db_new';

$pg_host = $host ?? $dbhost ?? $db_host ?? pg_host($dbconn);
$pg_port = $port ?? $dbport ?? $db_port ?? pg_port($dbconn);
$pg_user = $user ?? $username ?? $dbuser ?? $db_user ?? pg_parameter_status($dbconn, 'session_authorization');
$pg_pass = $password ?? $pass ?? $dbpass ?? $db_pass ?? '';

$conn_attempts = [];

// Attempt 1: Full connection string with host/port/user/password
$str1 = "dbname={$new_db_name}";
if (!empty($pg_host)) $str1 .= " host={$pg_host}";
if (!empty($pg_port)) $str1 .= " port={$pg_port}";
if (!empty($pg_user)) $str1 .= " user={$pg_user}";
if (!empty($pg_pass)) $str1 .= " password={$pg_pass}";
$conn_attempts[] = $str1;

// Attempt 2: Unix socket / local peer authentication
if (!empty($pg_user)) {
    $conn_attempts[] = "dbname={$new_db_name} user={$pg_user}";
}

// Attempt 3: Local default fallback
$conn_attempts[] = "dbname={$new_db_name}";

// Attempt 4: Replace dbname in any connection string variable defined in db_connect.php
foreach (['connection_string', 'conn_str', 'connString', 'db_conn_str'] as $v) {
    if (isset($$v) && is_string($$v)) {
        $conn_attempts[] = preg_replace('/dbname=\S+/', "dbname={$new_db_name}", $$v);
    }
}

$dbconn_new = false;
foreach ($conn_attempts as $conn_str) {
    $dbconn_new = @pg_connect($conn_str);
    if ($dbconn_new) {
        break;
    }
}

if (!$dbconn_new) {
    die("Secondary database connection to circanet_db_new failed. Please verify credentials.");
}

// Set maximum script execution and query timeouts
set_time_limit(300);
ini_set('max_execution_time', '300');
@pg_query($dbconn, "SET statement_timeout = 300000; SET work_mem = '64MB';");
@pg_query($dbconn_new, "SET statement_timeout = 300000; SET work_mem = '64MB';");

// Helper Function: Truncate long sequence cells (ALT, REF, HGVSp) with [more] / [less] toggle
function format_truncated_cell($text, $length = 25) {
    $str = (string)($text ?? '');
    if ($str === '' || $str === 'N/A') {
        return 'N/A';
    }
    if (strlen($str) <= $length) {
        return htmlspecialchars($str);
    }
    $short = htmlspecialchars(substr($str, 0, $length));
    $full  = htmlspecialchars($str);
    return '<span class="cell-short">' . $short . '... <a href="javascript:void(0)" onclick="toggleCellText(this)" class="text-primary fw-bold text-decoration-none">[more]</a></span>'
         . '<span class="cell-full d-none" style="word-break: break-all;">' . $full . ' <a href="javascript:void(0)" onclick="toggleCellText(this)" class="text-primary fw-bold text-decoration-none">[less]</a></span>';
}

// Map chromosome names to master_chr* table names
function get_partition_table_name($chrom) {
    $c = strtolower(trim((string)$chrom));
    $c = ltrim($c, 'chr');
    if ($c === 'm' || $c === 'mt') {
        return 'master_chrm';
    } elseif ($c === 'x') {
        return 'master_chrx';
    } elseif ($c === 'y') {
        return 'master_chry';
    } elseif (is_numeric($c) && (int)$c >= 1 && (int)$c <= 22) {
        return 'master_chr' . (int)$c;
    }
    return null;
}

// System Configuration & Clean Display Names
$field_display_names = [
    'entrez_gene_symbol' => 'Gene Symbol',
    'tissue'             => 'Tissue',
    'community_name'     => 'Community Name',
    'rs'                 => 'RSID',
    'chrom'              => 'Chromosome',
    'aa_lookup'          => 'Select Amino Acid',
    'clnsig'             => 'ClinVar Significance',
    'acmgclassification' => 'BIAS-ACMG',
    'evo2_prediction'    => 'EVO2 Prediction',
    'am_class'           => 'AlphaMissense Class'
];

// Pre-fetch all Gene Symbols from gene_lookup table in circanet_db
$all_gene_symbols = [];
$geneFetchSql = "SELECT entrez_gene_symbol FROM gene_lookup ORDER BY entrez_gene_symbol ASC";
$geneFetchRes = @pg_query($dbconn, $geneFetchSql);
if ($geneFetchRes) {
    while ($gRow = pg_fetch_assoc($geneFetchRes)) {
        if (!empty($gRow['entrez_gene_symbol'])) {
            $all_gene_symbols[] = $gRow['entrez_gene_symbol'];
        }
    }
}

// Pre-fetch all Tissues from rhythmic_tb in circanet_db
$all_tissues = [];
$tissueFetchSql = "SELECT DISTINCT tissue FROM rhythmic_tb WHERE tissue IS NOT NULL AND tissue != '' ORDER BY tissue ASC";
$tissueFetchRes = @pg_query($dbconn, $tissueFetchSql);
if ($tissueFetchRes) {
    while ($tRow = pg_fetch_assoc($tissueFetchRes)) {
        if (!empty($tRow['tissue'])) {
            $all_tissues[] = $tRow['tissue'];
        }
    }
}

// Pre-fetch all Community Names from rhythmic_tb in circanet_db
$all_community_names = [];
$commFetchSql = "SELECT DISTINCT community_name FROM rhythmic_tb WHERE community_name IS NOT NULL AND community_name != '' ORDER BY community_name ASC";
$commFetchRes = @pg_query($dbconn, $commFetchSql);
if ($commFetchRes) {
    while ($cRow = pg_fetch_assoc($commFetchRes)) {
        if (!empty($cRow['community_name'])) {
            $all_community_names[] = $cRow['community_name'];
        }
    }
}

$amino_acids = [
    'Ala' => 'Alanine (Ala)',   'Arg' => 'Arginine (Arg)',  'Asn' => 'Asparagine (Asn)',
    'Asp' => 'Aspartate (Asp)', 'Cys' => 'Cysteine (Cys)',  'Gln' => 'Glutamine (Gln)',
    'Glu' => 'Glu', 'Gly' => 'Glycine (Gly)',   'His' => 'Histidine (His)',
    'Ile' => 'Isoleucine (Ile)','Leu' => 'Leucine (Leu)',   'Lys' => 'Lysine (Lys)',
    'Met' => 'Methionine (Met)','Phe' => 'Phenylalanine (Phe)','Pro' => 'Proline (Pro)',
    'Ser' => 'Serine (Ser)',   'Thr' => 'Threonine (Thr)', 'Trp' => 'Tryptophan (Trp)',
    'Tyr' => 'Tyrosine (Tyr)', 'Val' => 'Valine (Val)'
];

$chromosomes_raw = array_merge(array_map('strval', range(1, 22)), ['X', 'Y', 'M']);

$am_class_options = [
    'likely_pathogenic' => 'Likely Pathogenic',
    'likely_benign'     => 'Likely Benign',
    'ambiguous'         => 'Ambiguous'
];

$clnsig_groups = [
    'Pathogenic/Likely Pathogenic' => [
        'Pathogenic', 'Pathogenic/Likely_pathogenic', 'Likely_pathogenic',
        'Likely_pathogenic/Likely_pathogenic,_low_penetrance', 'Likely_pathogenic,_low_penetrance',
        'Pathogenic/Likely_pathogenic,_low_penetrance', 'Pathogenic/Likely_pathogenic|other',
        'Pathogenic/Likely_pathogenic/Pathogenic,_low_penetrance', 'Pathogenic/Likely_pathogenic|risk_factor',
        'Pathogenic,_low_penetrance', 'Pathogenic/Pathogenic,_low_penetrance', 'Pathogenic|risk_factor'
    ],
    'Benign/Likely_Benign' => [
        'Benign', 'Benign|association', 'Benign/Likely_benign', 'Benign/Likely_benign|other',
        'Benign|other', 'Benign|protective', 'Benign|risk_factor', 'Likely_benign', 'Likely_benign|association'
    ],
    'VUS' => [
        'Uncertain_significance', 'Uncertain_risk_allele', 'Uncertain_significance/Uncertain_risk_allele',
        'VUS-high', 'Conflicting_classifications_of_pathogenicity',
        'Conflicting_classifications_of_pathogenicity|other', 'Conflicting_classifications_of_pathogenicity|risk_factor'
    ],
    'Other' => [
        'Affects', 'association', 'drug_response', 'Established_risk_allele', 'Likely_risk_allele',
        'no_classification_for_the_single_variant', 'no_classifications_from_unflagged_records',
        'not_provided', 'other', 'protective', 'protective|risk_factor', 'risk_factor'
    ]
];

$acmg_groups = [
    'Pathogenic/Likely Pathogenic' => ['pathogenic', 'likely pathogenic', 'Pathogenic', 'Likely pathogenic'],
    'Benign/Likely_Benign'         => ['benign', 'likely benign', 'Benign', 'Likely benign'],
    'VUS'                          => ['uncertain', 'vus', 'Uncertain', 'VUS']
];

$evo2_groups = [
    'Pathogenic/Likely Pathogenic' => ['Likely_Pathogenic', 'Pathogenic', 'likely_pathogenic', 'pathogenic'],
    'Benign/Likely_Benign'         => ['Likely_Benign', 'Benign', 'likely_benign', 'benign'],
    'VUS'                          => ['Unclassified', 'Uncertain', 'uncertain', 'vus', 'VUS']
];

$clnsig_options = [
    'Pathogenic/Likely Pathogenic' => 'Pathogenic / Likely Pathogenic',
    'Benign/Likely_Benign'         => 'Benign / Likely Benign',
    'VUS'                          => 'Uncertain Significance (VUS)',
    'Other'                        => 'Other / Conflicting / Risk Factor'
];

$acmg_options = [
    'Pathogenic/Likely Pathogenic' => 'Pathogenic / Likely Pathogenic',
    'Benign/Likely_Benign'         => 'Benign / Likely Benign',
    'VUS'                          => 'Uncertain Significance (VUS)'
];

$evo2_options = [
    'Pathogenic/Likely Pathogenic' => 'Pathogenic / Likely Pathogenic',
    'Benign/Likely_Benign'         => 'Benign / Likely Benign',
    'VUS'                          => 'Uncertain Significance (VUS)'
];

$optional_columns_def = [
    'fdr_q' => 'FDR Q-Value', 'r_squared' => 'R-Squared', 'relative_amplitude' => 'Relative Amplitude',
    'acrophase_rad' => 'Acrophase (Rad)', 'acrophase_hours' => 'Acrophase (Hours)',
    'af_joint_raw' => 'AF Joint Raw', 'af_joint_xx' => 'AF Joint XX', 'af_joint_xy' => 'AF Joint XY',
    'af_joint_afr' => 'AF AFR', 'af_joint_afr_xx' => 'AF AFR XX', 'af_joint_afr_xy' => 'AF AFR XY',
    'af_joint_ami' => 'AF AMI', 'af_joint_ami_xx' => 'AF AMI XX', 'af_joint_ami_xy' => 'AF AMI XY',
    'af_joint_amr' => 'AF AMR', 'af_joint_amr_xx' => 'AF AMR XX', 'af_joint_amr_xy' => 'AF AMR XY',
    'af_joint_asj' => 'AF ASJ', 'af_joint_asj_xx' => 'AF ASJ XX', 'af_joint_asj_xy' => 'AF ASJ XY',
    'af_joint_eas' => 'AF EAS', 'af_joint_eas_xx' => 'AF EAS XX', 'af_joint_eas_xy' => 'AF EAS XY',
    'af_joint_fin' => 'AF FIN', 'af_joint_fin_xx' => 'AF FIN XX', 'af_joint_fin_xy' => 'AF FIN XY',
    'af_joint_mid' => 'AF MID', 'af_joint_mid_xx' => 'AF MID XX', 'af_joint_mid_xy' => 'AF MID XY',
    'af_joint_nfe' => 'AF NFE', 'af_joint_nfe_xx' => 'AF NFE XX', 'af_joint_nfe_xy' => 'AF NFE XY',
    'af_joint_remaining' => 'AF Remaining', 'af_joint_remaining_xx' => 'AF Remaining XX', 'af_joint_remaining_xy' => 'AF Remaining XY',
    'af_joint_sas' => 'AF SAS', 'af_joint_sas_xx' => 'AF SAS XX', 'af_joint_sas_xy' => 'AF SAS XY',
    'af_joint' => 'AF Joint (Overall)', 'transcript' => 'Transcript', 'clnrevstat' => 'ClinVar Review Status',
    'evo2_score' => 'EVO2 Score', 'varianttype' => 'Variant Type'
];

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

// Query State Parameters
$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;
$offset = ($page - 1) * $limit;

$run_search = isset($_POST['submitted']) && $_POST['submitted'] == '1';
$is_download_request = isset($_POST['download_tsv']) && $_POST['download_tsv'] == '1';

$fieldnames = $_POST['fieldname'] ?? ['entrez_gene_symbol'];
$operators = $_POST['operator'] ?? ['ILIKE'];
$keywords = $_POST['keyword'] ?? [''];
$pos_keywords = $_POST['pos_keyword'] ?? [''];
$logicalOperators = $_POST['logical_operator'] ?? ['AND'];
$selected_opt_cols = $_POST['opt_cols'] ?? [];

$mv_base_cols = [
    'entrez_gene_symbol', 'entrez_gene', 'rs', 'chrom', 'pos',
    'ref', 'alt', 'hgvsp', 'clnsig', 'evo2_prediction',
    'am_class', 'acmgclassification', 'consequence'
];

$rhythmic_fields_list = ['tissue', 'module_id', 'community_name', 'module_annotation', 'fdr_q', 'r_squared', 'relative_amplitude', 'acrophase_rad', 'acrophase_hours'];
$opt_mv_cols = [];
foreach ($selected_opt_cols as $sOpt) {
    if (!in_array($sOpt, $rhythmic_fields_list) && isset($optional_columns_def[$sOpt])) {
        $opt_mv_cols[] = $sOpt;
    }
}

$target_chrom_tables = [];
$whereClauses = [];
$params = [];
$paramIndex = 1;
$summaryParts = [];
$tissue_filter_active = null;
$comm_filter_active = null;

if ($run_search) {
    for ($i = 0; $i < count($fieldnames); $i++) {
        $field = $fieldnames[$i] ?? '';
        $op = $operators[$i] ?? 'ILIKE';
        $kw = trim($keywords[$i] ?? '');
        $pos_kw = trim($pos_keywords[$i] ?? '');
        $logOp = $logicalOperators[$i] ?? 'AND';

        if (empty($kw) && $kw !== '0' && empty($pos_kw)) continue;

        $clause = "";
        $readableKw = htmlspecialchars($kw);

        if ($field === 'chrom') {
            $table_name = get_partition_table_name($kw);
            if ($table_name) {
                $target_chrom_tables[] = $table_name;
            }
            $chrWithPrefix = strpos(strtolower($kw), 'chr') === 0 ? $kw : 'chr' . $kw;
            $chrWithoutPrefix = ltrim(strtolower($kw), 'chr');

            $pIdx1 = '$' . $paramIndex++;
            $pIdx2 = '$' . $paramIndex++;
            $clause = "(mv.chrom = $pIdx1 OR mv.chrom = $pIdx2)";
            $params[] = $chrWithPrefix;
            $params[] = $chrWithoutPrefix;
            
            if (!empty($pos_kw) && is_numeric($pos_kw)) {
                $pPosIdx = '$' . $paramIndex++;
                $clause .= " AND mv.pos = $pPosIdx";
                $params[] = (int)$pos_kw;
                $readableKw .= " (Position: $pos_kw)";
            }
        } 
        elseif ($field === 'tissue') {
            $tissue_filter_active = $kw;
            $tOp = ($op === 'NOT ILIKE') ? 'NOT ILIKE' : 'ILIKE';
            $tSql = "SELECT DISTINCT gene_symbol FROM rhythmic_tb WHERE tissue $tOp $1 AND gene_symbol IS NOT NULL";
            $tRes = @pg_query_params($dbconn, $tSql, [$kw]);
            $matchedGenes = [];
            if ($tRes) {
                while ($tr = pg_fetch_assoc($tRes)) {
                    $matchedGenes[] = $tr['gene_symbol'];
                }
            }
            if (!empty($matchedGenes)) {
                $gPlaceholders = implode(',', array_map(function($idx) { return '$' . ($idx + 1); }, range(0, count($matchedGenes) - 1)));
                $cLookupSql = "SELECT DISTINCT chrom FROM gene_lookup WHERE entrez_gene_symbol IN ($gPlaceholders)";
                $cLookupRes = @pg_query_params($dbconn, $cLookupSql, $matchedGenes);
                if ($cLookupRes) {
                    while ($clRow = pg_fetch_assoc($cLookupRes)) {
                        $pTab = get_partition_table_name($clRow['chrom']);
                        if ($pTab && !in_array($pTab, $target_chrom_tables)) {
                            $target_chrom_tables[] = $pTab;
                        }
                    }
                }

                $geneInPlaceholders = [];
                foreach ($matchedGenes as $mGene) {
                    $pGIdx = '$' . $paramIndex++;
                    $geneInPlaceholders[] = $pGIdx;
                    $params[] = $mGene;
                }
                $clause = "mv.entrez_gene_symbol IN (" . implode(', ', $geneInPlaceholders) . ")";
            } else {
                $clause = "1=0";
            }
        }
        elseif ($field === 'community_name') {
            $comm_filter_active = $kw;
            $cOp = ($op === 'NOT ILIKE') ? 'NOT ILIKE' : 'ILIKE';
            $cSql = "SELECT DISTINCT gene_symbol FROM rhythmic_tb WHERE community_name $cOp $1 AND gene_symbol IS NOT NULL";
            $cRes = @pg_query_params($dbconn, $cSql, [$kw]);
            $matchedGenes = [];
            if ($cRes) {
                while ($cr = pg_fetch_assoc($cRes)) {
                    $matchedGenes[] = $cr['gene_symbol'];
                }
            }
            if (!empty($matchedGenes)) {
                $gPlaceholders = implode(',', array_map(function($idx) { return '$' . ($idx + 1); }, range(0, count($matchedGenes) - 1)));
                $cLookupSql = "SELECT DISTINCT chrom FROM gene_lookup WHERE entrez_gene_symbol IN ($gPlaceholders)";
                $cLookupRes = @pg_query_params($dbconn, $cLookupSql, $matchedGenes);
                if ($cLookupRes) {
                    while ($clRow = pg_fetch_assoc($cLookupRes)) {
                        $pTab = get_partition_table_name($clRow['chrom']);
                        if ($pTab && !in_array($pTab, $target_chrom_tables)) {
                            $target_chrom_tables[] = $pTab;
                        }
                    }
                }

                $commInPlaceholders = [];
                foreach ($matchedGenes as $mGene) {
                    $pGIdx = '$' . $paramIndex++;
                    $commInPlaceholders[] = $pGIdx;
                    $params[] = $mGene;
                }
                $clause = "mv.entrez_gene_symbol IN (" . implode(', ', $commInPlaceholders) . ")";
            } else {
                $clause = "1=0";
            }
        }
        elseif ($field === 'aa_lookup') {
            $pIdx1 = '$' . $paramIndex++;
            $pIdx2 = '$' . $paramIndex++;
            $clause = "(mv.hgvsp LIKE $pIdx1 OR mv.hgvsp LIKE $pIdx2)";
            $params[] = '%:' . $kw . '%';
            $params[] = '%' . $kw . '%';
        }
        elseif ($field === 'am_class') {
            $pIdx1 = '$' . $paramIndex++;
            $clause = "(mv.\"am_class\" = $pIdx1)";
            $params[] = $kw;
        }
        elseif ($field === 'clnsig') {
            if (isset($clnsig_groups[$kw])) {
                $mapped_values = $clnsig_groups[$kw];
                $inPlaceholders = [];
                foreach ($mapped_values as $mVal) {
                    $pInIdx = '$' . $paramIndex++;
                    $inPlaceholders[] = $pInIdx;
                    $params[] = $mVal;
                }
                $inOp = ($op === 'NOT ILIKE') ? 'NOT IN' : 'IN';
                $clause = "mv.clnsig $inOp (" . implode(', ', $inPlaceholders) . ")";
            }
        }
        elseif ($field === 'acmgclassification') {
            if (isset($acmg_groups[$kw])) {
                $mapped_values = $acmg_groups[$kw];
                $inPlaceholders = [];
                foreach ($mapped_values as $mVal) {
                    $pInIdx = '$' . $paramIndex++;
                    $inPlaceholders[] = $pInIdx;
                    $params[] = $mVal;
                }
                $inOp = ($op === 'NOT ILIKE') ? 'NOT IN' : 'IN';
                $clause = "mv.\"acmgclassification\" $inOp (" . implode(', ', $inPlaceholders) . ")";
            }
        }
        elseif ($field === 'evo2_prediction') {
            if (isset($evo2_groups[$kw])) {
                $mapped_values = $evo2_groups[$kw];
                $inPlaceholders = [];
                foreach ($mapped_values as $mVal) {
                    $pInIdx = '$' . $paramIndex++;
                    $inPlaceholders[] = $pInIdx;
                    $params[] = $mVal;
                }
                $inOp = ($op === 'NOT ILIKE') ? 'NOT IN' : 'IN';
                $clause = "mv.\"evo2_prediction\" $inOp (" . implode(', ', $inPlaceholders) . ")";
            }
        }
        elseif ($field === 'rs') {
            $numRs = preg_replace('/^rs/i', '', $kw);
            $withRs = 'rs' . $numRs;
            $upperRs = 'RS' . $numRs;
            $readableKw = 'rs' . $numRs;

            $pIdx1 = '$' . $paramIndex++;
            $pIdx2 = '$' . $paramIndex++;
            $pIdx3 = '$' . $paramIndex++;
            $pIdx4 = '$' . $paramIndex++;
            $clause = "(mv.rs = $pIdx1 OR mv.rs = $pIdx2 OR mv.rs = $pIdx3 OR mv.rs = $pIdx4)";
            $params[] = $kw;
            $params[] = $numRs;
            $params[] = $withRs;
            $params[] = $upperRs;
        }
        elseif ($field === 'entrez_gene_symbol') {
            $upperKw = strtoupper($kw);
            
            // 1. Exact chromosome lookup in gene_lookup to target ONLY matching chromosome tables
            $lookupSql = "SELECT DISTINCT chrom FROM gene_lookup WHERE entrez_gene_symbol = $1 LIMIT 5";
            $lookupRes = @pg_query_params($dbconn, $lookupSql, [$upperKw]);
            
            if ($lookupRes) {
                while ($lRow = pg_fetch_assoc($lookupRes)) {
                    if (!empty($lRow['chrom'])) {
                        $pTab = get_partition_table_name($lRow['chrom']);
                        if ($pTab && !in_array($pTab, $target_chrom_tables)) {
                            $target_chrom_tables[] = $pTab;
                        }
                    }
                }
            }

            // 2. Strict Equality forces immediate B-Tree index scan in <1ms
            if ($op === 'NOT ILIKE') {
                $pIdx = '$' . $paramIndex++;
                $clause = "mv.\"entrez_gene_symbol\" != $pIdx";
                $params[] = $upperKw;
            } else {
                $pIdx = '$' . $paramIndex++;
                $clause = "mv.\"entrez_gene_symbol\" = $pIdx";
                $params[] = $upperKw;
            }
        }

        if (!empty($clause)) {
            $whereClauses[] = [
                'clause' => "($clause)",
                'logical' => $logOp
            ];
            $readableField = "<strong>" . htmlspecialchars($field_display_names[$field] ?? $field) . "</strong>";
            $summaryPiece = ($i > 0) ? " " . $whereClauses[$i-1]['logical'] . " " : "";
            $summaryPiece .= "$readableField matches <em>\"$readableKw\"</em>";
            $summaryParts[] = $summaryPiece;
        }
    }
}

// Build Final SQL WHERE String
$fullWhere = "";
if (!empty($whereClauses)) {
    $fullWhere = "WHERE " . $whereClauses[0]['clause'];
    for ($k = 1; $k < count($whereClauses); $k++) {
        $connector = $whereClauses[$k - 1]['logical'];
        $fullWhere .= " " . $connector . " " . $whereClauses[$k]['clause'];
    }
}

// Select only relevant partitioned tables in circanet_db_new
$all_partition_tables = [];
for ($cNum = 1; $cNum <= 22; $cNum++) {
    $all_partition_tables[] = 'master_chr' . $cNum;
}
$all_partition_tables[] = 'master_chrx';
$all_partition_tables[] = 'master_chry';
$all_partition_tables[] = 'master_chrm';

$selected_tables = !empty($target_chrom_tables) ? array_unique($target_chrom_tables) : $all_partition_tables;

$mv_col_select = array_merge($mv_base_cols, $opt_mv_cols);
$quoted_cols = array_map(function($c) { return '"' . $c . '"'; }, $mv_col_select);
$select_col_str = implode(', ', $quoted_cols);

// Push WHERE clause inside each table's SELECT query for early index evaluation
$individual_queries = [];
foreach ($selected_tables as $tName) {
    $tabWhere = !empty($fullWhere) ? str_replace('mv.', '', $fullWhere) : '';
    $individual_queries[] = "SELECT $select_col_str FROM $tName $tabWhere";
}
$union_from_sql = "( " . implode(" UNION ALL ", $individual_queries) . " ) AS mv";

function enrich_variants_with_rhythmic($rows, $dbconn, $tissue_filter = null, $comm_filter = null) {
    if (empty($rows)) return [];

    $gene_symbols = array_unique(array_filter(array_column($rows, 'entrez_gene_symbol')));
    if (empty($gene_symbols)) return $rows;

    $placeholders = [];
    $params = [];
    $pIdx = 1;
    foreach ($gene_symbols as $gSym) {
        $placeholders[] = '$' . $pIdx++;
        $params[] = $gSym;
    }

    $rSql = "SELECT gene_symbol, tissue, module_id, community_name, module_annotation,
                    fdr_q, r_squared, relative_amplitude, acrophase_rad, acrophase_hours
             FROM rhythmic_tb WHERE gene_symbol IN (" . implode(',', $placeholders) . ")";
    
    if (!empty($tissue_filter)) {
        $pTIdx = '$' . $pIdx++;
        $rSql .= " AND tissue ILIKE $pTIdx";
        $params[] = $tissue_filter;
    }
    if (!empty($comm_filter)) {
        $pCIdx = '$' . $pIdx++;
        $rSql .= " AND community_name ILIKE $pCIdx";
        $params[] = $comm_filter;
    }

    $rRes = @pg_query_params($dbconn, $rSql, $params);
    $rhythmic_data = [];
    if ($rRes) {
        while ($rRow = pg_fetch_assoc($rRes)) {
            $sym = $rRow['gene_symbol'];
            if (!isset($rhythmic_data[$sym])) {
                $rhythmic_data[$sym] = $rRow;
            }
        }
    }

    foreach ($rows as &$vRow) {
        $sym = $vRow['entrez_gene_symbol'] ?? '';
        if (isset($rhythmic_data[$sym])) {
            $rItem = $rhythmic_data[$sym];
            $vRow['tissue'] = $rItem['tissue'] ?? '';
            $vRow['module_id'] = $rItem['module_id'] ?? '';
            $vRow['community_name'] = $rItem['community_name'] ?? '';
            $vRow['module_annotation'] = $rItem['module_annotation'] ?? '';
            $vRow['fdr_q'] = $rItem['fdr_q'] ?? '';
            $vRow['r_squared'] = $rItem['r_squared'] ?? '';
            $vRow['relative_amplitude'] = $rItem['relative_amplitude'] ?? '';
            $vRow['acrophase_rad'] = $rItem['acrophase_rad'] ?? '';
            $vRow['acrophase_hours'] = $rItem['acrophase_hours'] ?? '';
        } else {
            $vRow['tissue'] = 'N/A';
            $vRow['module_id'] = 'N/A';
            $vRow['community_name'] = 'N/A';
            $vRow['module_annotation'] = 'N/A';
            $vRow['fdr_q'] = 'N/A';
            $vRow['r_squared'] = 'N/A';
            $vRow['relative_amplitude'] = 'N/A';
            $vRow['acrophase_rad'] = 'N/A';
            $vRow['acrophase_hours'] = 'N/A';
        }
    }
    return $rows;
}

// --- EXECUTE TSV EXPORT (STREAMING OPTIMIZATION PREVENTS 504 TIMEOUT) ---
if ($is_download_request && $run_search && !empty($whereClauses)) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('X-Accel-Buffering: no');
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="Export_CircaNet_' . date('Y-m-d_His') . '.tsv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Connection: close');

    $out = fopen('php://output', 'w');

    $export_headers = [
        'Gene Symbol', 'Entrez ID', 'RSID', 'Chrom', 'Pos', 'Ref', 'Alt', 'HGVSp', 
        'ClinVar', 'EVO2 Prediction', 'AlphaMissense', 'BIAS-ACMG', 'Consequence',
        'Tissue', 'Module ID', 'Community Name', 'Module Annotation'
    ];
    foreach ($selected_opt_cols as $oCol) {
        if (isset($optional_columns_def[$oCol])) {
            $export_headers[] = $optional_columns_def[$oCol];
        }
    }
    fputcsv($out, $export_headers, "\t");
    fflush($out);
    flush();

    $exported_count = 0;
    $max_export_limit = 500000;

    foreach ($selected_tables as $tName) {
        if ($exported_count >= $max_export_limit) break;

        $tabWhere = !empty($fullWhere) ? str_replace('mv.', '', $fullWhere) : '';
        $tabSql = "SELECT $select_col_str FROM $tName $tabWhere";
        $tRes = @pg_query_params($dbconn_new, $tabSql, $params);

        if ($tRes) {
            $batch = [];
            while ($row = pg_fetch_assoc($tRes)) {
                $batch[] = $row;
                $exported_count++;

                if (count($batch) >= 500) {
                    $enrichedBatch = enrich_variants_with_rhythmic($batch, $dbconn, $tissue_filter_active, $comm_filter_active);
                    foreach ($enrichedBatch as $eRow) {
                        $ordered_row = [
                            $eRow['entrez_gene_symbol'] ?? '', $eRow['entrez_gene'] ?? '', $eRow['rs'] ?? '',
                            $eRow['chrom'] ?? '', $eRow['pos'] ?? '', $eRow['ref'] ?? '', $eRow['alt'] ?? '',
                            $eRow['hgvsp'] ?? '', $eRow['clnsig'] ?? '', $eRow['evo2_prediction'] ?? '',
                            $eRow['am_class'] ?? '', $eRow['acmgclassification'] ?? '', $eRow['consequence'] ?? '',
                            $eRow['tissue'] ?? '', $eRow['module_id'] ?? '', $eRow['community_name'] ?? '',
                            $eRow['module_annotation'] ?? ''
                        ];
                        foreach ($selected_opt_cols as $oCol) {
                            $ordered_row[] = $eRow[$oCol] ?? '';
                        }
                        fputcsv($out, $ordered_row, "\t");
                    }
                    $batch = [];
                    fflush($out);
                    flush();
                }

                if ($exported_count >= $max_export_limit) break;
            }

            if (!empty($batch)) {
                $enrichedBatch = enrich_variants_with_rhythmic($batch, $dbconn, $tissue_filter_active, $comm_filter_active);
                foreach ($enrichedBatch as $eRow) {
                    $ordered_row = [
                        $eRow['entrez_gene_symbol'] ?? '', $eRow['entrez_gene'] ?? '', $eRow['rs'] ?? '',
                        $eRow['chrom'] ?? '', $eRow['pos'] ?? '', $eRow['ref'] ?? '', $eRow['alt'] ?? '',
                        $eRow['hgvsp'] ?? '', $eRow['clnsig'] ?? '', $eRow['evo2_prediction'] ?? '',
                        $eRow['am_class'] ?? '', $eRow['acmgclassification'] ?? '', $eRow['consequence'] ?? '',
                        $eRow['tissue'] ?? '', $eRow['module_id'] ?? '', $eRow['community_name'] ?? '',
                        $eRow['module_annotation'] ?? ''
                    ];
                    foreach ($selected_opt_cols as $oCol) {
                        $ordered_row[] = $eRow[$oCol] ?? '';
                    }
                    fputcsv($out, $ordered_row, "\t");
                }
                fflush($out);
                flush();
            }
            pg_free_result($tRes);
        }
    }

    fclose($out);
    exit;
}

// Fast Pagination & Query Execution without slow COUNT bottlenecks
$paginated_rows = [];
$has_more_rows = false;
$query_error_message = '';

if ($run_search) {
    if (empty($whereClauses)) {
        $query_error_message = "Please enter or select at least one filter criterion before searching.";
    } else {
        $fetch_limit = $limit + 1;
        $dataSql = "SELECT * FROM $union_from_sql LIMIT $fetch_limit OFFSET $offset";
        $dRes = @pg_query_params($dbconn_new, $dataSql, $params);
        
        if ($dRes) {
            $raw_rows = [];
            while ($row = pg_fetch_assoc($dRes)) {
                $raw_rows[] = $row;
            }

            if (count($raw_rows) > $limit) {
                $has_more_rows = true;
                array_pop($raw_rows);
            }

            $paginated_rows = enrich_variants_with_rhythmic($raw_rows, $dbconn, $tissue_filter_active, $comm_filter_active);
        } else {
            $query_error_message = "Query Execution Error: " . pg_last_error($dbconn_new);
        }
    }
}

if (file_exists('header.php')) {
    include 'header.php';
}
?>

<style>
    :root {
        --primary-color: #10428d;
        --primary-hover: #0d3a7a;
        --accent-color: #2cab6c;
        --border-color: #dee2e6;
    }

    html { height: 100%; }

    body {
        display: flex !important;
        flex-direction: column !important;
        min-height: 100vh !important;
        margin: 0 !important;
    }

    main.container {
        flex: 1 0 auto !important;
        width: 100% !important;
        max-width: 1450px !important;
        padding-bottom: 20px;
    }

    footer.secondary-nav { margin-top: auto !important; }

    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] { -moz-appearance: textfield; }

    .query-builder-container { background-color: #fff; padding: 35px; border-radius: 12px; box-shadow: 0px 6px 18px rgba(0, 0, 0, 0.08); width: 100%; margin: 10px auto; }
    
    .condition { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; background: #fdfdfd; padding: 12px; border: 1px solid #eef2f5; border-radius: 8px; }
    .condition select, .condition input[type="text"], .condition input[type="number"] { padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 6px; color: #333; font-size: 0.95rem; }
    .condition > .field-select { flex: 0 0 200px; font-weight: 600; background-color: #fafbfc; }
    .condition > .op-select { flex: 0 0 150px; }
    
    .keyword-container { flex: 1 1 260px; }
    .keyword-container select { width: 100%; }

    .rs-input-group {
        display: flex;
        align-items: center;
        width: 100%;
    }
    .rs-input-addon {
        background-color: #e9ecef;
        border: 1px solid var(--border-color);
        border-right: none;
        padding: 9px 12px;
        border-top-left-radius: 6px;
        border-bottom-left-radius: 6px;
        font-weight: 700;
        color: #495057;
        font-size: 0.95rem;
        user-select: none;
    }
    .rs-input-group input.rs-input-field {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
        width: 100%;
    }

    .gene-group .select2-container { width: 100% !important; }
    .select2-container--bootstrap-5 .select2-selection { min-height: 42px; border-color: var(--border-color); border-radius: 6px; }

    .pos-dependent-inline { gap: 8px; align-items: center; background: #edf4fc; padding: 4px 10px; border-radius: 6px; border: 1px solid #d0e1f9; }

    .btn { background-color: var(--primary-color); color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s ease; }
    .btn:hover { background-color: var(--primary-hover); }
    .btn-add { background-color: var(--accent-color) !important; }
    .btn-reset { background-color: #6c757d !important; }
    .btn-download { background-color: #17a2b8 !important; }
    .btn-remove { background-color: #dc3545 !important; padding: 8px 14px; font-size: 1rem; line-height: 1; }

    .optional-cols-drawer { margin: 20px 0; border: 1px solid var(--border-color); border-radius: 8px; background: #fafafa; overflow: hidden; }
    .drawer-toggle { width: 100%; background: #edf2f7; padding: 12px 18px; text-align: left; border: none; font-weight: bold; color: var(--primary-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
    .drawer-content { display: none; padding: 18px; background: #fff; }
    .drawer-content.show { display: block; }
    
    .drawer-actions { display: flex; gap: 10px; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; }
    .btn-sm { padding: 6px 14px; font-size: 0.85rem; border-radius: 5px; }

    .drawer-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; max-height: 250px; overflow-y: auto; }
    .drawer-checkbox-grid label { font-size: 0.85rem; display: flex; align-items: center; gap: 6px; cursor: pointer; }

    .results-table-container { width: 100%; overflow-x: auto; margin-top: 15px; border-radius: 8px; border: 1px solid var(--border-color); }
    .results-table { width: 100%; border-collapse: separate; border-spacing: 0; background-color: #fff; font-size: 0.88rem; white-space: nowrap; }
    .results-table th, .results-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #eef2f5; border-right: 1px solid #eef2f5; }
    .results-table th { background-color: var(--primary-color); color: white; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; position: sticky; top: 0; z-index: 4; }

    .results-table th:nth-child(1), .results-table td:nth-child(1) { position: sticky; left: 0; z-index: 5; min-width: 65px; width: 65px; }
    .results-table th:nth-child(2), .results-table td:nth-child(2) { position: sticky; left: 65px; z-index: 5; min-width: 130px; width: 130px; }
    .results-table th:nth-child(3), .results-table td:nth-child(3) { position: sticky; left: 195px; z-index: 5; min-width: 100px; width: 100px; }
    .results-table th:nth-child(4), .results-table td:nth-child(4) { position: sticky; left: 295px; z-index: 5; min-width: 120px; width: 120px; box-shadow: 3px 0 6px -1px rgba(0,0,0,0.15); }

    .results-table th:nth-child(1), .results-table th:nth-child(2),
    .results-table th:nth-child(3), .results-table th:nth-child(4) { z-index: 10 !important; background-color: var(--primary-color) !important; color: #fff !important; }

    .results-table tbody tr:nth-child(odd) td:nth-child(1),
    .results-table tbody tr:nth-child(odd) td:nth-child(2),
    .results-table tbody tr:nth-child(odd) td:nth-child(3),
    .results-table tbody tr:nth-child(odd) td:nth-child(4) { background-color: #ffffff !important; }

    .results-table tbody tr:nth-child(even) td:nth-child(1),
    .results-table tbody tr:nth-child(even) td:nth-child(2),
    .results-table tbody tr:nth-child(even) td:nth-child(3),
    .results-table tbody tr:nth-child(even) td:nth-child(4) { background-color: #f8f9fa !important; }

    .results-table tbody tr:hover td:nth-child(1),
    .results-table tbody tr:hover td:nth-child(2),
    .results-table tbody tr:hover td:nth-child(3),
    .results-table tbody tr:hover td:nth-child(4) { background-color: #f4f8ff !important; }

    .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.92); display: none; justify-content: center; align-items: center; z-index: 10000; flex-direction: column; text-align: center; padding: 20px; }
    .loading-overlay.show { display: flex; }
    .spinner { width: 55px; height: 55px; border: 6px solid #e2e8f0; border-top: 6px solid var(--primary-color); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    .quote-box { margin-top: 25px; max-width: 650px; background: #ffffff; border-radius: 12px; padding: 18px 25px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); border-left: 5px solid var(--accent-color); }
    .quote-text { font-size: 1.05rem; font-style: italic; color: #2d3748; line-height: 1.6; min-height: 50px; }
    .quote-author { font-size: 0.85rem; font-weight: bold; color: var(--primary-color); margin-top: 8px; }

    .pagination-controls { display: flex; justify-content: space-between; align-items: center; margin-top: 18px; flex-wrap: wrap; gap: 12px; }
    .page-links a { padding: 6px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--primary-color); cursor: pointer; font-weight: 500; }
    .page-links a.active { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
    .page-links a.disabled { pointer-events: none; opacity: 0.5; }
    
    #top-scrollbar-container { width: 100%; overflow-x: auto; overflow-y: hidden; height: 16px; display: none; }
    #top-scrollbar-content { height: 1px; }
    .hidden { display: none !important; }
</style>

<!-- Circadian Quote Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="spinner"></div>
    <div style="margin-top: 18px; font-size: 1.15rem; font-weight: 700; color: var(--primary-color);">
        Aligning Circadian Rhythms & Querying Genomic Datasets...
    </div>
    
    <div class="quote-box">
        <div id="quoteText" class="quote-text">"Our internal clocks coordinate virtually all aspects of our behavior, physiology, and metabolism."</div>
        <div id="quoteAuthor" class="quote-author">â€” Nobel Prize Committee, Physiology or Medicine 2017</div>
    </div>
</div>

<div class="query-builder-container">
    <div class="text-center mb-4">
        <h1 style="font-family: 'Segoe UI', sans-serif; font-size: 40px; font-weight: 700; color: #212529; padding: 0px; margin-bottom: 5px;">
            Advanced Search Query Builder
        </h1>
        <p style="font-family: 'Segoe UI', sans-serif; font-size: 20px; font-weight: 400; color: #212529; line-height: 1.5; margin-bottom: 0;">
            Filter and lookup variants and rhythmic gene metrics across tissue profiles.
        </p>
    </div>
    
    <?php if (!empty($query_error_message)): ?>
        <div style="color: #721c24; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 15px;">
            <strong>Search Message:</strong> <?php echo htmlspecialchars($query_error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="searchForm">
        <input type="hidden" name="page" id="currentPage" value="<?php echo $page; ?>">
        <input type="hidden" name="limit" id="currentLimit" value="<?php echo $limit; ?>">
        <input type="hidden" name="submitted" value="1">
        <input type="hidden" name="download_tsv" id="downloadTrigger" value="0">

        <!-- Search Conditions Section -->
        <div id="conditions">
            <?php 
            $num_conditions = max(count($fieldnames), 1);
            for ($i = 0; $i < $num_conditions; $i++):
                $selectedField = $fieldnames[$i] ?? 'entrez_gene_symbol';
                $selectedOperator = $operators[$i] ?? 'ILIKE';
                $keywordValue = htmlspecialchars($keywords[$i] ?? '');
                $posValue = htmlspecialchars($pos_keywords[$i] ?? '');
                $logicalOperator = $logicalOperators[$i] ?? 'AND';

                $rsDisplayVal = preg_replace('/^rs/i', '', $keywordValue);
            ?>
            <div class="condition">
                <select name="fieldname[]" class="field-select" onchange="toggleInputType(this)">
                    <?php foreach($field_display_names as $fk => $flabel): ?>
                        <option value="<?php echo $fk; ?>" <?php if($selectedField == $fk) echo 'selected';?>><?php echo $flabel; ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="operator[]" class="op-select">
                    <option value="ILIKE" <?php if($selectedOperator == 'ILIKE') echo 'selected';?>>CONTAINS / EQUALS</option>
                    <option value="NOT ILIKE" <?php if($selectedOperator == 'NOT ILIKE') echo 'selected';?>>NOT CONTAINS</option>
                </select>

                <input type="hidden" name="keyword[]" class="keyword-hidden-input" value="<?php echo $keywordValue; ?>">

                <!-- Gene Symbol Dropdown -->
                <div class="keyword-container gene-group <?php if($selectedField !== 'entrez_gene_symbol') echo 'hidden'; ?>">
                    <select class="gene-select select2-gene" style="width: 100%;">
                        <option value="">-- Select Gene Symbol --</option>
                        <?php foreach ($all_gene_symbols as $gSym): ?>
                            <option value="<?php echo htmlspecialchars($gSym); ?>" <?php if($keywordValue == $gSym) echo 'selected'; ?>><?php echo htmlspecialchars($gSym); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tissue Dropdown Option -->
                <div class="keyword-container tissue-group <?php if($selectedField !== 'tissue') echo 'hidden'; ?>">
                    <select class="tissue-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select Tissue --</option>
                        <?php foreach ($all_tissues as $tis): ?>
                            <option value="<?php echo htmlspecialchars($tis); ?>" <?php if($keywordValue == $tis) echo 'selected'; ?>><?php echo htmlspecialchars($tis); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Community Name Dropdown Option -->
                <div class="keyword-container comm-group <?php if($selectedField !== 'community_name') echo 'hidden'; ?>">
                    <select class="comm-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select Community Name --</option>
                        <?php foreach ($all_community_names as $cName): ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php if($keywordValue == $cName) echo 'selected'; ?>><?php echo htmlspecialchars($cName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Fixed "rs" Prefix Input Group -->
                <div class="keyword-container rs-field-group <?php if($selectedField !== 'rs') echo 'hidden'; ?>">
                    <div class="rs-input-group">
                        <span class="rs-input-addon">rs</span>
                        <input type="text" class="rs-input-field text-input-field" value="<?php echo $selectedField === 'rs' ? $rsDisplayVal : ''; ?>" placeholder="e.g. 2124142756" autocomplete="off">
                    </div>
                </div>

                <!-- Chromosome Dropdown -->
                <div class="keyword-container chrom-group <?php if($selectedField !== 'chrom') echo 'hidden'; ?>">
                    <select class="chrom-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select Chromosome --</option>
                        <?php foreach ($chromosomes_raw as $chr): ?>
                            <option value="<?php echo $chr; ?>" <?php if(ltrim($keywordValue, 'chr') == $chr) echo 'selected'; ?>>Chr <?php echo $chr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dependent Inline Position Input -->
                <div class="pos-dependent-inline" style="display: <?php echo ($selectedField === 'chrom') ? 'flex' : 'none'; ?>;">
                    <label style="font-size: 0.85rem; font-weight: bold; margin: 0;">Position:</label>
                    <input type="number" name="pos_keyword[]" class="pos-input-field" value="<?php echo $posValue; ?>" placeholder="e.g. 94187889" style="width: 120px;">
                </div>

                <!-- Amino Acid Dropdown -->
                <div class="keyword-container aa-group <?php if($selectedField !== 'aa_lookup') echo 'hidden'; ?>">
                    <select class="aa-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select Amino Acid --</option>
                        <?php foreach ($amino_acids as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php if($keywordValue == $code) echo 'selected'; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- AlphaMissense Dropdown -->
                <div class="keyword-container amclass-group <?php if($selectedField !== 'am_class') echo 'hidden'; ?>">
                    <select class="amclass-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select AlphaMissense Class --</option>
                        <?php foreach ($am_class_options as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php if($keywordValue == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ClinVar Dropdown -->
                <div class="keyword-container clnsig-group <?php if($selectedField !== 'clnsig') echo 'hidden'; ?>">
                    <select class="clnsig-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select ClinVar Significance --</option>
                        <?php foreach ($clnsig_options as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php if($keywordValue == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- BIAS-ACMG Dropdown -->
                <div class="keyword-container acmg-group <?php if($selectedField !== 'acmgclassification') echo 'hidden'; ?>">
                    <select class="acmg-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select BIAS-ACMG --</option>
                        <?php foreach ($acmg_options as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php if($keywordValue == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- EVO2 Dropdown -->
                <div class="keyword-container evo2-group <?php if($selectedField !== 'evo2_prediction') echo 'hidden'; ?>">
                    <select class="evo2-select" onchange="syncRowKeyword(this.closest('.condition'))">
                        <option value="">-- Select EVO2 Prediction --</option>
                        <?php foreach ($evo2_options as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php if($keywordValue == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Logical Operator Dropdown -->
                <select name="logical_operator[]" class="log-select">
                    <option value="AND" <?php if($logicalOperator == 'AND') echo 'selected';?>>AND</option>
                    <option value="OR" <?php if($logicalOperator == 'OR') echo 'selected';?>>OR</option>
                </select>

                <button type="button" class="btn btn-remove" onclick="removeCondition(this)" title="Remove Condition">&times;</button>
            </div>
            <?php endfor; ?>
        </div>

        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-add" onclick="addCondition()">+ Add Field Filter</button>
            <button type="submit" class="btn" id="searchButton">Run Query</button>
            <button type="button" class="btn btn-reset" onclick="window.location.href=window.location.pathname;">Reset</button>
            <button type="button" class="btn btn-download" id="downloadBtn">Export Results (TSV)</button>
        </div>

        <!-- Optional Columns Selection Drawer -->
        <div class="optional-cols-drawer">
            <button type="button" class="drawer-toggle" onclick="toggleDrawer()">
                <span><i class="fas fa-cog me-1"></i> Optional / Additional Display Columns Toggle</span>
                <span id="drawerArrow">&#9660;</span>
            </button>
            <div class="drawer-content" id="drawerContent">
                <div class="drawer-actions">
                    <button type="button" class="btn btn-sm" onclick="selectAllOptCols()">Select All</button>
                    <button type="button" class="btn btn-sm btn-reset" onclick="deselectAllOptCols()">Deselect All</button>
                    <button type="button" class="btn btn-sm btn-add" onclick="submitOptCols()">Apply Column Selection</button>
                </div>

                <div class="drawer-checkbox-grid">
                    <?php foreach ($optional_columns_def as $col_key => $col_label): ?>
                        <label>
                            <input type="checkbox" name="opt_cols[]" value="<?php echo $col_key; ?>" <?php if(in_array($col_key, $selected_opt_cols)) echo 'checked'; ?>>
                            <?php echo $col_label; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Results Table Block -->
    <?php if ($run_search && empty($query_error_message)): ?>
        <div style="margin-top: 25px;">
            <?php if (!empty($summaryParts)): ?>
                <div style="padding: 10px 15px; background: #edf4fc; border: 1px solid #d0e1f9; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem;">
                    <strong>Active Search Filters:</strong> <?php echo implode('', $summaryParts); ?>
                </div>
            <?php endif; ?>

            <div id="top-scrollbar-container">
                <div id="top-scrollbar-content"></div>
            </div>

            <div class="results-table-container" id="bottomTableContainer">
                <table class="results-table" id="mainResultsTable">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Gene Symbol</th>
                            <th>Entrez ID</th>
                            <th>RSID</th>
                            <th>Chrom</th>
                            <th>Pos</th>
                            <th>Ref</th>
                            <th>Alt</th>
                            <th>HGVSp</th>
                            <th>ClinVar</th>
                            <th>EVO2 Prediction</th>
                            <th>AlphaMissense</th>
                            <th>BIAS-ACMG</th>
                            <th>Consequence</th>
                            <th>Tissue</th>
                            <th>Module ID</th>
                            <th>Community Name</th>
                            <th>Module Annotation</th>

                            <?php foreach ($selected_opt_cols as $optCol): if(isset($optional_columns_def[$optCol])): ?>
                                <th style="background-color: #0d3a7a;"><?php echo $optional_columns_def[$optCol]; ?></th>
                            <?php endif; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    if (!empty($paginated_rows)):
                        $sr = $offset + 1;
                        foreach ($paginated_rows as $row): 
                            $displayRs = $row['rs'] ?? '';
                            if (!empty($displayRs) && !preg_match('/^rs/i', $displayRs)) {
                                $displayRs = 'rs' . $displayRs;
                            }
                    ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['entrez_gene_symbol'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['entrez_gene'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if(!empty($displayRs)): ?>
                                    <a href="https://www.ncbi.nlm.nih.gov/snp/<?php echo urlencode($displayRs); ?>" target="_blank" style="color: var(--primary-color); font-weight: bold;"><?php echo htmlspecialchars($displayRs); ?></a>
                                <?php else: echo 'N/A'; endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['chrom'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['pos'] ?? ''); ?></td>
                            <td><?php echo format_truncated_cell($row['ref'] ?? ''); ?></td>
                            <td><?php echo format_truncated_cell($row['alt'] ?? ''); ?></td>
                            <td><?php echo format_truncated_cell($row['hgvsp'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['clnsig'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['evo2_prediction'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['am_class'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['acmgclassification'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['consequence'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['tissue'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['module_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['community_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['module_annotation'] ?? 'N/A'); ?></td>

                            <?php foreach ($selected_opt_cols as $optCol): if(isset($optional_columns_def[$optCol])): ?>
                                <td><?php echo format_truncated_cell($row[$optCol] ?? 'N/A'); ?></td>
                            <?php endif; endforeach; ?>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="35" style="text-align: center; padding: 30px; background-color: #fff9e6; color: #856404; font-size: 0.95rem;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No records matched your exact search combination.</strong><br>
                                Please try using another combination or broadening your search parameters.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-controls">
                <div>
                    <label>Rows per page: </label>
                    <select onchange="changeLimit(this.value)" style="padding: 5px; border-radius: 4px;">
                        <option value="10" <?php if($limit == 10) echo 'selected'; ?>>10</option>
                        <option value="20" <?php if($limit == 20) echo 'selected'; ?>>20</option>
                        <option value="50" <?php if($limit == 50) echo 'selected'; ?>>50</option>
                        <option value="100" <?php if($limit == 100) echo 'selected'; ?>>100</option>
                    </select>
                </div>
                <div class="page-links">
                    <a onclick="changePage(<?php echo $page - 1; ?>)" class="<?php if($page <= 1) echo 'disabled'; ?>">Prev</a>
                    <span>Page <?php echo $page; ?></span>
                    <a onclick="changePage(<?php echo $page + 1; ?>)" class="<?php if(!$has_more_rows) echo 'disabled'; ?>">Next</a>
                </div>
                <div>
                    <?php echo "Showing " . count($paginated_rows) . " matching records on page " . $page; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const circadianQuotes = [
    { text: "Our internal clocks coordinate virtually all aspects of our behavior, physiology, and metabolism.", author: "Nobel Prize Committee, Physiology or Medicine 2017" },
    { text: "Life is an orchestration of biological rhythms â€” every cell ticking in harmony with the cosmos.", author: "Chronobiology Axiom" },
    { text: "In every physiological mechanism, time is not merely a parameter, but a fundamental biological dimension.", author: "Franz Halberg, Pioneer of Chronobiology" },
    { text: "The circadian clock synchronizes internal biochemical processes with the 24-hour cycle of light and darkness.", author: "Michael W. Young, Nobel Laureate" },
    { text: "Understanding rhythmic gene networks unlocks the precision timing of targeted therapeutics.", author: "Circadian Genomics & Precision Medicine" },
    { text: "Within every gene lies a rhythm, and within every rhythm lies the code of life.", author: "CircaNet Core Research" }
];

let quoteInterval = null;

function startQuoteRotation() {
    let qIndex = 0;
    const textEl = document.getElementById('quoteText');
    const authorEl = document.getElementById('quoteAuthor');
    
    if (quoteInterval) clearInterval(quoteInterval);

    quoteInterval = setInterval(() => {
        qIndex = (qIndex + 1) % circadianQuotes.length;
        if (textEl && authorEl) {
            textEl.style.opacity = 0;
            authorEl.style.opacity = 0;
            setTimeout(() => {
                textEl.innerText = `"${circadianQuotes[qIndex].text}"`;
                authorEl.innerText = `â€” ${circadianQuotes[qIndex].author}`;
                textEl.style.transition = "opacity 0.5s ease-in-out";
                authorEl.style.transition = "opacity 0.5s ease-in-out";
                textEl.style.opacity = 1;
                authorEl.style.opacity = 1;
            }, 300);
        }
    }, 3200);
}

function stopQuoteRotation() {
    if (quoteInterval) {
        clearInterval(quoteInterval);
        quoteInterval = null;
    }
}

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('show');
    startQuoteRotation();
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
    stopQuoteRotation();
}

function toggleDrawer() {
    const content = document.getElementById('drawerContent');
    const arrow = document.getElementById('drawerArrow');
    if(content && arrow) {
        content.classList.toggle('show');
        arrow.innerHTML = content.classList.contains('show') ? '&#9650;' : '&#9660;';
    }
}

function selectAllOptCols() {
    document.querySelectorAll('.drawer-checkbox-grid input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function deselectAllOptCols() {
    document.querySelectorAll('.drawer-checkbox-grid input[type="checkbox"]').forEach(cb => cb.checked = false);
}

function submitOptCols() {
    syncAllKeywords();
    showLoading();
    document.getElementById('searchForm').submit();
}

function toggleCellText(el) {
    const cell = el.closest('td');
    const shortSpan = cell.querySelector('.cell-short');
    const fullSpan = cell.querySelector('.cell-full');
    if (shortSpan.classList.contains('d-none')) {
        shortSpan.classList.remove('d-none');
        fullSpan.classList.add('d-none');
    } else {
        shortSpan.classList.add('d-none');
        fullSpan.classList.remove('d-none');
    }
}

function initGeneSelect2() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2-gene').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Select Gene Symbol --',
            allowClear: true,
            width: '100%'
        }).on('change', function() {
            syncRowKeyword($(this).closest('.condition')[0]);
        });
    }
}

function toggleInputType(selectElem) {
    const cond = selectElem.closest('.condition');
    const field = selectElem.value;

    cond.querySelector('.gene-group').classList.add('hidden');
    cond.querySelector('.tissue-group').classList.add('hidden');
    cond.querySelector('.comm-group').classList.add('hidden');
    cond.querySelector('.rs-field-group').classList.add('hidden');
    cond.querySelector('.chrom-group').classList.add('hidden');
    cond.querySelector('.aa-group').classList.add('hidden');
    cond.querySelector('.amclass-group').classList.add('hidden');
    cond.querySelector('.clnsig-group').classList.add('hidden');
    cond.querySelector('.acmg-group').classList.add('hidden');
    cond.querySelector('.evo2-group').classList.add('hidden');

    const posContainer = cond.querySelector('.pos-dependent-inline');
    if (field === 'chrom') {
        cond.querySelector('.chrom-group').classList.remove('hidden');
        posContainer.style.display = 'flex';
    } else {
        posContainer.style.display = 'none';
        const posInput = posContainer.querySelector('.pos-input-field');
        if (posInput) posInput.value = '';
    }

    if (field === 'entrez_gene_symbol') {
        cond.querySelector('.gene-group').classList.remove('hidden');
    } else if (field === 'tissue') {
        cond.querySelector('.tissue-group').classList.remove('hidden');
    } else if (field === 'community_name') {
        cond.querySelector('.comm-group').classList.remove('hidden');
    } else if (field === 'rs') {
        cond.querySelector('.rs-field-group').classList.remove('hidden');
    } else if (field === 'aa_lookup') {
        cond.querySelector('.aa-group').classList.remove('hidden');
    } else if (field === 'am_class') {
        cond.querySelector('.amclass-group').classList.remove('hidden');
    } else if (field === 'clnsig') {
        cond.querySelector('.clnsig-group').classList.remove('hidden');
    } else if (field === 'acmgclassification') {
        cond.querySelector('.acmg-group').classList.remove('hidden');
    } else if (field === 'evo2_prediction') {
        cond.querySelector('.evo2-group').classList.remove('hidden');
    }

    syncRowKeyword(cond);
}

function syncRowKeyword(condRow) {
    const field = condRow.querySelector('.field-select').value;
    const hiddenInp = condRow.querySelector('.keyword-hidden-input');
    
    if (field === 'entrez_gene_symbol') {
        hiddenInp.value = condRow.querySelector('.gene-select').value;
    } else if (field === 'tissue') {
        hiddenInp.value = condRow.querySelector('.tissue-select').value;
    } else if (field === 'community_name') {
        hiddenInp.value = condRow.querySelector('.comm-select').value;
    } else if (field === 'rs') {
        const rawVal = condRow.querySelector('.rs-input-field').value.trim();
        const numVal = rawVal.replace(/[^0-9]/g, '');
        hiddenInp.value = numVal ? ('rs' + numVal) : '';
    } else if (field === 'chrom') {
        hiddenInp.value = condRow.querySelector('.chrom-select').value;
    } else if (field === 'aa_lookup') {
        hiddenInp.value = condRow.querySelector('.aa-select').value;
    } else if (field === 'am_class') {
        hiddenInp.value = condRow.querySelector('.amclass-select').value;
    } else if (field === 'clnsig') {
        hiddenInp.value = condRow.querySelector('.clnsig-select').value;
    } else if (field === 'acmgclassification') {
        hiddenInp.value = condRow.querySelector('.acmg-select').value;
    } else if (field === 'evo2_prediction') {
        hiddenInp.value = condRow.querySelector('.evo2-select').value;
    }
}

function syncAllKeywords() {
    document.querySelectorAll('.condition').forEach(cond => syncRowKeyword(cond));
}

function addCondition() {
    const container = document.getElementById('conditions');
    const firstCond = container.firstElementChild;

    $(firstCond).find('.select2-gene').select2('destroy');

    const newCond = firstCond.cloneNode(true);

    initGeneSelect2();

    newCond.querySelector('.field-select').selectedIndex = 0;
    newCond.querySelector('.op-select').selectedIndex = 0;
    newCond.querySelector('.gene-select').selectedIndex = 0;
    newCond.querySelector('.tissue-select').selectedIndex = 0;
    newCond.querySelector('.comm-select').selectedIndex = 0;
    newCond.querySelector('.rs-input-field').value = '';
    newCond.querySelector('.keyword-hidden-input').value = '';
    
    const posInp = newCond.querySelector('.pos-input-field');
    if (posInp) posInp.value = '';

    container.appendChild(newCond);
    toggleInputType(newCond.querySelector('.field-select'));
    
    initGeneSelect2();
}

function removeCondition(btn) {
    const container = document.getElementById('conditions');
    if (container.children.length > 1) {
        btn.closest('.condition').remove();
    } else {
        alert("At least one search condition row must remain.");
    }
}

function changePage(p) {
    document.getElementById('currentPage').value = p;
    syncAllKeywords();
    showLoading();
    document.getElementById('searchForm').submit();
}

function changeLimit(l) {
    document.getElementById('currentLimit').value = l;
    document.getElementById('currentPage').value = 1;
    syncAllKeywords();
    showLoading();
    document.getElementById('searchForm').submit();
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('rs-input-field')) {
        const input = e.target;
        input.value = input.value.replace(/[^0-9]/g, '');
        syncRowKeyword(input.closest('.condition'));
    }
});

function setupDualScroll() {
    const topContainer = document.getElementById('top-scrollbar-container');
    const bottomContainer = document.getElementById('bottomTableContainer');
    const topContent = document.getElementById('top-scrollbar-content');
    const table = document.getElementById('mainResultsTable');

    if (!topContainer || !bottomContainer || !table) return;

    if (bottomContainer.scrollWidth > bottomContainer.clientWidth) {
        topContainer.style.display = 'block';
        topContent.style.width = table.offsetWidth + 'px';

        let syncing = false;
        topContainer.onscroll = function() {
            if (syncing) return; syncing = true;
            bottomContainer.scrollLeft = topContainer.scrollLeft;
            syncing = false;
        };
        bottomContainer.scrollLeft = topContainer.scrollLeft;
        bottomContainer.addEventListener('scroll', function() {
            if (syncing) return; syncing = true;
            topContainer.scrollLeft = bottomContainer.scrollLeft;
            syncing = false;
        });
    } else {
        topContainer.style.display = 'none';
    }
}

document.getElementById('searchForm').onsubmit = function(e) {
    if (document.getElementById('downloadTrigger').value !== '1') {
        syncAllKeywords();
        showLoading();
    }
};

document.getElementById('downloadBtn').onclick = function() {
    syncAllKeywords();
    document.getElementById('downloadTrigger').value = '1';
    document.getElementById('searchForm').submit();
    setTimeout(() => { document.getElementById('downloadTrigger').value = '0'; }, 1000);
};

window.onload = function() {
    hideLoading();
    initGeneSelect2();
    setupDualScroll();
};
window.onresize = setupDualScroll;
</script>

<?php 
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>
