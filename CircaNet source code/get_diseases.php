<?php
// get_diseases.php

header('Content-Type: application/json');
require_once 'conn.php';

// Get the search term from Select2, default to empty string
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$response = [];

// Only perform a search if the user has typed at least 2 characters
if (strlen($searchTerm) >= 2) { 
    // SQL to find distinct disease names that match the search term
    $sql = "SELECT DISTINCT diseaseName FROM Disease_tb WHERE diseaseName LIKE ? ORDER BY diseaseName ASC LIMIT 30";
    
    // Use a prepared statement to prevent any chance of SQL injection
    if ($stmt = $conn->prepare($sql)) {
        // Add wildcards for the LIKE clause
        $searchParam = "%" . $searchTerm . "%";
        $stmt->bind_param("s", $searchParam);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Select2 expects data in a specific format: { id: 'value', text: 'label' }
                $response[] = [
                    'id'   => $row['diseaseName'],
                    'text' => $row['diseaseName']
                ];
            }
        }
        $stmt->close();
    }
}

$conn->close();

// Return the results as a JSON object
echo json_encode($response);
?>