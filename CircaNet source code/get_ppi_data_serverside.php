<?php
require_once 'conn.php';

header('Content-Type: application/json');

$table = 'Prot_string_with_HGNC';
$protein = isset($_POST['protein']) ? trim($_POST['protein']) : '';
$min_score = isset($_POST['min_score']) ? (float)$_POST['min_score'] : 0.0;

// Datatable parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

// 1. Get exact total row count from database
$totalRecords = 0;
$countTotalQuery = "SELECT COUNT(*) as cnt FROM $table";
if ($res = $conn->query($countTotalQuery)) {
    $totalRecords = (int)$res->fetch_assoc()['cnt'];
}

// 2. CONSTRUCT FILTER CONDITIONS
$whereClauses = [];
$params = [];
$types = "";

if ($protein !== '') {
    $whereClauses[] = "(hgnc_symbol_A = ? OR hgnc_symbol_B = ? OR preferredName_A = ? OR preferredName_B = ?)";
    $params[] = $protein;
    $params[] = $protein;
    $params[] = $protein;
    $params[] = $protein;
    $types .= "ssss";
}

if ($min_score > 0) {
    $whereClauses[] = "score >= ?";
    $params[] = $min_score;
    $types .= "d";
}

if (!empty($searchValue)) {
    $whereClauses[] = "(hgnc_symbol_A LIKE ? OR hgnc_symbol_B LIKE ? OR preferredName_A LIKE ? OR preferredName_B LIKE ? OR stringId_A LIKE ? OR stringId_B LIKE ? OR input_uniprot_id LIKE ? OR proteinB_uniprot LIKE ?)";
    $like = "%" . $searchValue . "%";
    $params[] = $like; 
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssssssss";
}

$hasFilters = count($whereClauses) > 0;
$whereSQL = $hasFilters ? implode(" AND ", $whereClauses) : "1=1";

// 3. GET FILTERED COUNT
if (!$hasFilters) {
    $filteredCount = $totalRecords;
} else {
    $countQuery = "SELECT COUNT(*) as cnt FROM $table WHERE $whereSQL";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($types)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $filteredCount = (int)$countStmt->get_result()->fetch_assoc()['cnt'];
    $countStmt->close();
}

// 4. GET DATA
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 7;
$orderDir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'asc') ? 'asc' : 'desc';

$columnsMap = [
    1 => 'stringId_A',
    2 => 'stringId_B',
    3 => 'hgnc_symbol_A',
    4 => 'hgnc_symbol_B',
    5 => 'input_uniprot_id',
    6 => 'proteinB_uniprot',
    7 => 'score'
];
$orderByField = isset($columnsMap[$orderColumnIndex]) ? $columnsMap[$orderColumnIndex] : 'score';

$dataSQL = "SELECT stringId_A, stringId_B, hgnc_symbol_A, hgnc_symbol_B, input_uniprot_id, proteinB_uniprot, score 
            FROM $table 
            WHERE $whereSQL 
            ORDER BY $orderByField $orderDir 
            LIMIT ?, ?";
            
$dataStmt = $conn->prepare($dataSQL);

// Append pagination limits
$params[] = $start;
$params[] = $length;
$types .= "ii";

$dataStmt->bind_param($types, ...$params);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

$data = [];
$rowIndex = $start + 1;
while ($row = $dataResult->fetch_assoc()) {
    $data[] = [
        $rowIndex++,
        htmlspecialchars($row['stringId_A'] ?? ''),
        htmlspecialchars($row['stringId_B'] ?? ''),
        htmlspecialchars($row['hgnc_symbol_A'] ?? ''),
        htmlspecialchars($row['hgnc_symbol_B'] ?? ''),
        htmlspecialchars($row['input_uniprot_id'] ?? ''),
        htmlspecialchars($row['proteinB_uniprot'] ?? ''),
        $row['score'] !== null ? number_format((float)$row['score'], 3) : '0.000'
    ];
}
$dataStmt->close();

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredCount,
    "data" => $data
]);
$conn->close();