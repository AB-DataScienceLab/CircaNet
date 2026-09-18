<?php
// get_disease_data_serverside.php
header('Content-Type: application/json');
require_once 'conn.php';

$table = 'Disease_tb';
$primaryKey = 'id';
$columns = [
    ['db' => 'id', 'dt' => 0],
    ['db' => 'HGNC_ID', 'dt' => 1],
    ['db' => 'Approved_symbol', 'dt' => 2],
    ['db' => 'diseaseName', 'dt' => 3],
    ['db' => 'MONDO', 'dt' => 4],
    ['db' => 'Source', 'dt' => 5],
];

// Request Parameters from DataTables
$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 25);
$searchValue = $_POST['search']['value'] ?? '';

// Custom Filters
$symbolFilter = $_POST['symbol'] ?? '';
$diseaseFilter = $_POST['disease'] ?? '';
$sourceFilter = $_POST['source'] ?? '';

// Build Query
$whereClauses = [];
$params = [];
$param_types = '';

// BASE CHANGE: Always exclude "Disgenet" and "ClinGen" records from query results
$whereClauses[] = "UPPER(Source) NOT LIKE '%DISGENET%'";
$whereClauses[] = "UPPER(Source) NOT LIKE '%CLINGEN%'";

// Symbol filter - case-insensitive
if (!empty($symbolFilter)) {
    $whereClauses[] = "UPPER(TRIM(Approved_symbol)) = UPPER(?)";
    $params[] = $symbolFilter;
    $param_types .= 's';
}

// Disease filter - case-insensitive  
if (!empty($diseaseFilter)) {
    $whereClauses[] = "UPPER(TRIM(diseaseName)) = UPPER(?)";
    $params[] = $diseaseFilter;
    $param_types .= 's';
}

// Switched from exact match to a "contains" search (LIKE) for the source filter.
if (!empty($sourceFilter)) {
    $whereClauses[] = "UPPER(Source) LIKE UPPER(?)"; 
    $params[] = '%' . $sourceFilter . '%'; 
    $param_types .= 's';
}

// Add global search
if (!empty($searchValue)) {
    $search_term = "%" . $searchValue . "%";
    $search_clauses = [];
    foreach ($columns as $col) {
        if ($col['db'] != 'id') {
           $search_clauses[] = "`" . $col['db'] . "` LIKE ?";
           $params[] = $search_term;
           $param_types .= 's';
        }
    }
    if (!empty($search_clauses)) {
        $whereClauses[] = "(" . implode(' OR ', $search_clauses) . ")";
    }
}

$where_sql = '';
if (count($whereClauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// MODIFIED: Exclude Disgenet and ClinGen from Total Records count so pagination behaves correctly
$totalRecordsResult = $conn->query("SELECT COUNT({$primaryKey}) as count FROM {$table} WHERE UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%'");
$recordsTotal = $totalRecordsResult->fetch_assoc()['count'];

// Get Filtered Records Count
$count_query = "SELECT COUNT({$primaryKey}) as count FROM {$table} {$where_sql}";
$stmt_count = $conn->prepare($count_query);
if ($param_types) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$recordsFiltered = $stmt_count->get_result()->fetch_assoc()['count'];
$stmt_count->close();

// Fetch Data
$data = [];
$data_query = "SELECT id, HGNC_ID, Approved_symbol, diseaseName, MONDO, Source FROM {$table} {$where_sql} ORDER BY Approved_symbol ASC LIMIT ?, ?";
$limit_params = $params;
$limit_param_types = $param_types . 'ii';
$limit_params[] = $start;
$limit_params[] = $length;

$stmt_data = $conn->prepare($data_query);
if ($limit_param_types) {
    $stmt_data->bind_param($limit_param_types, ...$limit_params);
}
$stmt_data->execute();
$result = $stmt_data->get_result();

$serial_number = $start + 1;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            $serial_number++,
            htmlspecialchars($row["HGNC_ID"]),
            htmlspecialchars($row["Approved_symbol"]),
            htmlspecialchars($row["diseaseName"]),
            htmlspecialchars($row["MONDO"]),
            htmlspecialchars($row["Source"])
        ];
    }
}

$stmt_data->close();
$conn->close();

// Final JSON Output for DataTables
echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data"            => $data
]);
?>