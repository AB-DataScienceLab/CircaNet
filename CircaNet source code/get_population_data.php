<?php
// get_explorer_data.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep output clean of raw PHP notices
header('Content-Type: application/json');

// Include your database connection credentials
require_once 'conn.php';

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection error: " . (isset($conn) ? $conn->connect_error : "Connection object not defined.")
    ]);
    exit;
}

// Enforce UTF-8 collation safety
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
        if (!$stmt) {
            throw new Exception("Preparation of suggestion statement failed: " . $conn->error);
        }
        
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

        // 1. Calculate average allele frequencies on-the-fly for the indexed gene
        $aggregateSql = "SELECT 
            AVG(gnomAD_AF_XX) as avg_XX,
            AVG(gnomAD_AF_XY) as avg_XY,
            AVG(gnomAD_AF_raw) as avg_raw,
            AVG(gnomAD_AF_afr) as afr,
            AVG(gnomAD_AF_afr_XX) as afr_XX,
            AVG(gnomAD_AF_afr_XY) as afr_XY,
            AVG(gnomAD_AF_amr) as amr,
            AVG(gnomAD_AF_amr_XX) as amr_XX,
            AVG(gnomAD_AF_amr_XY) as amr_XY,
            AVG(gnomAD_AF_eas) as eas,
            AVG(gnomAD_AF_eas_XX) as eas_XX,
            AVG(gnomAD_AF_eas_XY) as eas_XY,
            AVG(gnomAD_AF_fin) as fin,
            AVG(gnomAD_AF_fin_XX) as fin_XX,
            AVG(gnomAD_AF_fin_XY) as fin_XY,
            AVG(gnomAD_AF_asj) as asj,
            AVG(gnomAD_AF_asj_XX) as asj_XX,
            AVG(gnomAD_AF_asj_XY) as asj_XY,
            AVG(gnomAD_AF_nfe) as nfe,
            AVG(gnomAD_AF_nfe_XX) as nfe_XX,
            AVG(gnomAD_AF_nfe_XY) as nfe_XY,
            AVG(gnomAD_AF_remaining) as remaining,
            AVG(gnomAD_AF_remaining_XX) as remaining_XX,
            AVG(gnomAD_AF_remaining_XY) as remaining_XY
        FROM variant_tb 
        WHERE SYMBOL = ?";

        $stmt1 = $conn->prepare($aggregateSql);
        if (!$stmt1) {
            throw new Exception("Preparation of analytical statement failed: " . $conn->error);
        }
        
        $stmt1->bind_param("s", $gene);
        $stmt1->execute();
        $aggregates = $stmt1->get_result()->fetch_assoc();

        if (empty($aggregates) || $aggregates['avg_raw'] === null) {
            echo json_encode([
                "success" => false, 
                "message" => "No variant records located for focal gene symbol: " . htmlspecialchars($gene)
            ]);
            exit;
        }

        // 2. Fetch specific top variants for detailed evidence representation
        $variantSql = "SELECT Variant_ID, CLNSIG, gnomAD_AF_raw 
                       FROM variant_tb 
                       WHERE SYMBOL = ? 
                       ORDER BY gnomAD_AF_raw DESC 
                       LIMIT 15";
        
        $stmt2 = $conn->prepare($variantSql);
        if (!$stmt2) {
            throw new Exception("Preparation of variant evidence statement failed: " . $conn->error);
        }
        
        $stmt2->bind_param("s", $gene);
        $stmt2->execute();
        $vResult = $stmt2->get_result();

        $variants = [];
        while ($vRow = $vResult->fetch_assoc()) {
            $variants[] = [
                "id" => $vRow['Variant_ID'] ? "rs" . $vRow['Variant_ID'] : "N/A",
                "sig" => $vRow['CLNSIG'] ? str_replace('_', ' ', $vRow['CLNSIG']) : "Unknown",
                "af" => (float)$vRow['gnomAD_AF_raw']
            ];
        }

        // 3. Construct response mapping
        echo json_encode([
            "success" => true,
            "gene" => $gene,
            "overall" => [
                "XX" => round((float)$aggregates['avg_XX'], 6),
                "XY" => round((float)$aggregates['avg_XY'], 6)
            ],
            "populations" => [
                "labels" => ["AFR", "AMR", "EAS", "FIN", "ASJ", "NFE", "REM"],
                "global_af" => [
                    round((float)$aggregates['afr'], 6),
                    round((float)$aggregates['amr'], 6),
                    round((float)$aggregates['eas'], 6),
                    round((float)$aggregates['fin'], 6),
                    round((float)$aggregates['asj'], 6),
                    round((float)$aggregates['nfe'], 6),
                    round((float)$aggregates['remaining'], 6)
                ],
                "XX_af" => [
                    round((float)$aggregates['afr_XX'], 6),
                    round((float)$aggregates['amr_XX'], 6),
                    round((float)$aggregates['eas_XX'], 6),
                    round((float)$aggregates['fin_XX'], 6),
                    round((float)$aggregates['asj_XX'], 6),
                    round((float)$aggregates['nfe_XX'], 6),
                    round((float)$aggregates['remaining_XX'], 6)
                ],
                "XY_af" => [
                    round((float)$aggregates['afr_XY'], 6),
                    round((float)$aggregates['amr_XY'], 6),
                    round((float)$aggregates['eas_XY'], 6),
                    round((float)$aggregates['fin_XY'], 6),
                    round((float)$aggregates['asj_XY'], 6),
                    round((float)$aggregates['nfe_XY'], 6),
                    round((float)$aggregates['remaining_XY'], 6)
                ]
            ],
            "variants" => $variants
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
    exit;
}