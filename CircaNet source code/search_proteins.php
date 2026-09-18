<?php
require_once 'conn.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$table = 'Prot_string_with_HGNC';

$results = [];

if (strlen($term) >= 2) {
    $stmt = $conn->prepare("SELECT DISTINCT preferredName_A FROM $table WHERE preferredName_A LIKE ? LIMIT 20");
    $likeTerm = $term . "%";
    $stmt->bind_param("s", $likeTerm);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'id' => $row['preferredName_A'],
            'text' => $row['preferredName_A']
        ];
    }
    $stmt->close();
}

echo json_encode($results);
$conn->close();