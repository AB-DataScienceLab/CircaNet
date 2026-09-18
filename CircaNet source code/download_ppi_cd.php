<?php
require_once 'conn.php';

ob_clean();

header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename=community_detection_dataset.tsv');

$output = fopen('php://output', 'w');

$headers = [
    'Community Name',
    'Annotated Members Size',
    'Annotated Members',
    'Member List',
    'Annotated Members Overlap',
    'Member List Size'
];
fwrite($output, implode("\t", $headers) . "\n");

$protein = isset($_GET['protein']) ? trim($_GET['protein']) : '';
$community = isset($_GET['community']) ? trim($_GET['community']) : '';

$whereClauses = [
    "CD_CommunityName IS NOT NULL AND CD_CommunityName != '' AND LOWER(TRIM(CD_CommunityName)) NOT IN ('(none)', 'na', 'n/a', 'none')"
];
$params = [];
$types = "";

if ($community !== '') {
    $whereClauses[] = "CD_CommunityName = ?";
    $params[] = $community;
    $types .= "s";
}

if ($protein !== '') {
    $whereClauses[] = "(CD_AnnotatedMembers LIKE ? OR CD_MemberList LIKE ? OR CD_NonAnnotatedMembers LIKE ?)";
    $proteinLike = "%" . $protein . "%";
    $params[] = $proteinLike;
    $params[] = $proteinLike;
    $params[] = $proteinLike;
    $types .= "sss";
}

$whereSQL = implode(" AND ", $whereClauses);

$query = "SELECT CD_CommunityName, CD_AnnotatedMembers_Size, CD_AnnotatedMembers, CD_MemberList, CD_AnnotatedMembers_Overlap, CD_MemberList_Size 
          FROM ppi_module_new 
          WHERE $whereSQL 
          ORDER BY CD_CommunityName ASC";

if ($stmt = $conn->prepare($query)) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $cleanRow = [
            str_replace(["\r", "\n", "\t"], ' ', $row['CD_CommunityName'] ?? ''),
            (int)($row['CD_AnnotatedMembers_Size'] ?? 0),
            str_replace(["\r", "\n", "\t"], ' ', $row['CD_AnnotatedMembers'] ?? ''),
            str_replace(["\r", "\n", "\t"], ' ', $row['CD_MemberList'] ?? ''),
            $row['CD_AnnotatedMembers_Overlap'] !== null ? number_format((float)$row['CD_AnnotatedMembers_Overlap'], 4) : '0.0000',
            (int)($row['CD_MemberList_Size'] ?? 0)
        ];
        
        fwrite($output, implode("\t", $cleanRow) . "\n");
    }
    $stmt->close();
}

fclose($output);
$conn->close();
exit();