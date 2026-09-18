<?php
require_once 'conn.php';

// Select2 sends the search string in the 'term' parameter
$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

$data = [];

// If no term is provided, we can return an empty array or top results
// To prevent loading the whole DB, we only search if term length > 0
if (!empty($searchTerm)) {
    $safeTerm = $conn->real_escape_string($searchTerm);
    $sql = "SELECT DISTINCT Pathway_Name FROM Pathway_tb 
            WHERE Pathway_Name LIKE '%$safeTerm%' 
            AND Pathway_Name IS NOT NULL AND Pathway_Name != ''
            ORDER BY Pathway_Name ASC 
            LIMIT 30";
} else {
    // Optional: provide some default results when dropdown is first clicked
    $sql = "SELECT DISTINCT Pathway_Name FROM Pathway_tb 
            WHERE Pathway_Name IS NOT NULL AND Pathway_Name != ''
            ORDER BY Pathway_Name ASC 
            LIMIT 10";
}

$result = $conn->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['Pathway_Name'], 
            'text' => $row['Pathway_Name']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($data);