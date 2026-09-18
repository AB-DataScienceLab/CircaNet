<?php
/**
 * Optimized Backend Data Engine for Cross-Tissue Expression Explorer
 * Tables: gene_wise_tb_v2, tissue_wise_tb_v2
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('memory_limit', '512M');
set_time_limit(60);
error_reporting(0);
ini_set('display_errors', 0);

require_once 'conn.php';

$db = null;
$isPDO = false;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $isPDO = true;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
    $isPDO = true;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
    $isPDO = false;
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection failed in conn.php']);
    exit;
}

// Get all 68 tissue expression columns from gene_wise_tb_v2
function getTissueColumns($db, $isPDO) {
    $allCols = [];
    if ($isPDO) {
        $stmt = $db->query("SHOW COLUMNS FROM gene_wise_tb_v2");
        $allCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $res = $db->query("SHOW COLUMNS FROM gene_wise_tb_v2");
        while ($r = $res->fetch_assoc()) {
            $allCols[] = $r['Field'];
        }
    }
    $exclude = ['id', 'HGNC_ID', 'Gene_ID'];
    return array_values(array_diff($allCols, $exclude));
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {

    // 1. AUTOCOMPLETE
    case 'suggest_genes':
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($q) < 1) {
            echo json_encode([]);
            exit;
        }

        $term = "%$q%";
        $sql = "
            SELECT Gene_ID, Gene_Name, Approved_symbol 
            FROM tissue_wise_tb_v2 
            WHERE Gene_ID LIKE ? OR Gene_Name LIKE ? OR Approved_symbol LIKE ?
            LIMIT 15
        ";

        $results = [];
        if ($isPDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$term, $term, $term]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sss', $term, $term, $term);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $results[] = $row;
            }
        }
        echo json_encode($results);
        exit;

    // 2. DATA TABLE CATALOG
    case 'get_genes':
        $sql = "
            SELECT 
                Gene_ID, Gene_Name, Approved_symbol, Approved_name, 
                Chromosome, Top_Tissue, Max_TPM, Tau, Gini, Specificity 
            FROM tissue_wise_tb_v2 
            ORDER BY Max_TPM DESC
        ";

        $data = [];
        if ($isPDO) {
            $stmt = $db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $res = $db->query($sql);
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['success' => true, 'total' => count($data), 'data' => $data]);
        exit;

    // 3. SINGLE GENE DETAILS (Bar, Radar & Stats Grid)
    case 'get_gene_details':
        $geneId = isset($_GET['gene_id']) ? trim($_GET['gene_id']) : '';
        if (empty($geneId)) {
            echo json_encode(['success' => false, 'message' => 'No gene identifier provided.']);
            exit;
        }

        // Strict JOIN on Gene_ID avoids Cartesian explosions
        $sql = "
            SELECT 
                t.Gene_ID, t.Gene_Name, t.Approved_symbol, t.Approved_name, 
                t.Top_Tissue, t.Max_TPM, t.Tau, t.TSI, t.Gini, 
                t.Z_max, t.SPM_max, t.Specificity, t.HGNC_ID, t.Chromosome, t.NCBI_Gene_ID,
                g.*
            FROM tissue_wise_tb_v2 t
            LEFT JOIN gene_wise_tb_v2 g ON t.Gene_ID = g.Gene_ID
            WHERE t.Gene_ID = ? OR t.Gene_Name = ? OR t.Approved_symbol = ?
            LIMIT 1
        ";

        $row = null;
        if ($isPDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$geneId, $geneId, $geneId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sss', $geneId, $geneId, $geneId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
        }

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Gene not found in database.']);
            exit;
        }

        $tissueCols = getTissueColumns($db, $isPDO);
        $tissuesData = [];
        foreach ($tissueCols as $col) {
            $val = (isset($row[$col]) && is_numeric($row[$col])) ? (float)$row[$col] : 0.0;
            $tissuesData[] = [
                'field' => $col,
                'label' => str_replace('_', ' ', $col),
                'tpm'   => round($val, 3)
            ];
        }

        echo json_encode([
            'success'         => true,
            'gene_id'         => $row['Gene_ID'],
            'gene_name'       => $row['Gene_Name'] ?: ($row['Approved_symbol'] ?: $row['Gene_ID']),
            'approved_symbol' => $row['Approved_symbol'] ?: '',
            'approved_name'   => $row['Approved_name'] ?: '-',
            'hgnc_id'         => $row['HGNC_ID'] ?: '-',
            'chromosome'      => $row['Chromosome'] ?: '-',
            'ncbi_gene_id'    => $row['NCBI_Gene_ID'] ?: '-',
            'top_tissue'      => $row['Top_Tissue'] ?: '',
            'max_tpm'         => isset($row['Max_TPM']) ? (float)$row['Max_TPM'] : 0.0,
            'tau'             => isset($row['Tau']) ? (float)$row['Tau'] : 0.0,
            'tsi'             => isset($row['TSI']) ? (float)$row['TSI'] : 0.0,
            'gini'            => isset($row['Gini']) ? (float)$row['Gini'] : 0.0,
            'z_max'           => isset($row['Z_max']) ? (float)$row['Z_max'] : 0.0,
            'spm_max'         => isset($row['SPM_max']) ? (float)$row['SPM_max'] : 0.0,
            'specificity'     => $row['Specificity'] ?: 'Intermediate',
            'tissues'         => $tissuesData
        ]);
        exit;

    // 4. MULTI-GENE HEATMAP MATRIX
    case 'get_heatmap':
        $genesInput = isset($_GET['genes']) ? trim($_GET['genes']) : '';
        $geneList = preg_split('/[\s,]+/', $genesInput, -1, PREG_SPLIT_NO_EMPTY);
        $geneList = array_unique(array_slice($geneList, 0, 30));

        if (empty($geneList)) {
            echo json_encode(['success' => false, 'matrix' => [], 'tissues' => []]);
            exit;
        }

        $tissueCols = getTissueColumns($db, $isPDO);
        $placeholders = implode(',', array_fill(0, count($geneList), '?'));

        $sql = "
            SELECT 
                t.Gene_ID, t.Gene_Name, t.Approved_symbol,
                g.*
            FROM tissue_wise_tb_v2 t
            LEFT JOIN gene_wise_tb_v2 g ON t.Gene_ID = g.Gene_ID
            WHERE 
                t.Gene_ID IN ($placeholders)
                OR t.Gene_Name IN ($placeholders)
                OR t.Approved_symbol IN ($placeholders)
        ";

        $bindings = array_merge($geneList, $geneList, $geneList);
        $rows = [];

        if ($isPDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare($sql);
            $types = str_repeat('s', count($bindings));
            $stmt->bind_param($types, ...$bindings);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        $matrix = [];
        foreach ($rows as $r) {
            $name = $r['Approved_symbol'] ?: ($r['Gene_Name'] ?: $r['Gene_ID']);
            $rawVals = [];
            foreach ($tissueCols as $col) {
                $val = (isset($r[$col]) && is_numeric($r[$col])) ? (float)$r[$col] : 0.0;
                $rawVals[] = round($val, 3);
            }
            $matrix[] = [
                'gene_id'   => $r['Gene_ID'],
                'gene_name' => $name,
                'raw'       => $rawVals
            ];
        }

        echo json_encode([
            'success' => true,
            'tissues' => $tissueCols,
            'matrix'  => $matrix
        ]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit;
}