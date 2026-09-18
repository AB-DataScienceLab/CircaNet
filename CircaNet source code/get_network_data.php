<?php
// get_network_data.php
// NOTE: This endpoint has been deprecated as the dynamic network container was removed from variant.php.

error_reporting(0);
header('Content-Type: application/json');

// Returns an empty successful payload to handle legacy requests gracefully without frontend errors
echo json_encode([
    'success' => true,
    'nodes' => [],
    'edges' => [],
    'stats' => [
        'total_nodes' => 0,
        'total_edges' => 0
    ]
]);
exit;
?>