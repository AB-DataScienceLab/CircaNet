<?php
// get_compare_data.php
header('Content-Type: application/json');
require_once 'conn.php';

$type = $_GET['type'] ?? '';

if ($type === 'diseases') {
    $disA = $_GET['disA'] ?? '';
    $disB = $_GET['disB'] ?? '';
    
    $getInfo = function($mondo) use ($conn) {
        if (empty($mondo)) return null;
        
        $name_stmt = $conn->prepare("SELECT diseaseName FROM Disease_tb WHERE MONDO = ? LIMIT 1");
        $name_stmt->bind_param("s", $mondo);
        $name_stmt->execute();
        $name = $name_stmt->get_result()->fetch_assoc()['diseaseName'] ?? $mondo;
        $name_stmt->close();
        
        $cent_stmt = $conn->prepare("SELECT degree, weighted_degree, betweenness, closeness, pagerank FROM centrality_scores_tb WHERE disease = ? LIMIT 1");
        $cent_stmt->bind_param("s", $mondo);
        $cent_stmt->execute();
        $cent = $cent_stmt->get_result()->fetch_assoc() ?: ['degree' => 0, 'weighted_degree' => 0, 'betweenness' => 0, 'closeness' => 0, 'pagerank' => 0];
        $cent_stmt->close();
        
        $count_stmt = $conn->prepare("SELECT n_genes FROM disease_gene_count_tb WHERE diseaseId = ? LIMIT 1");
        $count_stmt->bind_param("s", $mondo);
        $count_stmt->execute();
        $genes_count = $count_stmt->get_result()->fetch_assoc()['n_genes'] ?? 0;
        $count_stmt->close();

        $list_stmt = $conn->prepare("SELECT DISTINCT Approved_symbol FROM Disease_tb WHERE MONDO = ? AND Approved_symbol != '' AND UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%'");
        $list_stmt->bind_param("s", $mondo);
        $list_stmt->execute();
        $res = $list_stmt->get_result();
        $genes_list = [];
        while ($r = $res->fetch_assoc()) { $genes_list[] = $r['Approved_symbol']; }
        $list_stmt->close();

        return [
            'mondo' => $mondo, 'name' => $name, 'centrality' => $cent, 'gene_count' => $genes_count, 'genes' => $genes_list
        ];
    };

    $dataA = $getInfo($disA);
    $dataB = $getInfo($disB);
    $overlap = ($dataA && $dataB) ? array_values(array_intersect($dataA['genes'], $dataB['genes'])) : [];
    
    echo json_encode(['diseaseA' => $dataA, 'diseaseB' => $dataB, 'overlap' => $overlap]);

} elseif ($type === 'genes') {
    $geneA = $_GET['geneA'] ?? '';
    $geneB = $_GET['geneB'] ?? '';
    
    $getGeneInfo = function($symbol) use ($conn) {
        if (empty($symbol)) return null;
        $stmt = $conn->prepare("SELECT DISTINCT MONDO, diseaseName FROM Disease_tb WHERE Approved_symbol = ? AND MONDO != '' AND UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%'");
        $stmt->bind_param("s", $symbol);
        $stmt->execute();
        $res = $stmt->get_result();
        $diseases = [];
        while($row = $res->fetch_assoc()) {
            $diseases[$row['MONDO']] = $row['diseaseName'];
        }
        $stmt->close();
        return ['symbol' => $symbol, 'diseases' => $diseases];
    };

    $infoA = $getGeneInfo($geneA);
    $infoB = $getGeneInfo($geneB);
    $overlap = [];
    if ($infoA && $infoB) {
        $overlapKeys = array_intersect(array_keys($infoA['diseases']), array_keys($infoB['diseases']));
        foreach ($overlapKeys as $key) {
            $overlap[] = ['mondo' => $key, 'name' => $infoA['diseases'][$key]];
        }
    }
    echo json_encode(['geneA' => $infoA, 'geneB' => $infoB, 'overlap' => $overlap]);

} elseif ($type === 'search_diseases') {
    $term = "%" . ($_GET['q'] ?? '') . "%";
    $stmt = $conn->prepare("SELECT DISTINCT MONDO, diseaseName FROM Disease_tb WHERE (diseaseName LIKE ? OR MONDO LIKE ?) AND MONDO != '' AND UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%' LIMIT 30");
    $stmt->bind_param("ss", $term, $term);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = ['id' => $row['MONDO'], 'text' => $row['diseaseName'] . " (" . $row['MONDO'] . ")"];
    }
    $stmt->close();
    echo json_encode($data);

} elseif ($type === 'search_genes') {
    $term = "%" . ($_GET['q'] ?? '') . "%";
    $stmt = $conn->prepare("SELECT DISTINCT Approved_symbol FROM Disease_tb WHERE Approved_symbol LIKE ? AND Approved_symbol != '' AND UPPER(Source) NOT LIKE '%DISGENET%' AND UPPER(Source) NOT LIKE '%CLINGEN%' LIMIT 30");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = ['id' => $row['Approved_symbol'], 'text' => $row['Approved_symbol']];
    }
    $stmt->close();
    echo json_encode($data);
}
$conn->close();