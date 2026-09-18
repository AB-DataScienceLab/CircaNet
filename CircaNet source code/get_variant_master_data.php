<?php
// get_variant_master_data.php
require_once 'db_connect.php';

error_reporting(0);
header('Content-Type: application/json');

if (!isset($dbconn) || !$dbconn) {
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Database connection failed"]);
    exit;
}

// -------------------------------------------------------------------------
// Connect to secondary partitioned database (circanet_db_new)
// -------------------------------------------------------------------------
$new_db_name = 'circanet_db_new';

$pg_host = $host ?? $dbhost ?? $db_host ?? pg_host($dbconn);
$pg_port = $port ?? $dbport ?? $db_port ?? pg_port($dbconn);
$pg_user = $user ?? $username ?? $dbuser ?? $db_user ?? pg_parameter_status($dbconn, 'session_authorization');
$pg_pass = $password ?? $pass ?? $dbpass ?? $db_pass ?? '';

$conn_attempts = [];
$str1 = "dbname={$new_db_name}";
if (!empty($pg_host)) $str1 .= " host={$pg_host}";
if (!empty($pg_port)) $str1 .= " port={$pg_port}";
if (!empty($pg_user)) $str1 .= " user={$pg_user}";
if (!empty($pg_pass)) $str1 .= " password={$pg_pass}";
$conn_attempts[] = $str1;

if (!empty($pg_user)) {
    $conn_attempts[] = "dbname={$new_db_name} user={$pg_user}";
}
$conn_attempts[] = "dbname={$new_db_name}";

$dbconn_new = false;
foreach ($conn_attempts as $conn_str) {
    $dbconn_new = @pg_connect($conn_str);
    if ($dbconn_new) {
        break;
    }
}

// Target database connection to run the variant queries against
$active_db = $dbconn_new ?: $dbconn;
@pg_query($active_db, "SET statement_timeout = 15000; SET work_mem = '64MB';");

$request = $_POST;

// Helper to determine partition table name
function get_partition_table($chrom) {
    $c = strtolower(trim((string)$chrom));
    $c = ltrim($c, 'chr');
    if ($c === 'm' || $c === 'mt') return 'master_chrm';
    if ($c === 'x') return 'master_chrx';
    if ($c === 'y') return 'master_chry';
    if (is_numeric($c) && (int)$c >= 1 && (int)$c <= 22) return 'master_chr' . (int)$c;
    return null;
}

// 41-Value Group Mappings for ClinVar
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

// Sequential column mapping
$columns = [
    0  => 'ct_id',
    1  => 'chrom',
    2  => 'pos',
    3  => 'ref',
    4  => 'alt',
    5  => 'entrez_gene_symbol',
    6  => 'clnsig',
    7  => 'am_class',
    8  => 'rs',
    9  => 'evo2_prediction',
    10 => 'acmgclassification',
    11 => 'varianttype',
    12 => 'consequence',
    
    // Optional columns
    13 => 'id',
    14 => 'hgvsc',
    15 => 'hgvsp',
    16 => 'aachange',
    17 => 'transcript',
    18 => 'entrez_gene',
    19 => 'uniprot_id',
    20 => 'am_pathogenicity',
    21 => 'evo2_score',
    22 => 'clndn',
    23 => 'clnrevstat',
    24 => 'af_joint_raw',
    25 => 'af_joint',
    26 => 'af_joint_xx',
    27 => 'af_joint_xy',
    28 => 'af_joint_afr',
    29 => 'af_joint_afr_xx',
    30 => 'af_joint_afr_xy',
    31 => 'af_joint_ami',
    32 => 'af_joint_ami_xx',
    33 => 'af_joint_ami_xy',
    34 => 'af_joint_amr',
    35 => 'af_joint_amr_xx',
    36 => 'af_joint_amr_xy',
    37 => 'af_joint_asj',
    38 => 'af_joint_asj_xx',
    39 => 'af_joint_asj_xy',
    40 => 'af_joint_eas',
    41 => 'af_joint_eas_xx',
    42 => 'af_joint_eas_xy',
    43 => 'af_joint_fin',
    44 => 'af_joint_fin_xx',
    45 => 'af_joint_fin_xy',
    46 => 'af_joint_mid',
    47 => 'af_joint_mid_xx',
    48 => 'af_joint_mid_xy',
    49 => 'af_joint_nfe',
    50 => 'af_joint_nfe_xx',
    51 => 'af_joint_nfe_xy',
    52 => 'af_joint_remaining',
    53 => 'af_joint_sas'
];

