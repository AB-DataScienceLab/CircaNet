<?php
require_once 'conn.php';

// Ensure standard mysqli connection variable is mapped
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo; 
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$response = ['results' => []];

if ($term !== '') {
    try {
        // Query database for matches in either field
        $sql = "SELECT DISTINCT am.uniprot_id, m.gene_symbol 
                FROM alphamissense_metric am
                LEFT JOIN main_tb m ON am.uniprot_id = m.uniprot_id
                WHERE m.gene_symbol LIKE ? OR am.uniprot_id LIKE ? 
                LIMIT 15";
                
        $stmt = $conn->prepare($sql);
        $search_term = "%" . $term . "%";
        $stmt->bind_param('ss', $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Set HGNC Symbol as search submission ID if it exists; otherwise fallback to UniProt ID
            $id_value = !empty($row['gene_symbol']) ? $row['gene_symbol'] : $row['uniprot_id'];
            
            // Format descriptive display text
            $display_text = !empty($row['gene_symbol']) 
                ? htmlspecialchars($row['gene_symbol']) . " (" . htmlspecialchars($row['uniprot_id']) . ")" 
                : htmlspecialchars($row['uniprot_id']);
                
            $response['results'][] = [
                'id'   => $id_value,
                'text' => $display_text
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Autocomplete error: " . $e->getMessage());
    }
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
exit;