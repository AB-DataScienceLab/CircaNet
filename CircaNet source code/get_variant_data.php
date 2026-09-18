<?php
// get_variant_data.php
require_once 'conn.php';

error_reporting(0);
header('Content-Type: application/json');

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

// Fail-safe: Temporarily increase the session sort buffer size to prevent out of memory errors
$conn->query("SET SESSION sort_buffer_size = 33554432;"); // 32MB

$request = $_POST;

// Aligned with the sequence defined in variant.php (54 columns total)
$columns = [
    0 => 'row_id',
    1 => 'CHROM',
    2 => 'POS',
    3 => 'REF',
    4 => 'ALT',
    5 => 'SYMBOL',
    6 => 'CLNSIG',
    7 => 'am_class',
    8 => 'RSID',
    9 => 'EVO2_score',
    10 => 'acmgClassification',
    11 => 'VARIANT_CLASS',
    12 => 'Consequence',
    
    // Optional columns sequentially indexed
    13 => 'clinvar_id',
    14 => 'cDNA_position',
    15 => 'CDS_position',
    16 => 'Protein_position',
    17 => 'Amino_acids',
    18 => 'HGNC_ID',
    19 => 'SWISSPROT',
    20 => 'UNIPROT_ISOFORM',
    21 => 'GENE_PHENO',
    22 => 'DOMAINS',
    23 => 'am_pathogenicity',
    24 => 'gnomAD_AF_XX',
    25 => 'gnomAD_AF_XY',
    26 => 'gnomAD_AF_afr_XX',
    27 => 'gnomAD_AF_afr_XY',
    28 => 'gnomAD_AF_afr',
    29 => 'gnomAD_AF_ami_XX',
    30 => 'gnomAD_AF_ami_XY',
    31 => 'gnomAD_AF_ami',
    32 => 'gnomAD_AF_amr_XX',
    33 => 'gnomAD_AF_amr_XY',
    34 => 'gnomAD_AF_amr',
    35 => 'gnomAD_AF_asj_XX',
    36 => 'gnomAD_AF_asj_XY',
    37 => 'gnomAD_AF_asj',
    38 => 'gnomAD_AF_eas_XX',
    39 => 'gnomAD_AF_eas_XY',
    40 => 'gnomAD_AF_eas',
    41 => 'gnomAD_AF_fin_XX',
    42 => 'gnomAD_AF_fin_XY',
    43 => 'gnomAD_AF_fin',
    44 => 'gnomAD_AF_mid_XX',
    45 => 'gnomAD_AF_mid_XY',
    46 => 'gnomAD_AF_mid',
    47 => 'gnomAD_AF_nfe_XX',
    48 => 'gnomAD_AF_nfe_XY',
    49 => 'gnomAD_AF_nfe',
    50 => 'gnomAD_AF_raw',
    51 => 'gnomAD_AF_remaining_XX',
    52 => 'gnomAD_AF_remaining_XY',
    53 => 'gnomAD_AF_remaining'
];

// Build fully-qualified outer SELECT projection to prevent column ambiguity errors
$selectFields = [];
foreach ($columns as $colName) {
    if (!empty($colName)) {
        $escaped = $conn->real_escape_string($colName);
        $selectFields[] = "genetic_variants.`$escaped` AS `$escaped`";
    }
}
$selectClause = implode(", ", array_unique($selectFields));

// 1. Build filtering WHERE clause
$whereClause = " WHERE 1=1 ";
if (!empty($request['symbol'])) {
    $whereClause .= " AND SYMBOL = '" . $conn->real_escape_string($request['symbol']) . "'";
}
if (!empty($request['clnsig'])) {
    $whereClause .= " AND CLNSIG = '" . $conn->real_escape_string($request['clnsig']) . "'";
}
if (!empty($request['amClass'])) {
    $whereClause .= " AND am_class = '" . $conn->real_escape_string($request['amClass']) . "'";
}

// Global Search (Requires at least 2 characters to trigger)
// Modified to incorporate search on 'RSID'
$searchValue = isset($request['search']['value']) ? trim($request['search']['value']) : '';
if (strlen($searchValue) >= 2) {
    $val = $conn->real_escape_string($searchValue);
    $whereClause .= " AND (SYMBOL LIKE '$val%' OR CHROM LIKE '$val%' OR RSID LIKE '$val%') ";
}

