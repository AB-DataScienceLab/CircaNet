<?php
require_once 'conn.php';

header('Content-Type: application/json');

$protein = isset($_POST['protein']) ? trim($_POST['protein']) : '';
$community = isset($_POST['community']) ? trim($_POST['community']) : '';

// Datatable parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

$table = 'ppi_module_new';

// Base condition to completely exclude (none), NA, none, N/A, and empty community names
$baseCondition = "CD_CommunityName IS NOT NULL AND CD_CommunityName != '' AND LOWER(TRIM(CD_CommunityName)) NOT IN ('(none)', 'na', 'n/a', 'none')";

// 1. Get total valid records in the table
$totalRecords = 0;
$totalQuery = "SELECT COUNT(*) as cnt FROM $table WHERE $baseCondition";
if ($result = $conn->query($totalQuery)) {
    $row = $result->fetch_assoc();
    $totalRecords = (int)$row['cnt'];
}

// 2. Construct filter conditions
$whereClauses = [$baseCondition];
$params = [];
$types = "";

// Filter by selected Community Name
if ($community !== '') {
    $whereClauses[] = "CD_CommunityName = ?";
    $params[] = $community;
    $types .= "s";
}

// Filter communities by selected protein
if ($protein !== '') {
    $whereClauses[] = "(CD_AnnotatedMembers LIKE ? OR CD_MemberList LIKE ? OR CD_NonAnnotatedMembers LIKE ?)";
    $proteinLike = "%" . $protein . "%";
    $params[] = $proteinLike;
    $params[] = $proteinLike;
    $params[] = $proteinLike;
    $types .= "sss";
}

// Global table search bar
if ($searchValue !== '') {
    $whereClauses[] = "(CD_CommunityName LIKE ? OR CD_AnnotatedMembers LIKE ? OR CD_MemberList LIKE ?)";
    $searchLike = "%" . $searchValue . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

$whereSQL = implode(" AND ", $whereClauses);

// 3. Get filtered records count
$countQuery = "SELECT COUNT(*) as cnt FROM $table WHERE $whereSQL";
$countStmt = $conn->prepare($countQuery);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$filteredRecords = (int)$countStmt->get_result()->fetch_assoc()['cnt'];
$countStmt->close();

// 4. Get paginated data
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
$orderDir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc') ? 'desc' : 'asc';

$columnsMap = [
    1 => 'CD_CommunityName',
    2 => 'CD_AnnotatedMembers_Size',
    3 => 'CD_AnnotatedMembers',
    4 => 'CD_MemberList',
    5 => 'CD_AnnotatedMembers_Overlap',
    6 => 'CD_MemberList_Size'
];
$orderByField = isset($columnsMap[$orderColumnIndex]) ? $columnsMap[$orderColumnIndex] : 'CD_CommunityName';

$dataSQL = "SELECT CD_CommunityName, CD_AnnotatedMembers_Size, CD_AnnotatedMembers, CD_MemberList, CD_AnnotatedMembers_Overlap, CD_MemberList_Size 
            FROM $table 
            WHERE $whereSQL 
            ORDER BY $orderByField $orderDir 
            LIMIT ?, ?";

$dataStmt = $conn->prepare($dataSQL);
$data = [];

if ($dataStmt) {
    $params[] = $start;
    $params[] = $length;
    $types .= "ii";

    $dataStmt->bind_param($types, ...$params);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();

    $rowIndex = $start + 1;
    while ($row = $dataResult->fetch_assoc()) {
        $data[] = [
            $rowIndex++,
            htmlspecialchars($row['CD_CommunityName'] ?? ''),
            $row['CD_AnnotatedMembers_Size'] !== null ? (int)$row['CD_AnnotatedMembers_Size'] : 0,
            htmlspecialchars($row['CD_AnnotatedMembers'] ?? ''),
            htmlspecialchars($row['CD_MemberList'] ?? ''),
            $row['CD_AnnotatedMembers_Overlap'] !== null ? number_format((float)$row['CD_AnnotatedMembers_Overlap'], 4) : '0.0000',
            $row['CD_MemberList_Size'] !== null ? (int)$row['CD_MemberList_Size'] : 0
        ];
    }
    $dataStmt->close();
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $filteredRecords,
    "data" => $data
]);

$conn->close();