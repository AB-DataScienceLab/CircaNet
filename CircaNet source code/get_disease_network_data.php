<?php
// get_disease_network_data.php (Final working version)
header('Content-Type: application/json');
require_once 'conn.php';

$limit = intval($_POST['limit'] ?? 200);
$symbolFilter = $_POST['symbol'] ?? '';
$diseaseFilter = $_POST['disease'] ?? '';
$sourceFilter = $_POST['source'] ?? '';
$searchValue = $_POST['search'] ?? '';

$whereClauses = [];
$params = [];
$param_types = '';

// BASE CHANGE: Always exclude "Disgenet" and "ClinGen" records from network calculations
$whereClauses[] = "UPPER(Source) NOT LIKE '%DISGENET%'";
$whereClauses[] = "UPPER(Source) NOT LIKE '%CLINGEN%'";

// Apply case-insensitive filtering for symbol and disease
if (!empty($symbolFilter)) {
    $whereClauses[] = "UPPER(TRIM(Approved_symbol)) = UPPER(?)";
    $params[] = $symbolFilter;
    $param_types .= 's';
}

if (!empty($diseaseFilter)) {
    $whereClauses[] = "UPPER(TRIM(diseaseName)) = UPPER(?)";
    $params[] = $diseaseFilter;
    $param_types .= 's';
}

// Modified source filter to be case-insensitive and handle spaces
if (!empty($sourceFilter)) {
    $whereClauses[] = "UPPER(TRIM(Source)) = UPPER(?)";
    $params[] = $sourceFilter;
    $param_types .= 's';
}

if (!empty($searchValue)) {
    $search_term = "%" . $searchValue . "%";
    $whereClauses[] = "(Approved_symbol LIKE ? OR diseaseName LIKE ? OR MONDO LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'sss';
}

$where_sql = '';
if (count($whereClauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $whereClauses);
}

$nodes = [];
$edges = [];
$node_ids = [];

try {
    $sql = "SELECT Approved_symbol, diseaseName FROM Disease_tb {$where_sql} LIMIT ?";
    $params[] = $limit;
    $param_types .= 'i';
    
    $stmt = $conn->prepare($sql);
    if($param_types) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $gene_id = 'g_' . $row['Approved_symbol'];
            $disease_id = 'd_' . md5($row['diseaseName']);
            
            if (!isset($node_ids[$gene_id])) {
                $nodes[] = [
                    'id' => $gene_id,
                    'label' => $row['Approved_symbol'],
                    'group' => 'gene',
                    'title' => 'Gene: ' . $row['Approved_symbol']
                ];
                $node_ids[$gene_id] = true;
            }
            
            if (!isset($node_ids[$disease_id])) {
                $nodes[] = [
                    'id' => $disease_id,
                    'label' => $row['diseaseName'],
                    'group' => 'disease',
                    'title' => 'Disease: ' . $row['diseaseName']
                ];
                $node_ids[$disease_id] = true;
            }
            
            $edges[] = ['from' => $gene_id, 'to' => $disease_id];
        }
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'nodes' => $nodes,
        'edges' => array_values(array_unique($edges, SORT_REGULAR)),
        'stats' => [
            'total_nodes' => count($nodes),
            'total_edges' => count($edges)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>