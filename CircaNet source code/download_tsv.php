<?php
// download_tsv.php
// This script generates a TSV file of variant data based on provided filters.

// 1. DATABASE CONNECTION
// -----------------------------------------------------------------------------
require_once 'conn.php';

// 2. GET FILTER PARAMETERS FROM URL
// -----------------------------------------------------------------------------
$symbolFilter = isset($_GET['symbolFilter']) ? $_GET['symbolFilter'] : '';
$clnsigFilter = isset($_GET['clnsigFilter']) ? $_GET['clnsigFilter'] : '';
$amClassFilter = isset($_GET['amClassFilter']) ? $_GET['amClassFilter'] : '';

// 3. DEFINE COLUMNS for the TSV file
// -----------------------------------------------------------------------------
$db_columns = [
    'SYMBOL', 'HGNC_ID', 'variant_internal_id', 'CHROM', 'POS', 'REF', 'ALT',
    'CLNSIG', 'CLNDN', 'am_class', 'Protein_position', 'Amino_acids', 'gnomAD',
    'gnomAD_AF_XX', 'gnomAD_AF_XY', 'gnomAD_AF_afr_XX', 'gnomAD_AF_afr_XY',
    'gnomAD_AF_afr', 'gnomAD_AF_ami_XX', 'gnomAD_AF_ami_XY', 'gnomAD_AF_ami',
    'gnomAD_AF_amr_XX', 'gnomAD_AF_amr_XY', 'gnomAD_AF_amr', 'gnomAD_AF_asj_XX',
    'gnomAD_AF_asj_XY', 'gnomAD_AF_asj', 'gnomAD_AF_eas_XX', 'gnomAD_AF_eas_XY',
    'gnomAD_AF_eas', 'gnomAD_AF_fin_XX', 'gnomAD_AF_fin_XY', 'gnomAD_AF_fin',
    'gnomAD_AF_mid_XX', 'gnomAD_AF_mid_XY', 'gnomAD_AF_mid', 'gnomAD_AF_nfe_XX',
    'gnomAD_AF_nfe_XY', 'gnomAD_AF_nfe', 'gnomAD_AF_raw', 'gnomAD_AF_remaining_XX',
    'gnomAD_AF_remaining_XY', 'gnomAD_AF_remaining'
];
$select_columns = implode(', ', $db_columns);

// 4. BUILD SQL QUERY (Securely with Prepared Statements)
// -----------------------------------------------------------------------------
$where_clauses = [];
$params = [];
$param_types = '';
$sql = "SELECT $select_columns FROM variant_tb";

if (!empty($symbolFilter)) {
    $where_clauses[] = "SYMBOL = ?";
    $params[] = $symbolFilter;
    $param_types .= 's';
}
if (!empty($clnsigFilter)) {
    $where_clauses[] = "CLNSIG = ?";
    $params[] = $clnsigFilter;
    $param_types .= 's';
}
if (!empty($amClassFilter)) {
    $where_clauses[] = "am_class = ?";
    $params[] = $amClassFilter;
    $param_types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

// Add ordering for consistent output
$sql .= " ORDER BY SYMBOL ASC, POS ASC";

// 5. SET HTTP HEADERS FOR DOWNLOAD
// -----------------------------------------------------------------------------
$filename = "variants_" . date('Y-m-d') . ".tsv";
header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 6. OPEN OUTPUT STREAM AND WRITE DATA
// -----------------------------------------------------------------------------
// Open a file pointer to the PHP output stream
$output = fopen('php://output', 'w');

// Write the header row to the TSV file
fputcsv($output, $db_columns, "\t");

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Loop through the results and write each row
    while ($row = $result->fetch_assoc()) {
        $data_row = [];
        foreach ($db_columns as $col) {
            $value = $row[$col];
            // Replace blank/null values with 'NA'
            if ($value === null || trim((string)$value) === '') {
                $data_row[] = 'NA';
            } else {
                $data_row[] = $value;
            }
        }
        fputcsv($output, $data_row, "\t");
    }
    $stmt->close();
}

fclose($output);
$conn->close();
exit();