// 2. Fast Count Query with 24-hour file caching layer to speed up loads
if (empty($request['symbol']) && empty($request['clnsig']) && (empty($request['amClass']) || $request['amClass'] === '') && strlen($searchValue) < 2) {
    $totalFiltered = 4123904; 
} else {
    $countCacheKey = md5("count_" . $whereClause);
    $countCacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "circanet_count_" . $countCacheKey . ".json";
    $countCacheTime = 86400; // 24 hours
    
    $totalFiltered = false;
    if (file_exists($countCacheFile) && (time() - filemtime($countCacheFile) < $countCacheTime)) {
        $totalFiltered = intval(@file_get_contents($countCacheFile));
    }
    
    if ($totalFiltered === false || $totalFiltered < 0) {
        $countSql = "SELECT COUNT(*) FROM genetic_variants $whereClause";
        $countResult = $conn->query($countSql);
        $totalFiltered = $countResult ? intval($countResult->fetch_row()[0]) : 0;
        @file_put_contents($countCacheFile, $totalFiltered);
    }
}

// 3. Build Ordering Clauses (Applies CASE logic with secondary ascending sort)
$innerOrderClause = "";
$outerOrderClause = "";

if (isset($request['order']) && count($request['order'])) {
    $colIndex = $request['order'][0]['column'];
    $dir = $request['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    $sortCol = isset($columns[$colIndex]) ? $columns[$colIndex] : 'EVO2_score';
    
    if ($sortCol === 'EVO2_score') {
        $innerOrderClause = " ORDER BY (CASE WHEN `EVO2_score` = 'NA' OR `EVO2_score` IS NULL OR `EVO2_score` = '' OR `EVO2_score` = '.' THEN 1 ELSE 0 END) ASC, `EVO2_score` $dir ";
        $outerOrderClause = " ORDER BY (CASE WHEN genetic_variants.`EVO2_score` = 'NA' OR genetic_variants.`EVO2_score` IS NULL OR genetic_variants.`EVO2_score` = '' OR genetic_variants.`EVO2_score` = '.' THEN 1 ELSE 0 END) ASC, genetic_variants.`EVO2_score` $dir ";
    } else {
        $escapedSort = $conn->real_escape_string($sortCol);
        $innerOrderClause = " ORDER BY `$escapedSort` $dir ";
        $outerOrderClause = " ORDER BY genetic_variants.`$escapedSort` $dir ";
    }
} else {
    // Default sort: Priorities non-NA first, sorted by EVO2 score ascending
    $innerOrderClause = " ORDER BY (CASE WHEN `EVO2_score` = 'NA' OR `EVO2_score` IS NULL OR `EVO2_score` = '' OR `EVO2_score` = '.' THEN 1 ELSE 0 END) ASC, `EVO2_score` ASC ";
    $outerOrderClause = " ORDER BY (CASE WHEN genetic_variants.`EVO2_score` = 'NA' OR genetic_variants.`EVO2_score` IS NULL OR genetic_variants.`EVO2_score` = '' OR genetic_variants.`EVO2_score` = '.' THEN 1 ELSE 0 END) ASC, genetic_variants.`EVO2_score` ASC ";
}

// 4. Build Pagination Clause
$limitClause = "";
if (isset($request['start']) && $request['length'] != -1) {
    $limitClause = " LIMIT " . intval($request['start']) . ", " . intval($request['length']);
}

// 5. High-Performance Deferred Join Query (Avoids "Out of sort memory" by sorting only the row_id index)
$sql = "SELECT $selectClause 
        FROM genetic_variants 
        JOIN (
            SELECT `row_id` 
            FROM genetic_variants 
            $whereClause 
            $innerOrderClause 
            $limitClause
        ) AS tmp ON genetic_variants.row_id = tmp.row_id
        $outerOrderClause";

$query = $conn->query($sql);
$data = [];
$start = intval($request['start']) + 1;

if ($query) {
    while($row = $query->fetch_assoc()) {
        $subdata = [];
        $subdata[] = $start++;
        
        // Loop runs sequentially up to index 53 (the total column count minus row_id)
        for($i = 1; $i <= 53; $i++) {
            $colName = $columns[$i];
            if (isset($row[$colName])) {
                $subdata[] = $row[$colName];
            } else {
                $lowerCol = strtolower($colName);
                $subdata[] = isset($row[$lowerCol]) ? $row[$lowerCol] : "";
            }
        }
        $data[] = $subdata;
    }
    
    echo json_encode([
        "draw" => intval($request['draw']),
        "recordsTotal" => 4123904,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    
} else {
    // Returns exact query error message to the DataTables frontend on failure
    $error_msg = "Database Query Error: " . $conn->error . " | Query: " . $sql;
    error_log($error_msg);
    
    echo json_encode([
        "draw" => intval($request['draw']),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => $error_msg
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}
?>