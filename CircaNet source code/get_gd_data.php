<?php
// get_gd_data.php
header('Content-Type: application/json');
require_once 'conn.php';

$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';

$where = "WHERE Source != 'Disgenet'";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (Approved_symbol LIKE ? OR diseaseName LIKE ? OR MONDO LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $types .= "sss";
}

// 1. Get total records count
$count_all = 0;
$count_all_query = "SELECT COUNT(*) FROM Disease_tb WHERE Source != 'Disgenet'";
if ($res = $conn->query($count_all_query)) {
    $count_all = intval($res->fetch_row()[0]);
}

// 2. Get filtered records count
$count_filtered = $count_all;
if (!empty($search)) {
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM Disease_tb $where");
    if ($stmt_count) {
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $count_filtered = intval($stmt_count->get_result()->fetch_row()[0]);
        $stmt_count->close();
    }
}

// 3. Fetch paginated data
$data = [];
$data_query = "SELECT HGNC_ID, Approved_symbol, diseaseName, MONDO, Source FROM Disease_tb $where LIMIT ?, ?";
$stmt = $conn->prepare($data_query);

if ($stmt) {
    $bind_types = $types . "ii";
    $bind_params = array_merge($params, [$start, $length]);
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sr = $start + 1;
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            $sr++,
            htmlspecialchars($row['HGNC_ID']),
            htmlspecialchars($row['Approved_symbol']),
            htmlspecialchars($row['diseaseName']),
            htmlspecialchars($row['MONDO']),
            htmlspecialchars($row['Source'])
        ];
    }
    $stmt->close();
}

$conn->close();

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $count_all,
    "recordsFiltered" => $count_filtered,
    "data" => $data
]);