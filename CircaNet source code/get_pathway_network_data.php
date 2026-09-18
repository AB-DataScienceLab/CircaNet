<?php
header('Content-Type: application/json');
require_once 'conn.php';

$pathway = $_POST['pathway'] ?? '';
$search = $_POST['search'] ?? '';

$where = " WHERE 1=1 AND GeneID IS NOT NULL AND Pathway_Name IS NOT NULL AND Pathway_Name != ''";

if ($pathway) {
    $where .= " AND Pathway_Name = '" . $conn->real_escape_string($pathway) . "'";
}
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (Pathway_Name LIKE '%$s%' OR GeneID LIKE '%$s%')";
}

// Limit 150 connections to maintain browser performance during dynamic updates
$sql = "SELECT DISTINCT GeneID, Pathway_Name FROM Pathway_tb $where LIMIT 150";
$result = $conn->query($sql);

$nodes = []; $edges = []; $node_map = [];

while ($row = $result->fetch_assoc()) {
    $gNode = "g_" . $row['GeneID'];
    $pNode = "p_" . md5($row['Pathway_Name']);

    if (!isset($node_map[$gNode])) {
        $nodes[] = ['id' => $gNode, 'label' => "Gene: " . $row['GeneID'], 'group' => 'gene'];
        $node_map[$gNode] = true;
    }
    if (!isset($node_map[$pNode])) {
        $nodes[] = ['id' => $pNode, 'label' => $row['Pathway_Name'], 'group' => 'pathway', 'title' => $row['Pathway_Name']];
        $node_map[$pNode] = true;
    }
    $edges[] = ['from' => $gNode, 'to' => $pNode];
}

echo json_encode([
    'success' => true, 
    'nodes' => $nodes, 
    'edges' => $edges, 
    'stats' => ['total_nodes' => count($nodes)]
]);