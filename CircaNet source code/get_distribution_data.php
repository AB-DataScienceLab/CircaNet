<?php
// get_distribution_data.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'conn.php';

// Enforce database-level UTF-8 encoding
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($action === 'suggest') {
        $queryTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($queryTerm) < 1) {
            echo json_encode([]);
            exit;
        }

        $escTerm = $queryTerm . '%';
        $stmt = $conn->prepare("SELECT DISTINCT SYMBOL FROM variant_tb WHERE SYMBOL LIKE ? ORDER BY SYMBOL ASC LIMIT 10");
        $stmt->bind_param("s", $escTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        $suggestions = [];
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row['SYMBOL'];
        }
        echo json_encode($suggestions);
        exit;
    } 
    
    elseif ($action === 'analyze') {
        $gene = isset($_GET['gene']) ? trim($_GET['gene']) : '';
        if (empty($gene)) {
            throw new Exception("No gene symbol specified.");
        }

        // 1. Unified aggregate query to load top KPI cards instantly
        $aggregateSql = "SELECT 
            COUNT(*) as total_variants,
            SUM(CASE WHEN IMPACT = 'MODERATE' OR Consequence LIKE '%missense%' THEN 1 ELSE 0 END) as missense_count,
            SUM(CASE WHEN CLNSIG LIKE '%Pathogenic%' THEN 1 ELSE 0 END) as pathogenic_count,
            SUM(CASE WHEN CLNSIG LIKE '%Uncertain%' OR CLNSIG LIKE '%VUS%' THEN 1 ELSE 0 END) as vus_count,
            SUM(CASE WHEN CLNSIG LIKE '%Benign%' THEN 1 ELSE 0 END) as benign_count
        FROM variant_tb 
        WHERE SYMBOL = ?";

        $stmt1 = $conn->prepare($aggregateSql);
        $stmt1->bind_param("s", $gene);
        $stmt1->execute();
        $totals = $stmt1->get_result()->fetch_assoc();

        if (empty($totals) || (int)$totals['total_variants'] === 0) {
            echo json_encode([
                "success" => false, 
                "message" => "No variant records located for focal gene symbol: " . htmlspecialchars($gene)
            ]);
            exit;
        }

        // 2. Fetch specific VEP Consequences frequency counts for Chart 1
        $consequenceSql = "SELECT Consequence, COUNT(*) as cnt 
                           FROM variant_tb 
                           WHERE SYMBOL = ? AND Consequence IS NOT NULL AND Consequence != ''
                           GROUP BY Consequence 
                           ORDER BY cnt DESC 
                           LIMIT 5";
        $stmt2 = $conn->prepare($consequenceSql);
        $stmt2->bind_param("s", $gene);
        $stmt2->execute();
        $cResult = $stmt2->get_result();

        $consequences = ["labels" => [], "data" => []];
        while ($cRow = $cResult->fetch_assoc()) {
            $consequences['labels'][] = str_replace('_', ' ', $cRow['Consequence']);
            $consequences['data'][] = (int)$cRow['cnt'];
        }

        // 3. Fetch ClinVar Significance counts for Chart 2
        $clinvarSql = "SELECT CLNSIG, COUNT(*) as cnt 
                       FROM variant_tb 
                       WHERE SYMBOL = ? AND CLNSIG IS NOT NULL AND CLNSIG != ''
                       GROUP BY CLNSIG 
                       ORDER BY cnt DESC 
                       LIMIT 5";
        $stmt3 = $conn->prepare($clinvarSql);
        $stmt3->bind_param("s", $gene);
        $stmt3->execute();
        $clResult = $stmt3->get_result();

        $clinvars = ["labels" => [], "data" => []];
        while ($clRow = $clResult->fetch_assoc()) {
            $clinvars['labels'][] = str_replace('_', ' ', $clRow['CLNSIG']);
            $clinvars['data'][] = (int)$clRow['cnt'];
        }

        // 4. Fetch the raw annotation records for the consolidated grid table
        $tableSql = "SELECT Variant_ID, Consequence, CLNSIG, IMPACT, Existing_variation 
                     FROM variant_tb 
                     WHERE SYMBOL = ? 
                     LIMIT 15";
        $stmt4 = $conn->prepare($tableSql);
        $stmt4->bind_param("s", $gene);
        $stmt4->execute();
        $tResult = $stmt4->get_result();

        $tableData = [];
        while ($tRow = $tResult->fetch_assoc()) {
            $tableData[] = [
                "id" => $tRow['Variant_ID'] ? "rs" . $tRow['Variant_ID'] : "N/A",
                "consequence" => $tRow['Consequence'] ? str_replace('_', ' ', $tRow['Consequence']) : "Unknown",
                "clnsig" => $tRow['CLNSIG'] ? str_replace('_', ' ', $tRow['CLNSIG']) : "Unknown",
                "impact" => $tRow['IMPACT'] ? $tRow['IMPACT'] : "Modifier",
                "variation" => $tRow['Existing_variation'] ? $tRow['Existing_variation'] : "N/A"
            ];
        }

        echo json_encode([
            "success" => true,
            "gene" => $gene,
            "totals" => [
                "total" => (int)$totals['total_variants'],
                "missense" => (int)$totals['missense_count'],
                "pathogenic" => (int)$totals['pathogenic_count'],
                "vus" => (int)$totals['vus_count'],
                "benign" => (int)$totals['benign_count']
            ],
            "consequences" => $consequences,
            "clinvar" => $clinvars,
            "table" => $tableData
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database Execution Exception: " . $e->getMessage()
    ]);
    exit;
}