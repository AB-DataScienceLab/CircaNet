<?php
// search_diseases.php

header('Content-Type: application/json');
require_once 'conn.php';

// Get the search term from Select2's AJAX request
$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

if (empty($searchTerm)) {
    echo json_encode([]);
    exit();
}

$response = [];
$searchQuery = "%" . $searchTerm . "%";

// Query to find distinct diseases matching the search term.
// LIMIT is important to keep the response fast.
$sql = "SELECT DISTINCT diseaseName FROM Disease_tb WHERE diseaseName LIKE ? ORDER BY diseaseName ASC LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $searchQuery);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Select2 expects the data in a specific {id, text} format
        $response[] = [
            'id'   => $row['diseaseName'],
            'text' => $row['diseaseName']
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>