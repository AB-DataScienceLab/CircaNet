<?php
require_once 'conn.php';

$chromosome  = isset($_GET['chromosome']) ? trim($_GET['chromosome']) : '';
$symbol      = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';
$searchValue = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereClauses = ["1=1"];
$params = [];
$types = "";

if (!empty($chromosome)) {
    if ($chromosome === 'X and Y') {
        $whereClauses[] = "(Chromosome = 'X and Y' OR Chromosome = 'X,Y')";
    } elseif ($chromosome === 'mitochondria') {
        $whereClauses[] = "(Chromosome = 'mitochondria' OR Chromosome = 'MT' OR Chromosome = 'M' OR Chromosome = 'chrM' OR Chromosome = 'chrMT')";
    } else {
        $whereClauses[] = "Chromosome = ?";
        $params[] = $chromosome;
        $types .= "s";
    }
}

if (!empty($symbol)) {
    $whereClauses[] = "symbol = ?";
    $params[] = $symbol;
    $types .= "s";
}

if (!empty($searchValue)) {
    $whereClauses[] = "(symbol LIKE ? OR hgnc_id LIKE ? OR entrez_id LIKE ? OR Chromosome LIKE ?)";
    $searchParam = "%" . $searchValue . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

$whereSql = implode(" AND ", $whereClauses);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=gene_catalog_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['HGNC ID', 'Symbol', 'Gene Name', 'Entrez ID', 'Ensembl Gene ID', 'Chromosome', 'Locus Type']);

$sql = "SELECT hgnc_id, symbol, name, entrez_id, ensembl_gene_id, Chromosome, locus_type 
        FROM gene_annotation 
        WHERE $whereSql 
        ORDER BY symbol ASC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $row['hgnc_id'],
        $row['symbol'],
        $row['name'],
        $row['entrez_id'],
        $row['ensembl_gene_id'],
        $row['Chromosome'],
        $row['locus_type']
    ]);
}

$stmt->close();
$conn->close();
exit;