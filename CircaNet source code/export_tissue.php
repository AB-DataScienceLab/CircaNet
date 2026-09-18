<?php
// export_tissue.php
require_once 'conn.php';

// Set headers to trigger file download stream
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=tissue_wise_complete_dataset.csv');

// Open system output pointer
$output = fopen('php://output', 'w');

// Set headers row
fputcsv($output, [
    'Gene Symbol', 'Gene Name', 'Ensembl Gene ID', 'HGNC ID', 'NCBI ID',
    'Top Tissue', 'Max TPM', 'Specificity', 'Tau', 'TSI', 'Gini',
    'Z-Max', 'HPA', 'SPM-Max', 'Chr', 'Status'
]);

// Stream data rows out directly 
$query = "SELECT COALESCE(Approved_symbol, Gene_Name) AS Symbol, Approved_name, Gene_ID, HGNC_ID, NCBI_Gene_ID, 
                 Top_Tissue, Max_TPM, Specificity, Tau, TSI, Gini, Z_max, HPA, SPM_max, Chromosome, Status 
          FROM tissue_wise_tb 
          ORDER BY Max_TPM DESC";

$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
}
fclose($output);
exit;
?>