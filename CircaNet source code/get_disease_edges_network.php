<?php
// get_disease_edges_network.php
require_once 'conn.php';
header('Content-Type: application/json');

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$threshold = isset($_POST['threshold']) ? intval($_POST['threshold']) : 100;

$response = array(
    'edges' => array(),
    'error' => null
);

try {
    // Fetch all disease-disease edges from diseases_edges_tb table
    // filtered by composite_score threshold.
    // LEFT JOIN a deduplicated MONDO -> diseaseName lookup (built from
    // Disease_tb, the Gene-Disease Associations table) so we can return
    // the human-readable Disease Name for both disease_A and disease_B,
    // matched on their MONDO IDs.
    $sql = "SELECT 
                e.disease_A,
                e.disease_B,
                dA.diseaseName AS diseaseA_name,
                dB.diseaseName AS diseaseB_name,
                e.composite_score,
                e.jaccard,
                e.pval_adj,
                e.n_shared
            FROM diseases_edges_tb e
            LEFT JOIN (
                SELECT MONDO, MIN(diseaseName) AS diseaseName
                FROM Disease_tb
                WHERE MONDO IS NOT NULL AND MONDO != ''
                GROUP BY MONDO
            ) dA ON dA.MONDO COLLATE utf8mb4_unicode_ci = e.disease_A COLLATE utf8mb4_unicode_ci
            LEFT JOIN (
                SELECT MONDO, MIN(diseaseName) AS diseaseName
                FROM Disease_tb
                WHERE MONDO IS NOT NULL AND MONDO != ''
                GROUP BY MONDO
            ) dB ON dB.MONDO COLLATE utf8mb4_unicode_ci = e.disease_B COLLATE utf8mb4_unicode_ci
            WHERE e.composite_score >= " . intval($threshold) . "
            ORDER BY e.composite_score DESC
            LIMIT 5000";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        $response['error'] = "Database query failed: " . $conn->error;
        echo json_encode($response);
        exit;
    }
    
    if ($result->num_rows === 0) {
        $response['error'] = null;
        echo json_encode($response);
        exit;
    }
    
    while ($row = $result->fetch_assoc()) {
        $response['edges'][] = array(
            'disease_A' => htmlspecialchars($row['disease_A']),
            'disease_B' => htmlspecialchars($row['disease_B']),
            'diseaseA_name' => htmlspecialchars($row['diseaseA_name'] !== null && $row['diseaseA_name'] !== '' ? $row['diseaseA_name'] : $row['disease_A']),
            'diseaseB_name' => htmlspecialchars($row['diseaseB_name'] !== null && $row['diseaseB_name'] !== '' ? $row['diseaseB_name'] : $row['disease_B']),
            'composite_score' => floatval($row['composite_score']),
            'jaccard' => floatval($row['jaccard']),
            'pval_adj' => floatval($row['pval_adj']),
            'n_shared' => intval($row['n_shared'])
        );
    }
    
    $result->free();
    
} catch (Exception $e) {
    $response['error'] = "Exception: " . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>