<?php
require_once 'conn.php';

header('Content-Type: application/json');

$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 25;

$filterGene = isset($_POST['gene']) ? trim($_POST['gene']) : '';
$filterVc = isset($_POST['vc']) ? trim($_POST['vc']) : '';

try {
    $whereClauses = [];
    $params = [];
    $types = "";

    if ($filterGene !== '') {
        $whereClauses[] = "gene = ?";
        $params[] = $filterGene;
        $types .= "s";
    }

    if ($filterVc !== '') {
        $whereClauses[] = "vc = ?";
        $params[] = $filterVc;
        $types .= "s";
    }

    $whereSQL = "";
    if (count($whereClauses) > 0) {
        $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
    }

    $limitPlusOne = $length + 1;

    // /*+ MAX_EXECUTION_TIME(5000) */ restricts execution to 5 seconds.
    // If a full sequential scan takes too long, MySQL aborts it to protect disk IO.
    $dataQueryStr = "SELECT /*+ MAX_EXECUTION_TIME(5000) */ gene, geneid, rsid, chrom, pos, ref, alt, vc 
                     FROM dbsnp" . $whereSQL . " 
                     LIMIT ?, ?";
                     
    $params[] = $start;
    $params[] = $limitPlusOne;
    $types .= "ii";

    $stmtData = $conn->prepare($dataQueryStr);
    if (!empty($types)) {
        $stmtData->bind_param($types, ...$params);
    }
    
    $stmtData->execute();
    $resData = $stmtData->get_result();

    $data = [];
    while ($row = $resData->fetch_assoc()) {
        $data[] = [
            $row['gene'],
            $row['geneid'],
            $row['rsid'],
            $row['chrom'],
            $row['pos'],
            $row['ref'],
            $row['alt'],
            $row['vc']
        ];
    }
    $stmtData->close();

    $rowsFetched = count($data);
    $hasMore = ($rowsFetched > $length);

    if ($hasMore) {
        array_pop($data);
        $recordsFiltered = $start + $length + 1; 
    } else {
        $recordsFiltered = $start + $rowsFetched;
    }
    
    $recordsTotal = $recordsFiltered;

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $data
    ]);

} catch (Exception $e) {
    error_log("Error in get_dbsnp_data.php: " . $e->getMessage());
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "The database query took too long to complete. Filters require indexing to run fast."
    ]);
}