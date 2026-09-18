<?php
// get_dashboard_data.php
// This script fetches data for the chart and network visuals.

require_once 'conn.php';

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response structure
$response = [
    'chart' => [
        'labels' => [],
        'data' => []
    ],
    'network' => [
        'nodes' => [],
        'edges' => []
    ]
];

// --- 1. Data for the Chart (Top 10 Genes by Variant Count) ---
$chart_sql = "SELECT SYMBOL, COUNT(*) as variant_count 
              FROM variant_tb 
              WHERE SYMBOL IS NOT NULL AND SYMBOL != '' AND SYMBOL != 'NULL'
              GROUP BY SYMBOL 
              HAVING COUNT(*) > 1
              ORDER BY variant_count DESC 
              LIMIT 10";

$chart_result = $conn->query($chart_sql);

$top_gene_symbols = [];

if ($chart_result && $chart_result->num_rows > 0) {
    while($row = $chart_result->fetch_assoc()) {
        $response['chart']['labels'][] = $row['SYMBOL'];
        $response['chart']['data'][] = (int)$row['variant_count'];
        $top_gene_symbols[] = $row['SYMBOL']; // Collect symbols for the next query
    }
}

// --- 2. Data for Network Graph ---
// Only run this query if we found top genes from the first query
if (!empty($top_gene_symbols)) {
    // Sanitize symbols for the IN clause
    $in_clause = "'" . implode("','", array_map([$conn, 'real_escape_string'], $top_gene_symbols)) . "'";
    
    $network_sql = "
        SELECT DISTINCT SYMBOL, CLNSIG 
        FROM variant_tb
        WHERE SYMBOL IN ($in_clause) 
        AND CLNSIG IS NOT NULL 
        AND CLNSIG != '' 
        AND CLNSIG != 'not_specified'
        AND CLNSIG != 'NULL'
    ";
    
    $network_result = $conn->query($network_sql);

    if ($network_result && $network_result->num_rows > 0) {
        $nodes = [];
        while ($row = $network_result->fetch_assoc()) {
            $gene = $row['SYMBOL'];
            $clnsig = $row['CLNSIG'];

            // Add gene node if not already added
            if (!isset($nodes[$gene])) {
                $nodes[$gene] = true;
                $response['network']['nodes'][] = ['id' => $gene, 'label' => $gene, 'type' => 'gene'];
            }
            // Add clnsig node if not already added
            if (!isset($nodes[$clnsig])) {
                $nodes[$clnsig] = true;
                $response['network']['nodes'][] = ['id' => $clnsig, 'label' => $clnsig, 'type' => 'clnsig'];
            }
            // Add the edge connecting them
            $response['network']['edges'][] = ['source' => $gene, 'target' => $clnsig];
        }
    }
}

// Close the connection
$conn->close();

// Send the JSON response
echo json_encode($response);