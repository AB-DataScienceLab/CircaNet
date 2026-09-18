<?php
// Include connection
require_once 'conn.php';

// Ensure standard mysqli connection variable is mapped
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo; 
}

// Retrieve active filters
$search      = isset($_GET['search']) ? trim($_GET['search']) : '';
$risk_filter = isset($_GET['risk_filter']) ? $_GET['risk_filter'] : 'all';
$sort_by     = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'gene_symbol';
$order       = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
$format      = isset($_GET['format']) && $_GET['format'] === 'tsv' ? 'tsv' : 'csv';

// Whitelist sorting parameters
$allowed_sort_columns = [
    'gene_symbol', 'uniprot_id', 'protein_length', 'high_risk_residues', 
    'max_burden_percent', 'mean_am_score', 'total_substitutions', 
    'pathogenic_substitutions', 'pathogenic_sub_fraction'
];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'gene_symbol';
}

// Normalize column prefixes for SQL sort statement
$sort_by_prefix = ($sort_by === 'gene_symbol') ? 'm.gene_symbol' : (($sort_by === 'uniprot_id') ? 'am.uniprot_id' : 'am.' . $sort_by);

// Build query with LEFT JOIN mapping
$where_clauses = [];
$bind_types = '';
$bind_params = [];

if ($search !== '') {
    $where_clauses[] = "(m.gene_symbol LIKE ? OR am.uniprot_id LIKE ?)";
    $bind_types .= 'ss';
    $bind_params[] = "%" . $search . "%";
    $bind_params[] = "%" . $search . "%";
}

if ($risk_filter === 'high') {
    $where_clauses[] = "am.max_burden_percent >= 50";
} elseif ($risk_filter === 'medium') {
    $where_clauses[] = "am.max_burden_percent >= 10 AND am.max_burden_percent < 50";
} elseif ($risk_filter === 'low') {
    $where_clauses[] = "am.max_burden_percent < 10";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Run Query mapping main_tb to obtain HGNC symbols
$sql = "SELECT am.*, m.gene_symbol 
        FROM alphamissense_metric am
        LEFT JOIN main_tb m ON am.uniprot_id = m.uniprot_id
        $where_sql 
        ORDER BY $sort_by_prefix $order";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($search !== '') {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set format-specific connection outputs
    $delimiter = ($format === 'tsv') ? "\t" : ",";
    $file_extension = ($format === 'tsv') ? 'tsv' : 'csv';
    $mime_type = ($format === 'tsv') ? 'text/tab-separated-values' : 'text/csv';
    
    header('Content-Type: ' . $mime_type . '; charset=utf-8');
    header('Content-Disposition: attachment; filename=alphamissense_export_' . date('Ymd_His') . '.' . $file_extension);
    
    $output = fopen('php://output', 'w');
    
    // Header Row mapping
    $headers = [
        'Sr. No.', 
        'Gene Symbol',
        'UniProt ID', 
        'Protein Length (aa)', 
        'High-Risk Residues', 
        'Max Burden %', 
        'Mean AM Score', 
        'Total Substitutions', 
        'Pathogenic Substitutions', 
        'Pathogenic Sub Fraction %'
    ];
    
    // Write format-specific headers
    if ($format === 'tsv') {
        fwrite($output, implode("\t", $headers) . "\n");
    } else {
        fputcsv($output, $headers);
    }
    
    $sr_no = 1;
    while ($row = $result->fetch_assoc()) {
        $gene_name = !empty($row['gene_symbol']) ? $row['gene_symbol'] : 'N/A';
        $data_line = [
            $sr_no++,
            $gene_name,
            $row['uniprot_id'],
            $row['protein_length'],
            $row['high_risk_residues'],
            round($row['max_burden_percent'], 4),
            round($row['mean_am_score'], 6),
            $row['total_substitutions'],
            $row['pathogenic_substitutions'],
            round($row['pathogenic_sub_fraction'], 4)
        ];
        
        if ($format === 'tsv') {
            fwrite($output, implode("\t", $data_line) . "\n");
        } else {
            fputcsv($output, $data_line);
        }
    }
    
    fclose($output);
    $stmt->close();
}
$conn->close();
exit;