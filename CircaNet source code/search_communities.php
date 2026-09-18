<?php
require_once 'conn.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

$results = [];

try {
    $query = "SELECT DISTINCT CD_CommunityName AS id, CD_CommunityName AS text 
              FROM ppi_module_new 
              WHERE CD_CommunityName LIKE ? 
                AND CD_CommunityName IS NOT NULL 
                AND CD_CommunityName != '' 
                AND LOWER(TRIM(CD_CommunityName)) NOT IN ('(none)', 'na', 'n/a', 'none')
              LIMIT 20";

    if ($stmt = $conn->prepare($query)) {
        $like = "%" . $term . "%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Graceful error containment
}

echo json_encode($results);
$conn->close();