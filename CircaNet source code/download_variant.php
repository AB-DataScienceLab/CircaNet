<?php
// download_variant.php
require_once 'conn.php';

set_time_limit(300); // 5-minute timeout window
ini_set('memory_limit', '512M');

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$symbol = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';
$clnsig = isset($_GET['clnsig']) ? trim($_GET['clnsig']) : '';
$amClass = isset($_GET['amClass']) ? trim($_GET['amClass']) : '';

$sql = "SELECT * FROM genetic_variants WHERE 1=1 ";

if (!empty($symbol)) {
    $sql .= " AND SYMBOL = '" . $conn->real_escape_string($symbol) . "'";
}
if (!empty($clnsig)) {
    $sql .= " AND CLNSIG = '" . $conn->real_escape_string($clnsig) . "'";
}
if (!empty($amClass)) {
    $sql .= " AND am_class = '" . $conn->real_escape_string($amClass) . "'";
}

$filename = "variant_export_" . date('Ymd_His') . ".tsv";

header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Opens progressive buffered output stream to protect system memory
$output = fopen('php://output', 'w');

$headers = [
    'row_id', 'CHROM', 'POS', 'clinvar_id', 'REF', 'ALT', 'CLNDN', 'CLNREVSTAT', 
    'CLNSIG', 'Consequence', 'IMPACT', 'SYMBOL', 'cDNA_position', 'CDS_position', 
    'Protein_position', 'Amino_acids', 'Existing_variation', 'VARIANT_CLASS', 
    'HGNC_ID', 'CANONICAL', 'SWISSPROT', 'UNIPROT_ISOFORM', 'GENE_PHENO', 'DOMAINS', 
    'am_class', 'am_pathogenicity', 'EVO2_score', 'acmgClassification', 'RSID',
    'gnomAD_AF_XX', 'gnomAD_AF_XY', 'gnomAD_AF_afr_XX', 'gnomAD_AF_afr_XY', 'gnomAD_AF_afr',
    'gnomAD_AF_ami_XX', 'gnomAD_AF_ami_XY', 'gnomAD_AF_ami', 'gnomAD_AF_amr_XX', 'gnomAD_AF_amr_XY',
    'gnomAD_AF_amr', 'gnomAD_AF_asj_XX', 'gnomAD_AF_asj_XY', 'gnomAD_AF_asj', 'gnomAD_AF_eas_XX',
    'gnomAD_AF_eas_XY', 'gnomAD_AF_eas', 'gnomAD_AF_fin_XX', 'gnomAD_AF_fin_XY', 'gnomAD_AF_fin',
    'gnomAD_AF_mid_XX', 'gnomAD_AF_mid_XY', 'gnomAD_AF_mid', 'gnomAD_AF_nfe_XX', 'gnomAD_AF_nfe_XY',
    'gnomAD_AF_nfe', 'gnomAD_AF_raw', 'gnomAD_AF_remaining_XX', 'gnomAD_AF_remaining_XY', 'gnomAD_AF_remaining'
];

// Write TSV Column Headers
fwrite($output, implode("\t", $headers) . "\n");

$query = $conn->query($sql);
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $line = [];
        foreach ($headers as $col) {
            $val = isset($row[$col]) ? $row[$col] : '';
            
            // Standardizes dots and empty cells to NA inside the export
            if ($val === '.' || $val === '' || $val === null) {
                $val = 'NA';
            }
            
            $val = str_replace(["\r", "\n", "\t"], ' ', $val); // Strip formatting layout breaks
            $line[] = $val;
        }
        fwrite($output, implode("\t", $line) . "\n");
    }
}
fclose($output);
exit;
?>