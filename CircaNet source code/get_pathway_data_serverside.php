<?php
require_once 'conn.php';

$requestData = $_POST;
$columns = array(0 => 'GeneID', 1 => 'GeneID', 2 => 'Identifier', 3 => 'Pathway_Name', 4 => 'Evidence_code', 5 => 'Organism');

$sql = "SELECT GeneID, Identifier, URL, Pathway_Name, Evidence_code, Organism FROM Pathway_tb WHERE 1=1";

if (!empty($requestData['pathway'])) {
    $sql .= " AND Pathway_Name = '" . $conn->real_escape_string($requestData['pathway']) . "'";
}
if (!empty($requestData['search']['value'])) {
    $s = $conn->real_escape_string($requestData['search']['value']);
    $sql .= " AND (Pathway_Name LIKE '%$s%' OR GeneID LIKE '%$s%' OR Identifier LIKE '%$s%')";
}

$orderCol = $columns[$requestData['order'][0]['column']];
$orderDir = $requestData['order'][0]['dir'];

// Custom Sorting: Rows where critical data is empty move to bottom
$sql .= " ORDER BY 
            (CASE WHEN (GeneID IS NULL OR GeneID = '') AND (Pathway_Name IS NULL OR Pathway_Name = '') THEN 1 ELSE 0 END) ASC, 
            $orderCol $orderDir";

$sql .= " LIMIT " . $requestData['start'] . " ," . $requestData['length'];

$query = $conn->query($sql);
$data = [];
$counter = $requestData['start'] + 1;

while ($row = $query->fetch_assoc()) {
    $clean = function($v) { return (empty($v) || $v == NULL) ? "NA" : $v; };
    $data[] = [
        $counter++,
        $clean($row["GeneID"]),
        $clean($row["Identifier"]),
        $clean($row["Pathway_Name"]),
        $clean($row["Evidence_code"]),
        "Homo sapiens", // Always Homo sapiens
        $clean($row["URL"])
    ];
}

$totalData = $conn->query("SELECT COUNT(*) FROM Pathway_tb")->fetch_row()[0];
echo json_encode([
    "draw" => intval($requestData['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalData), // Simplified
    "data" => $data
]);