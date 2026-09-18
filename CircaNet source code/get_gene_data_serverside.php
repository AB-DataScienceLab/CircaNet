<?php
require_once 'conn.php';

header('Content-Type: application/json');

// Read DataTables parameters
$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

// Read filters from frontend dropdowns
$chromosome = isset($_POST['chromosome']) ? trim($_POST['chromosome']) : '';
$symbol     = isset($_POST['symbol']) ? trim($_POST['symbol']) : '';

// Column mapping for DataTables sorting (0 to 7)
$columns = [
    0 => 'hgnc_id',    // S.No
    1 => 'symbol',     // Symbol
    2 => 'hgnc_id',    // HGNC ID
    3 => 'entrez_id',  // NCBI Gene ID
    4 => 'symbol',     // View Structure
    5 => 'Chromosome', // Chr
    6 => 'source',     // Source (NEW)
    7 => 'symbol'      // Actions
];

$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
$orderDir         = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';
$orderColumn      = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'symbol';

// Base query filters
$whereClauses = ["1=1"];
$params = [];
$types = "";

// Chromosome Filter
if (!empty($chromosome)) {
    if ($chromosome === 'PAR' || $chromosome === 'X and Y' || $chromosome === 'X,Y') {
        $whereClauses[] = "(Chromosome = 'PAR' OR Chromosome = 'X and Y' OR Chromosome = 'X,Y')";
    } elseif ($chromosome === 'M' || $chromosome === 'mitochondria') {
        $whereClauses[] = "(Chromosome = 'mitochondria' OR Chromosome = 'MT' OR Chromosome = 'M' OR Chromosome = 'chrM' OR Chromosome = 'chrMT')";
    } else {
        $whereClauses[] = "Chromosome = ?";
        $params[] = $chromosome;
        $types .= "s";
    }
}

// Symbol Filter
if (!empty($symbol)) {
    $whereClauses[] = "symbol = ?";
    $params[] = $symbol;
    $types .= "s";
}

// Global Search Filter (includes symbol, hgnc_id, entrez_id, Chromosome, and source)
if (!empty($searchValue)) {
    $whereClauses[] = "(symbol LIKE ? OR hgnc_id LIKE ? OR entrez_id LIKE ? OR Chromosome LIKE ? OR source LIKE ?)";
    $searchParam = "%" . $searchValue . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sssss";
}

$whereSql = implode(" AND ", $whereClauses);

try {
    // 1. Get Total Unfiltered Records Count
    $totalRes = $conn->query("SELECT COUNT(*) AS total FROM gene_annotation");
    $totalRecords = $totalRes ? $totalRes->fetch_assoc()['total'] : 0;

    // 2. Get Filtered Records Count
    $filteredSql = "SELECT COUNT(*) AS total FROM gene_annotation WHERE $whereSql";
    $stmtFiltered = $conn->prepare($filteredSql);
    if (!empty($types)) {
        $stmtFiltered->bind_param($types, ...$params);
    }
    $stmtFiltered->execute();
    $filteredRes = $stmtFiltered->get_result();
    $recordsFiltered = $filteredRes->fetch_assoc()['total'];
    $stmtFiltered->close();

    // 3. Fetch Data Rows (Including `source`)
    $dataSql = "SELECT hgnc_id, symbol, name, entrez_id, ensembl_gene_id, uniprot_ids, locus_type, Chromosome, source 
                FROM gene_annotation 
                WHERE $whereSql 
                ORDER BY $orderColumn $orderDir 
                LIMIT ?, ?";
    
    $dataParams = $params;
    $dataTypes  = $types . "ii";
    $dataParams[] = $start;
    $dataParams[] = $length;

    $stmtData = $conn->prepare($dataSql);
    $stmtData->bind_param($dataTypes, ...$dataParams);
    $stmtData->execute();
    $dataRes = $stmtData->get_result();

    $data = [];
    while ($row = $dataRes->fetch_assoc()) {
        $data[] = $row;
    }
    $stmtData->close();

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => intval($totalRecords),
        "recordsFiltered" => intval($recordsFiltered),
        "data"            => $data
    ]);

} catch (Exception $e) {
    error_log("get_gene_data_serverside error: " . $e->getMessage());
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => 0,
        "recordsFiltered" => 0,
        "data"            => [],
        "error"           => $e->getMessage()
    ]);
}

$conn->close();
?>