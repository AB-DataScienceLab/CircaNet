<?php
// fetch_chart_data.php
require_once 'conn.php';
header('Content-Type: application/json');

try {
    $response = ['pie' => [], 'bar' => []];

    // 1. Get fast aggregated stats for Pie Chart
    $pieQuery = "SELECT COALESCE(NULLIF(TRIM(Specificity), ''), 'Unknown') AS label, COUNT(*) AS qty 
                 FROM tissue_wise_tb 
                 GROUP BY Specificity";
    $pieResult = $conn->query($pieQuery);
    if ($pieResult) {
        while ($row = $pieResult->fetch_assoc()) {
            $response['pie'][] = [
                'label' => $row['label'],
                'value' => (int)$row['qty']
            ];
        }
    }

    // 2. Limit to top 10 items for the Max TPM Bar Chart
    $barQuery = "SELECT COALESCE(Approved_symbol, Gene_Name, 'N/A') AS label, Max_TPM 
                 FROM tissue_wise_tb 
                 ORDER BY Max_TPM DESC 
                 LIMIT 10";
    $barResult = $conn->query($barQuery);
    if ($barResult) {
        while ($row = $barResult->fetch_assoc()) {
            $response['bar'][] = [
                'label' => $row['label'],
                'value' => (float)$row['Max_TPM']
            ];
        }
    }

    echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>