$quoted_cols = [];
foreach ($columns as $idx => $colName) {
    if ($idx > 0 && !empty($colName)) {
        $quoted_cols[] = '"' . trim($colName) . '"';
    }
}
$selectClause = implode(', ', array_unique($quoted_cols));

$whereClauses = [];
$params = [];
$paramIndex = 1;
$target_tables = [];

// 1. Symbol Filter (Uses gene_lookup for partition pruning)
if (!empty($request['symbol'])) {
    $sym = trim($request['symbol']);
    $upperSym = strtoupper($sym);
    
    $pIdx1 = '$' . $paramIndex++;
    $pIdx2 = '$' . $paramIndex++;
    $whereClauses[] = "(entrez_gene_symbol = $pIdx1 OR entrez_gene_symbol = $pIdx2)";
    $params[] = $sym;
    $params[] = $upperSym;

    $lookupSql = "SELECT DISTINCT chrom FROM gene_lookup WHERE entrez_gene_symbol = $1 OR entrez_gene_symbol = $2 LIMIT 5";
    $lookupRes = @pg_query_params($dbconn, $lookupSql, [$sym, $upperSym]);
    if ($lookupRes) {
        $tChrs = [];
        while ($lRow = pg_fetch_assoc($lookupRes)) {
            if (!empty($lRow['chrom'])) {
                $tChrs[] = $lRow['chrom'];
                $pTab = get_partition_table($lRow['chrom']);
                if ($pTab && !in_array($pTab, $target_tables)) {
                    $target_tables[] = $pTab;
                }
            }
        }
        if (!empty($tChrs)) {
            $inList = [];
            foreach ($tChrs as $tc) {
                $pChr = '$' . $paramIndex++;
                $inList[] = $pChr;
                $params[] = $tc;
            }
            $whereClauses[] = "chrom IN (" . implode(', ', $inList) . ")";
        }
    }
}

// 2. ClinVar Filter
if (!empty($request['clnsig'])) {
    $cVal = trim($request['clnsig']);
    if (isset($clnsig_groups[$cVal])) {
        $mapped_values = $clnsig_groups[$cVal];
        $inPlaceholders = [];
        foreach ($mapped_values as $mVal) {
            $pInIdx = '$' . $paramIndex++;
            $inPlaceholders[] = $pInIdx;
            $params[] = $mVal;
        }
        $whereClauses[] = "clnsig IN (" . implode(', ', $inPlaceholders) . ")";
    } else {
        $pIdx = '$' . $paramIndex++;
        $whereClauses[] = "clnsig = $pIdx";
        $params[] = $cVal;
    }
}

// 3. AM Class Filter
if (!empty($request['amClass'])) {
    $amVal = trim($request['amClass']);
    $pIdx1 = '$' . $paramIndex++;
    $pIdx2 = '$' . $paramIndex++;
    $whereClauses[] = "(am_class = $pIdx1 OR am_class = $pIdx2)";
    $params[] = strtolower($amVal);
    $params[] = $amVal;
}

// 4. Global Search (Symbol, Chrom, RSID)
$searchValue = isset($request['search']['value']) ? trim($request['search']['value']) : '';
if (strlen($searchValue) >= 2) {
    $numRs = preg_replace('/^rs/i', '', $searchValue);
    $chrPrefix = strpos(strtolower($searchValue), 'chr') === 0 ? $searchValue : 'chr' . $searchValue;
    
    $pTab = get_partition_table($searchValue);
    if ($pTab && !in_array($pTab, $target_tables)) {
        $target_tables[] = $pTab;
    }

    $pIdx1 = '$' . $paramIndex++;
    $pIdx2 = '$' . $paramIndex++;
    $pIdx3 = '$' . $paramIndex++;
    $pIdx4 = '$' . $paramIndex++;

    $whereClauses[] = "(entrez_gene_symbol ILIKE $pIdx1 OR chrom = $pIdx2 OR rs = $pIdx3 OR rs = $pIdx4)";
    $params[] = $searchValue . '%';
    $params[] = $chrPrefix;
    $params[] = $searchValue;
    $params[] = $numRs;
}

