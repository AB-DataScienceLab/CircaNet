<?php
// search_suggest.php
header('Content-Type: application/json');
require_once 'conn.php';

$query = trim($_GET['term'] ?? '');

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$results = [];

// 1. Search Genes
$gene_term = $query . '%';
$stmt_gene = $conn->prepare("SELECT DISTINCT Symbol FROM Gene_tb_new_import WHERE Symbol LIKE ? LIMIT 5");
if ($stmt_gene) {
    $stmt_gene->bind_param("s", $gene_term);
    $stmt_gene->execute();
    $res_gene = $stmt_gene->get_result();
    while ($row = $res_gene->fetch_assoc()) {
        $results[] = [
            'type' => 'Gene',
            'symbol' => $row['Symbol'],
            'label' => $row['Symbol']
        ];
    }
    $stmt_gene->close();
}

// 2. Search Diseases
$disease_term = '%' . $query . '%';
$stmt_dis = $conn->prepare("SELECT DISTINCT MONDO, diseaseName FROM Disease_tb WHERE (diseaseName LIKE ? OR MONDO LIKE ?) AND MONDO != '' AND UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%' LIMIT 5");
if ($stmt_dis) {
    $stmt_dis->bind_param("ss", $disease_term, $disease_term);
    $stmt_dis->execute();
    $res_dis = $stmt_dis->get_result();
    while ($row = $res_dis->fetch_assoc()) {
        $results[] = [
            'type' => 'Disease',
            'mondo' => $row['MONDO'],
            'label' => $row['diseaseName'] . ' (' . $row['MONDO'] . ')'
        ];
    }
    $stmt_dis->close();
}

$conn->close();

echo json_encode($results);