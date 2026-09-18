<?php
// fetch_tissue_data.php
require_once 'conn.php';
header('Content-Type: application/json');

try {
    $draw = intval($_GET['draw'] ?? 1);
    $start = intval($_GET['start'] ?? 0);
    $length = intval($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';

    // Index 15 (Chromosome) has been removed from this mapping array
    $columns = [
        0 => 'S.No', 
        1 => 'Approved_symbol',
        2 => 'Approved_name',
        3 => 'Gene_ID',
        4 => 'HGNC_ID',
        5 => 'NCBI_Gene_ID',
        6 => 'Top_Tissue',
        7 => 'Max_TPM',
        8 => 'Specificity',
        9 => 'Tau',
        10 => 'TSI',
        11 => 'Gini',
        12 => 'Z_max',
        13 => 'HPA',
        14 => 'SPM_max'
    ];

    $orderColumnIndex = intval($_GET['order'][0]['column'] ?? 7);
    $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
    $orderColumn = $columns[$orderColumnIndex] ?? 'Max_TPM';
    if (!in_array(strtolower($orderDir), ['asc', 'desc'])) {
        $orderDir = 'desc';
    }

    $totalResult = $conn->query("SELECT COUNT(*) AS total FROM tissue_wise_tb");
    $totalRecords = $totalResult ? (int)$totalResult->fetch_assoc()['total'] : 0;

    $searchQuery = " WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($searchValue)) {
        // Removed Chromosome search binding to match the current interface structure
        $searchQuery .= " AND (
            Approved_symbol LIKE ? OR 
            Gene_Name LIKE ? OR 
            Approved_name LIKE ? OR 
            Gene_ID LIKE ? OR 
            Top_Tissue LIKE ? OR 
            Specificity LIKE ?
        )";
        $likeValue = "%" . $searchValue . "%";
        for ($i = 0; $i < 6; $i++) {
            $params[] = $likeValue;
            $types .= "s";
        }
    }

    $filteredQuery = "SELECT COUNT(*) AS total FROM tissue_wise_tb" . $searchQuery;
    if (!empty($searchValue)) {
        $stmt = $conn->prepare($filteredQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $recordsFiltered = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $recordsFiltered = $totalRecords;
    }

    $dataQuery = "SELECT * FROM tissue_wise_tb" . $searchQuery . " ORDER BY " . $orderColumn . " " . $orderDir . " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";

    $stmt = $conn->prepare($dataQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>