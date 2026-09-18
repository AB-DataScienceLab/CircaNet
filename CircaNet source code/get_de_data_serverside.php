<?php
// get_de_data_serverside.php
header('Content-Type: application/json');
require_once 'conn.php';

$table = 'diseases_edges_tb';
$primaryKey = 'id';

// SQL columns mapping
$columns = [
    0 => 'e.id',
    1 => 'e.disease_A',
    2 => 'e.disease_B',
    3 => 'd1.diseaseName',
    4 => 'd2.diseaseName',
    5 => 'e.n_shared',
    6 => 'e.jaccard',
    7 => 'e.pval_adj',
    8 => 'e.composite_score'
];

$draw = intval($_POST['draw'] ?? 0);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$searchValue = $_POST['search']['value'] ?? '';

$whereClauses = [];
$params = [];
$param_types = '';

// Handle Search
if (!empty($searchValue)) {
    $search_term = "%" . $searchValue . "%";
    $whereClauses[] = "(e.disease_A LIKE ? OR e.disease_B LIKE ? OR d1.diseaseName LIKE ? OR d2.diseaseName LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ssss';
}

$where_sql = '';
if (count($whereClauses) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Order mapping
$order_col = intval($_POST['order'][0]['column'] ?? 0);
$order_dir = $_POST['order'][0]['dir'] ?? 'asc';
$order_dir = ($order_dir === 'desc') ? 'DESC' : 'ASC';
$sort_col = $columns[$order_col] ?? 'e.id';

// Fetch Total Unfiltered Count
$count_all = 0;
$totalResult = $conn->query("SELECT COUNT({$primaryKey}) FROM {$table}");
if ($totalResult) {
    $count_all = intval($totalResult->fetch_row()[0]);
}

// Fetch Filtered Count
$count_filtered = $count_all;
if (!empty($searchValue)) {
    // MODIFIED: Corrected the variable name typo in query allocation
    $count_query = "SELECT COUNT(e.id) FROM {$table} e
                    LEFT JOIN (SELECT MONDO, MIN(diseaseName) as diseaseName FROM Disease_tb WHERE MONDO IS NOT NULL AND MONDO != '' GROUP BY MONDO) d1 ON e.disease_A = d1.MONDO
                    LEFT JOIN (SELECT MONDO, MIN(diseaseName) as diseaseName FROM Disease_tb WHERE MONDO IS NOT NULL AND MONDO != '' GROUP BY MONDO) d2 ON e.disease_B = d2.MONDO
                    {$where_sql}";
    $stmt_count = $conn->prepare($count_query);
    if ($stmt_count) {
        $stmt_count->bind_param($param_types, ...$params);
        $stmt_count->execute();
        $count_filtered = intval($stmt_count->get_result()->fetch_row()[0]);
        $stmt_count->close();
    }
}

// Fetch Records with mapped disease names
$data = [];
$data_query = "SELECT e.id, e.disease_A, e.disease_B, e.n_shared, e.jaccard, e.pval_adj, e.composite_score, 
                      d1.diseaseName as name_A, d2.diseaseName as name_B 
              FROM {$table} e
              LEFT JOIN (
                  SELECT MONDO, MIN(diseaseName) as diseaseName 
                  FROM Disease_tb 
                  WHERE MONDO IS NOT NULL AND MONDO != ''
                  GROUP BY MONDO
              ) d1 ON e.disease_A = d1.MONDO
              LEFT JOIN (
                  SELECT MONDO, MIN(diseaseName) as diseaseName 
                  FROM Disease_tb 
                  WHERE MONDO IS NOT NULL AND MONDO != ''
                  GROUP BY MONDO
              ) d2 ON e.disease_B = d2.MONDO
              {$where_sql} 
              ORDER BY {$sort_col} {$order_dir} 
              LIMIT ?, ?";

$limit_params = array_merge($params, [$start, $length]);
$limit_param_types = $param_types . 'ii';

$stmt_data = $conn->prepare($data_query);
if ($stmt_data) {
    $stmt_data->bind_param($limit_param_types, ...$limit_params);
    $stmt_data->execute();
    $result = $stmt_data->get_result();
    while ($row = $result->fetch_assoc()) {
        $labelA = $row['name_A'] ?: $row['disease_A'];
        $labelB = $row['name_B'] ?: $row['disease_B'];
        $data[] = [
            intval($row['id']),
            htmlspecialchars($row['disease_A']),
            htmlspecialchars($row['disease_B']),
            htmlspecialchars($labelA),
            htmlspecialchars($labelB),
            intval($row['n_shared']),
            round(floatval($row['jaccard']), 4),
            sprintf("%.4e", floatval($row['pval_adj'])),
            round(floatval($row['composite_score']), 2)
        ];
    }
    $stmt_data->close();
}

$conn->close();

echo json_encode([
    "draw"            => $draw,
    "recordsTotal"    => $count_all,
    "recordsFiltered" => $count_filtered,
    "data"            => $data
]);