// Initial Default Optimization (instant 1ms initial response)
if (empty($request['symbol']) && empty($request['clnsig']) && strlen($searchValue) < 2) {
    $target_tables = ['master_chr1'];
    $whereClauses[] = "chrom = 'chr1'";
    $whereClauses[] = "rs IS NOT NULL AND rs != '' AND rs != '.' AND rs != 'NA'";
    $whereClauses[] = "evo2_prediction IS NOT NULL AND evo2_prediction != '' AND evo2_prediction != '.' AND evo2_prediction != 'NA'";
}

$fullWhere = "";
if (!empty($whereClauses)) {
    $fullWhere = "WHERE " . implode(' AND ', $whereClauses);
}

// Build table list from partitioned tables or fallback to single master table
if (empty($target_tables)) {
    for ($cNum = 1; $cNum <= 22; $cNum++) {
        $target_tables[] = 'master_chr' . $cNum;
    }
    $target_tables[] = 'master_chrx';
    $target_tables[] = 'master_chry';
    $target_tables[] = 'master_chrm';
}

$union_queries = [];
foreach ($target_tables as $tName) {
    $union_queries[] = "SELECT $selectClause FROM $tName";
}
$fromClause = count($union_queries) === 1 ? $target_tables[0] : "( " . implode(" UNION ALL ", $union_queries) . " ) AS mv";

// Ordering Clause
$orderClause = "";
if (isset($request['order']) && count($request['order'])) {
    $colIndex = (int)$request['order'][0]['column'];
    $dir = $request['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
    if (isset($columns[$colIndex]) && $columns[$colIndex] !== 'ct_id') {
        $sortCol = $columns[$colIndex];
        $orderClause = "ORDER BY \"$sortCol\" $dir";
    }
}

if (empty($orderClause)) {
    $orderClause = "ORDER BY chrom ASC, pos ASC";
}

// Pagination
$start = isset($request['start']) ? intval($request['start']) : 0;
$length = isset($request['length']) ? intval($request['length']) : 25;
if ($length < 1) $length = 25;

// Data Query
$dataSql = "SELECT $selectClause FROM $fromClause $fullWhere $orderClause LIMIT $length OFFSET $start";
$dRes = @pg_query_params($active_db, $dataSql, $params);

// Fast fallback to primary dbconn if partitioned query failed
if (!$dRes && $active_db !== $dbconn) {
    $active_db = $dbconn;
    $dRes = @pg_query_params($active_db, "SELECT $selectClause FROM master_variants $fullWhere $orderClause LIMIT $length OFFSET $start", $params);
}

// Fast bounded count query
$displayTotal = 10000;
$countSql = "SELECT COUNT(*) as total FROM (SELECT 1 FROM $fromClause $fullWhere LIMIT 10001) sub";
$cRes = @pg_query_params($active_db, $countSql, $params);
if ($cRes && ($cRow = pg_fetch_assoc($cRes))) {
    $countFound = (int)$cRow['total'];
    $displayTotal = ($countFound > 10000) ? 10000 : $countFound;
}

$data = [];
$srNo = $start + 1;

if ($dRes) {
    while ($row = pg_fetch_assoc($dRes)) {
        $subdata = [];
        $subdata[] = $srNo++;
        
        for ($i = 1; $i <= 53; $i++) {
            $colName = $columns[$i];
            $val = $row[$colName] ?? 'NA';
            if ($val === '' || $val === null || $val === '.') {
                $val = 'NA';
            }
            $subdata[] = $val;
        }
        $data[] = $subdata;
    }

    // Set recordsTotal == recordsFiltered to eliminate misleading "(filtered from 630M entries)"
    echo json_encode([
        "draw"            => intval($request['draw'] ?? 1),
        "recordsTotal"    => $displayTotal,
        "recordsFiltered" => $displayTotal,
        "data"            => $data
    ]);
} else {
    echo json_encode([
        "draw"            => intval($request['draw'] ?? 1),
        "recordsTotal"    => 0,
        "recordsFiltered" => 0,
        "data"            => []
    ]);
}
?>