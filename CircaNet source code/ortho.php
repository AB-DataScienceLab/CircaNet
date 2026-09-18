

<?php
/**
 * Orthology Explorer - Integrated Database Powered Multi-Species Gene Viewer
 * Direct Gene Symbol Search Filtering, Unified Chart Colors & 3D Dark/Light Background Toggle
 */

// Buffer output to ensure clean JSON responses without stray PHP warnings
ob_start();

// Ensure UTF-8 header is sent to the browser
header('Content-Type: text/html; charset=utf-8');

// 1. Establish Database Connections
if (file_exists('conn.php')) {
    include 'conn.php';
} else {
    die("Error: The connection file 'conn.php' could not be found.");
}

// Include PostgreSQL connection
if (file_exists('db_connect.php')) {
    include_once 'db_connect.php';
}

// 2. Scientific to Common Name Mapping Dictionary
$TAX_NAME_MAP = [
    'Caenorhabditis elegans'   => 'C. elegans',
    'Danio rerio'              => 'Zebrafish',
    'Drosophila melanogaster'  => 'Fruit Fly',
    'Drosophila'               => 'Fruit Fly',
    'Gallus gallus'            => 'Chicken',
    'Macaca fascicularis'      => 'Cynomolgus',
    'Macaca mulatta'           => 'Macaque',
    'Mus musculus'             => 'Mouse',
    'Oryctolagus cuniculus'    => 'Rabbit',
    'Pan troglodytes'          => 'Chimpanzee',
    'Papio anubis'             => 'Baboon',
    'Papio'                    => 'Baboon',
    'Rattus norvegicus'        => 'Rat',
    'Homo sapiens'             => 'Human'
];

function getCommonName($taxname) {
    global $TAX_NAME_MAP;
    $trimmed = trim((string)$taxname);
    foreach ($TAX_NAME_MAP as $sci => $common) {
        if (strcasecmp($trimmed, $sci) === 0) {
            return $common;
        }
    }
    return !empty($trimmed) ? $trimmed : 'N/A';
}

// 2b. MySQL / MariaDB Database Connection Wrapper/Adapter Layer
$dbInstance = null;
$dbType = null; // 'pdo' or 'mysqli'

if (isset($pdo) && $pdo instanceof PDO) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $dbInstance = $pdo;
        $dbType = 'pdo';
        $dbInstance->exec("SET NAMES utf8mb4");
    }
}

if (!$dbInstance && isset($conn)) {
    if ($conn instanceof PDO && $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $dbInstance = $conn;
        $dbType = 'pdo';
        $dbInstance->exec("SET NAMES utf8mb4");
    } elseif ($conn instanceof mysqli) {
        $dbInstance = $conn;
        $dbType = 'mysqli';
        $dbInstance->set_charset("utf8mb4");
    }
}

function executeQuery($sql, $params = []) {
    global $dbInstance, $dbType;
    if (!$dbInstance) {
        throw new Exception("MySQL Database connection is not available.");
    }
    if ($dbType === 'pdo') {
        $stmt = $dbInstance->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } else {
        $stmt = $dbInstance->prepare($sql);
        if (!$stmt) {
            throw new Exception("MySQLi Statement Preparation Failed: " . $dbInstance->error);
        }
        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_double($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $bindParams[] = $param;
            }
            $stmt->bind_param($types, ...$bindParams);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
}

function fetchRowSingle($stmtResult) {
    global $dbType;
    if ($dbType === 'pdo') {
        return $stmtResult->fetch(PDO::FETCH_ASSOC);
    } else {
        return $stmtResult->fetch_assoc();
    }
}

function fetchRowAll($stmtResult) {
    global $dbType;
    if ($dbType === 'pdo') {
        return $stmtResult->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return $stmtResult->fetch_all(MYSQLI_ASSOC);
    }
}

// 2c. PostgreSQL Connection Wrapper / Adapter Layer
$pgInstance = null;
$pgType = null;

if (isset($pg_pdo) && $pg_pdo instanceof PDO) {
    $pgInstance = $pg_pdo;
    $pgType = 'pdo';
} elseif (isset($pdo_pg) && $pdo_pg instanceof PDO) {
    $pgInstance = $pdo_pg;
    $pgType = 'pdo';
} elseif (isset($db_pg) && $db_pg instanceof PDO) {
    $pgInstance = $db_pg;
    $pgType = 'pdo';
} elseif (isset($pdo) && $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
    $pgInstance = $pdo;
    $pgType = 'pdo';
} elseif (isset($pg_conn) && (is_resource($pg_conn) || (is_object($pg_conn) && get_class($pg_conn) === 'PgSql\Connection'))) {
    $pgInstance = $pg_conn;
    $pgType = 'pg';
} elseif (isset($conn_pg) && (is_resource($conn_pg) || (is_object($conn_pg) && get_class($conn_pg) === 'PgSql\Connection'))) {
    $pgInstance = $conn_pg;
    $pgType = 'pg';
} elseif (isset($dbconn) && (is_resource($dbconn) || (is_object($dbconn) && get_class($dbconn) === 'PgSql\Connection'))) {
    $pgInstance = $dbconn;
    $pgType = 'pg';
} elseif (isset($conn) && (is_resource($conn) || (is_object($conn) && get_class($conn) === 'PgSql\Connection'))) {
    $pgInstance = $conn;
    $pgType = 'pg';
}

function executePgQuery($sql, $params = []) {
    global $pgInstance, $pgType;
    if (!$pgInstance) {
        throw new Exception("PostgreSQL connection not detected. Please verify 'db_connect.php'.");
    }
    if ($pgType === 'pdo') {
        $stmt = $pgInstance->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $indexedSql = $sql;
        if (!empty($params)) {
            $paramIndex = 1;
            $indexedSql = preg_replace_callback('/\?/', function() use (&$paramIndex) {
                return '$' . ($paramIndex++);
            }, $sql);
            $strParams = array_map(function($p) { return (string)$p; }, $params);
            $result = @pg_query_params($pgInstance, $indexedSql, $strParams);
        } else {
            $result = @pg_query($pgInstance, $indexedSql);
        }

        if (!$result) {
            throw new Exception("PostgreSQL Query Error: " . pg_last_error($pgInstance));
        }
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

// 3. Define Species Mappings with Unique Primate Icons & Fruit Fly
$SPECIES_CONFIG = [
    ["key" => "Mouse",       "label" => "Mouse",       "icon" => "\u{1F42D}", "color" => "#f59e0b", "group" => "Mammal"],
    ["key" => "Rat",         "label" => "Rat",         "icon" => "\u{1F400}", "color" => "#10b981", "group" => "Mammal"],
    ["key" => "Zebrafish",   "label" => "Zebrafish",   "icon" => "\u{1F41F}", "color" => "#06b6d4", "group" => "Vertebrate"],
    ["key" => "Rabbit",      "label" => "Rabbit",      "icon" => "\u{1F430}", "color" => "#ec4899", "group" => "Mammal"],
    ["key" => "Baboon",      "label" => "Baboon",      "icon" => "\u{1F412}", "color" => "#a855f7", "group" => "Primate"],
    ["key" => "Macaque",     "label" => "Macaque",     "icon" => "\u{1F435}", "color" => "#8b5cf6", "group" => "Primate"],
    ["key" => "Cynomolgus",  "label" => "Cynomolgus",  "icon" => "\u{1F9A7}", "color" => "#d97706", "group" => "Primate"],
    ["key" => "Chimpanzee",  "label" => "Chimpanzee",  "icon" => "\u{1F98D}", "color" => "#64748b", "group" => "Primate"],
    ["key" => "Chicken",     "label" => "Chicken",     "icon" => "\u{1F414}", "color" => "#ef4444", "group" => "Vertebrate"],
    ["key" => "Drosophila",  "label" => "Fruit Fly",   "icon" => "\u{1FAB0}", "color" => "#6366f1", "group" => "Invertebrate"],
    ["key" => "CElegans",    "label" => "C. elegans",  "icon" => "\u{1FAB1}", "color" => "#14b8a6", "group" => "Invertebrate"]
];

$SPECIES_KEYS = array_column($SPECIES_CONFIG, 'key');

function parseOrthologs($val) {
    if (!$val) return [];
    $clean = trim($val);
    if (in_array(strtolower($clean), ["na", "none", "null", "-", "no ortholog", "no orthologs", ""])) {
        return [];
    }
    return array_filter(array_map('trim', explode(';', $clean)));
}

// 4. Server-Side AJAX API Controllers
if (isset($_GET['action'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $action = $_GET['action'];

    // Action 1: Search Orthology Table (MySQL)
    if ($action === 'search') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $speciesFilter = isset($_GET['species']) ? trim($_GET['species']) : 'all';
            $minConserved = isset($_GET['min_conserved']) ? max(0, min(11, (int)$_GET['min_conserved'])) : 0;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 100;
            $offset = ($page - 1) * $limit;

            $conditions = [];
            $params = [];

            if ($q !== '') {
                $conditions[] = "(ortho_tb_new.Gene_Symbol LIKE ? OR ortho_tb_new.Human_Ensembl LIKE ? OR ortho_tb_new.HGNC_ID LIKE ?)";
                $likeVal = "%$q%";
                $params[] = $likeVal;
                $params[] = $likeVal;
                $params[] = $likeVal;
            }

            if ($speciesFilter !== 'all' && in_array($speciesFilter, $SPECIES_KEYS)) {
                $conditions[] = "(ortho_tb_new.`$speciesFilter` IS NOT NULL AND ortho_tb_new.`$speciesFilter` != 'No ortholog' AND ortho_tb_new.`$speciesFilter` != '')";
            }

            if ($minConserved > 0) {
                $conservedCases = [];
                foreach ($SPECIES_KEYS as $sKey) {
                    $conservedCases[] = "CASE WHEN ortho_tb_new.`$sKey` IS NOT NULL AND ortho_tb_new.`$sKey` != 'No ortholog' AND ortho_tb_new.`$sKey` != '' THEN 1 ELSE 0 END";
                }
                $conditions[] = "(" . implode(" + ", $conservedCases) . ") >= ?";
                $params[] = $minConserved;
            }

            $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

            $countQuery = "SELECT COUNT(*) as total FROM ortho_tb_new $whereClause";
            $countRes = executeQuery($countQuery, $params);
            $countData = fetchRowSingle($countRes);
            $totalRows = (int)($countData['total'] ?? 0);

            $selectQuery = "SELECT ortho_tb_new.*, 
                                   CASE 
                                       WHEN ortho_tb_new.HGNC_ID = 'HGNC:33604' AND ortho_tb_new.Human_Ensembl = 'ENSG00000223459' THEN 'TCAF1P1' 
                                       ELSE ortho_tb_new.Gene_Symbol 
                                   END AS Gene_Name 
                            FROM ortho_tb_new 
                            $whereClause 
                            ORDER BY CASE 
                                         WHEN ortho_tb_new.HGNC_ID = 'HGNC:33604' AND ortho_tb_new.Human_Ensembl = 'ENSG00000223459' THEN 'TCAF1P1' 
                                         ELSE COALESCE(ortho_tb_new.Gene_Symbol, '') 
                                     END ASC 
                            LIMIT $limit OFFSET $offset";
            $dataRes = executeQuery($selectQuery, $params);
            $rawRows = fetchRowAll($dataRes);

            $processedRows = [];
            foreach ($rawRows as $row) {
                $conservationCount = 0;
                $orthologTotal = 0;
                $speciesData = [];

                foreach ($SPECIES_KEYS as $sKey) {
                    $parsed = parseOrthologs($row[$sKey] ?? '');
                    $speciesData[$sKey] = implode('; ', $parsed);
                    $count = count($parsed);
                    if ($count > 0) {
                        $conservationCount++;
                        $orthologTotal += $count;
                    }
                }

                $geneName = $row['Gene_Name'] ?? '';
                if (trim($row['HGNC_ID'] ?? '') === 'HGNC:33604' && trim($row['Human_Ensembl'] ?? '') === 'ENSG00000223459') {
                    $geneName = 'TCAF1P1';
                } elseif (!$geneName) {
                    $geneName = 'N/A';
                }

                $processed = [
                    'id' => $row['id'] ?? 0,
                    'HGNC_ID' => $row['HGNC_ID'] ?? '',
                    'NCBI_gene_ID' => 'N/A',
                    'HumanEnsembl' => $row['Human_Ensembl'] ?? '',
                    'Gene_Name' => $geneName,
                    '_conservation' => $conservationCount,
                    '_orthologTotal' => $orthologTotal
                ];

                foreach ($SPECIES_KEYS as $sKey) {
                    $processed[$sKey] = !empty($speciesData[$sKey]) ? $speciesData[$sKey] : "No ortholog";
                }

                $processedRows[] = $processed;
            }

            echo json_encode([
                'status' => 'success',
                'data' => $processedRows,
                'pagination' => [
                    'total' => $totalRows,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($totalRows / $limit) ?: 1
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Action 2: Fast Optimized Search on struct_tb (PostgreSQL) - Directly Filtering Gene Symbol
    if ($action === 'search_struct') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $minAlnScore = isset($_GET['min_aln_score']) ? (float)$_GET['min_aln_score'] : 0.0;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 40;
            $offset = ($page - 1) * $limit;

            $conditions = [];
            $params = [];

            // 1. Direct Search Matching on Gene Symbol, HGNC ID, UniProt Query, Target
            if ($q !== '') {
                $searchValues = [trim($q)];

                // If user entered Ensembl or HGNC ID, lookup corresponding Gene Symbol from MySQL
                if ($dbInstance && (stripos($q, 'ENSG') === 0 || stripos($q, 'HGNC') === 0)) {
                    try {
                        $aliasSql = "SELECT Gene_Symbol FROM ortho_tb_new WHERE Human_Ensembl = ? OR HGNC_ID = ? LIMIT 5";
                        $aliasRes = executeQuery($aliasSql, [$q, $q]);
                        $aliasRows = fetchRowAll($aliasRes);
                        foreach ($aliasRows as $aRow) {
                            if (!empty($aRow['Gene_Symbol'])) {
                                $searchValues[] = trim($aRow['Gene_Symbol']);
                            }
                        }
                    } catch (Exception $e) {}
                }

                $searchValues = array_unique(array_filter($searchValues));
                $qClauses = [];

                foreach ($searchValues as $val) {
                    $likeVal = "%$val%";
                    $qClauses[] = "gene_symbol ILIKE ?";
                    $qClauses[] = "hgnc_id ILIKE ?";
                    $qClauses[] = "query ILIKE ?";
                    $qClauses[] = "target ILIKE ?";
                    $params[] = $likeVal;
                    $params[] = $likeVal;
                    $params[] = $likeVal;
                    $params[] = $likeVal;
                }

                // Check common organism name match
                foreach ($TAX_NAME_MAP as $sci => $common) {
                    if (strcasecmp($common, $q) === 0 || strcasecmp($sci, $q) === 0) {
                        $qClauses[] = "taxname ILIKE ?";
                        $params[] = "%$sci%";
                    }
                }

                if (!empty($qClauses)) {
                    $conditions[] = "(" . implode(" OR ", $qClauses) . ")";
                }
            }

            // 2. Multi-Organisms Filter using fast IN (...) clause
            $selectedTaxes = [];
            if (!empty($_GET['taxnames'])) {
                $selectedTaxes = array_filter(array_map('trim', explode(',', $_GET['taxnames'])));
            } elseif (isset($_GET['taxname']) && $_GET['taxname'] !== 'all' && $_GET['taxname'] !== '') {
                $selectedTaxes = [trim($_GET['taxname'])];
            }

            if (!empty($selectedTaxes) && !in_array('all', $selectedTaxes)) {
                $inTaxList = [];
                foreach ($selectedTaxes as $tItem) {
                    $inTaxList[] = $tItem;
                    foreach ($TAX_NAME_MAP as $sci => $common) {
                        if (strcasecmp($tItem, $common) === 0) {
                            $inTaxList[] = $sci;
                        }
                    }
                }
                $inTaxList = array_unique(array_filter($inTaxList));
                if (!empty($inTaxList)) {
                    $placeholders = implode(',', array_fill(0, count($inTaxList), '?'));
                    $conditions[] = "taxname IN ($placeholders)";
                    foreach ($inTaxList as $taxVal) {
                        $params[] = $taxVal;
                    }
                }
            }

            // 3. Min Alignment TM-score Filter
            if ($minAlnScore > 0) {
                $conditions[] = "alntmscore >= ?";
                $params[] = $minAlnScore;
            }

            $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

            // 4. Fast Grouped Query by Gene Symbol
            $selectQuery = "SELECT 
                                gene_symbol,
                                MAX(hgnc_id) AS hgnc_id,
                                query,
                                COUNT(*) AS target_count,
                                MAX(alntmscore) AS max_alnscore,
                                AVG(alntmscore) AS avg_alnscore,
                                json_agg(
                                    json_build_object(
                                        'sno', sno,
                                        'target', target,
                                        'taxname', taxname,
                                        'qtmscore', qtmscore,
                                        'ttmscore', ttmscore,
                                        'alntmscore', alntmscore
                                    ) ORDER BY alntmscore DESC NULLS LAST
                                ) AS targets
                            FROM struct_tb 
                            $whereClause 
                            GROUP BY gene_symbol, query 
                            ORDER BY gene_symbol ASC NULLS LAST 
                            LIMIT $limit OFFSET $offset";

            $rawRows = executePgQuery($selectQuery, $params);

            // 5. Total count for pagination
            $totalRows = 0;
            if (count($rawRows) > 0) {
                if ($q !== '' || $minAlnScore > 0 || (!empty($selectedTaxes) && !in_array('all', $selectedTaxes))) {
                    $countQuery = "SELECT COUNT(*) as total FROM (SELECT 1 FROM struct_tb $whereClause GROUP BY gene_symbol, query) t";
                    $countRes = executePgQuery($countQuery, $params);
                    $totalRows = isset($countRes[0]['total']) ? (int)$countRes[0]['total'] : count($rawRows);
                } else {
                    $totalRows = 19200; // Baseline gene count estimate
                }
            }

            $processedRows = [];
            foreach ($rawRows as $r) {
                $targetsArr = is_string($r['targets']) ? json_decode($r['targets'], true) : $r['targets'];
                if (is_array($targetsArr)) {
                    foreach ($targetsArr as &$tObj) {
                        $tObj['common_tax'] = getCommonName($tObj['taxname'] ?? '');
                    }
                    unset($tObj);
                }

                $processedRows[] = [
                    'gene_symbol'   => !empty($r['gene_symbol']) ? trim($r['gene_symbol']) : 'Uncharacterized',
                    'hgnc_id'       => !empty($r['hgnc_id']) ? trim($r['hgnc_id']) : 'N/A',
                    'query'         => !empty($r['query']) ? trim($r['query']) : 'N/A',
                    'target_count'  => (int)$r['target_count'],
                    'max_alnscore'  => round((float)($r['max_alnscore'] ?? 0), 4),
                    'avg_alnscore'  => round((float)($r['avg_alnscore'] ?? 0), 4),
                    'targets'       => $targetsArr ?: []
                ];
            }

            echo json_encode([
                'status' => 'success',
                'data' => $processedRows,
                'pagination' => [
                    'total' => $totalRows,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => max(1, (int)ceil($totalRows / $limit))
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Action 3: Autocomplete suggestions
    if ($action === 'suggestions') {
        header('Content-Type: application/json; charset=utf-8');
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($q) < 2) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }

        try {
            $sugQuery = "SELECT ortho_tb_new.Human_Ensembl, ortho_tb_new.HGNC_ID, 
                                CASE 
                                    WHEN ortho_tb_new.HGNC_ID = 'HGNC:33604' AND ortho_tb_new.Human_Ensembl = 'ENSG00000223459' THEN 'TCAF1P1' 
                                    ELSE ortho_tb_new.Gene_Symbol 
                                END AS Gene_Name 
                         FROM ortho_tb_new 
                         WHERE (ortho_tb_new.Gene_Symbol LIKE ? OR ortho_tb_new.Human_Ensembl LIKE ? OR ortho_tb_new.HGNC_ID LIKE ?) 
                         ORDER BY Gene_Name ASC 
                         LIMIT 6";
            $likeVal = "%$q%";
            $sugRes = executeQuery($sugQuery, [$likeVal, $likeVal, $likeVal]);
            $rows = fetchRowAll($sugRes);

            echo json_encode([
                'status' => 'success',
                'data' => array_map(function($r) {
                    $geneName = $r['Gene_Name'];
                    if (trim($r['HGNC_ID']) === 'HGNC:33604' && trim($r['Human_Ensembl']) === 'ENSG00000223459') {
                        $geneName = 'TCAF1P1';
                    } elseif (!$geneName) {
                        $geneName = 'N/A';
                    }
                    return [
                        'HumanEnsembl' => $r['Human_Ensembl'],
                        'HGNC_ID' => $r['HGNC_ID'],
                        'Gene_Name' => $geneName
                    ];
                }, $rows)
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
        exit;
    }

    // Action 4: Export Orthology Table (CSV)
    if ($action === 'export') {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $speciesFilter = isset($_GET['species']) ? trim($_GET['species']) : 'all';
        $minConserved = isset($_GET['min_conserved']) ? max(0, min(11, (int)$_GET['min_conserved'])) : 0;

        $conditions = [];
        $params = [];

        if ($q !== '') {
            $conditions[] = "(ortho_tb_new.Gene_Symbol LIKE ? OR ortho_tb_new.Human_Ensembl LIKE ? OR ortho_tb_new.HGNC_ID LIKE ?)";
            $likeVal = "%$q%";
            $params[] = $likeVal;
            $params[] = $likeVal;
            $params[] = $likeVal;
        }
        if ($speciesFilter !== 'all' && in_array($speciesFilter, $SPECIES_KEYS)) {
            $conditions[] = "(ortho_tb_new.`$speciesFilter` IS NOT NULL AND ortho_tb_new.`$speciesFilter` != 'No ortholog' AND ortho_tb_new.`$speciesFilter` != '')";
        }
        if ($minConserved > 0) {
            $conservedCases = [];
            foreach ($SPECIES_KEYS as $sKey) {
                $conservedCases[] = "CASE WHEN ortho_tb_new.`$sKey` IS NOT NULL AND ortho_tb_new.`$sKey` != 'No ortholog' AND ortho_tb_new.`$sKey` != '' THEN 1 ELSE 0 END";
            }
            $conditions[] = "(" . implode(" + ", $conservedCases) . ") >= ?";
            $params[] = $minConserved;
        }
        $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=orthology_export_dataset.csv');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, array_merge(['Gene Name', 'HGNC ID', 'NCBI ID', 'Human Ensembl ID'], $SPECIES_KEYS));

        try {
            $exportQuery = "SELECT ortho_tb_new.*, 
                                   CASE 
                                       WHEN ortho_tb_new.HGNC_ID = 'HGNC:33604' AND ortho_tb_new.Human_Ensembl = 'ENSG00000223459' THEN 'TCAF1P1' 
                                       ELSE ortho_tb_new.Gene_Symbol 
                                   END AS Gene_Name 
                            FROM ortho_tb_new 
                            $whereClause 
                            ORDER BY CASE 
                                         WHEN ortho_tb_new.HGNC_ID = 'HGNC:33604' AND ortho_tb_new.Human_Ensembl = 'ENSG00000223459' THEN 'TCAF1P1' 
                                         ELSE COALESCE(ortho_tb_new.Gene_Symbol, '') 
                                     END ASC";
            $exportRes = executeQuery($exportQuery, $params);

            while ($row = fetchRowSingle($exportRes)) {
                $geneName = $row['Gene_Name'];
                if (trim($row['HGNC_ID']) === 'HGNC:33604' && trim($row['Human_Ensembl']) === 'ENSG00000223459') {
                    $geneName = 'TCAF1P1';
                } elseif (!$geneName) {
                    $geneName = 'N/A';
                }

                $line = [
                    $geneName,
                    $row['HGNC_ID'],
                    'N/A',
                    $row['Human_Ensembl']
                ];
                foreach ($SPECIES_KEYS as $sKey) {
                    $line[] = $row[$sKey];
                }
                fputcsv($output, $line);
            }
        } catch (Exception $e) {}
        fclose($output);
        exit;
    }

    // Action 5: Export struct_tb (PostgreSQL CSV)
    if ($action === 'export_struct') {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $minAlnScore = isset($_GET['min_aln_score']) ? (float)$_GET['min_aln_score'] : 0.0;

        $conditions = [];
        $params = [];

        if ($q !== '') {
            $conditions[] = "(gene_symbol ILIKE ? OR hgnc_id ILIKE ? OR query ILIKE ? OR target ILIKE ?)";
            $likeVal = "%$q%";
            $params[] = $likeVal;
            $params[] = $likeVal;
            $params[] = $likeVal;
            $params[] = $likeVal;
        }

        if ($minAlnScore > 0) {
            $conditions[] = "alntmscore >= ?";
            $params[] = $minAlnScore;
        }

        $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=structural_alignment_dataset.csv');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['S.No.', 'Gene Symbol', 'HGNC ID', 'Human Uniprot', 'Target', 'Organism', 'Q-TMscore', 'T-TMscore', 'Aln-TMscore']);

        try {
            $exportQuery = "SELECT sno, gene_symbol, hgnc_id, query, target, taxname, qtmscore, ttmscore, alntmscore 
                            FROM struct_tb 
                            $whereClause 
                            ORDER BY gene_symbol ASC NULLS LAST, alntmscore DESC NULLS LAST 
                            LIMIT 5000";
            $rows = executePgQuery($exportQuery, $params);
            foreach ($rows as $r) {
                fputcsv($output, [
                    $r['sno'],
                    $r['gene_symbol'] ?? 'N/A',
                    $r['hgnc_id'] ?? 'N/A',
                    $r['query'] ?? 'N/A',
                    $r['target'] ?? 'N/A',
                    getCommonName($r['taxname'] ?? ''),
                    $r['qtmscore'] ?? '0.0000',
                    $r['ttmscore'] ?? '0.0000',
                    $r['alntmscore'] ?? '0.0000'
                ]);
            }
        } catch (Exception $e) {
            fputcsv($output, ['Error executing export', $e->getMessage()]);
        }

        fclose($output);
        exit;
    }

    // Action 6: Local PDB Streamer directly from /srv/struct_ortho/
    if ($action === 'get_pdb') {
        $type = isset($_GET['type']) ? trim($_GET['type']) : 'query';
        $id = basename(trim($_GET['id'] ?? ''));

        if (empty($id) || $id === 'N/A') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid PDB / UniProt Identifier']);
            exit;
        }

        $cleanId = preg_replace('/(\.pdb|\.cif)$/i', '', $id);

        $baseDir = ($type === 'query') 
            ? '/srv/struct_ortho/alphafold_structures/' 
            : '/srv/struct_ortho/All_target_pdb/';

        $possibleFiles = [
            $baseDir . $cleanId . '.pdb',
            $baseDir . $cleanId . '.cif',
            $baseDir . 'AF-' . $cleanId . '-F1-model_v4.pdb',
            $baseDir . 'AF-' . $cleanId . '-F1-model_v4.cif',
            $baseDir . 'AF-' . $cleanId . '-F1-model_v3.pdb',
            $baseDir . strtolower($cleanId) . '.pdb',
            $baseDir . strtoupper($cleanId) . '.pdb'
        ];

        $matchedPath = null;
        foreach ($possibleFiles as $filePath) {
            if (file_exists($filePath) && is_readable($filePath)) {
                $matchedPath = $filePath;
                break;
            }
        }

        if ($matchedPath) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: inline; filename="' . basename($matchedPath) . '"');
            readfile($matchedPath);
            exit;
        }

        // Fallback: Stream directly from AlphaFold Database API
        $remoteAlphaFold = "https://alphafold.ebi.ac.uk/files/AF-" . urlencode($cleanId) . "-F1-model_v4.pdb";
        $ctx = stream_context_create(['http' => ['timeout' => 4]]);
        $remotePdb = @file_get_contents($remoteAlphaFold, false, $ctx);

        if ($remotePdb !== false && strlen($remotePdb) > 100) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $remotePdb;
            exit;
        }

        // Fallback 2: Stream from RCSB PDB if 4-character ID
        if (strlen($cleanId) === 4) {
            $rcsbUrl = "https://files.rcsb.org/download/" . strtoupper($cleanId) . ".pdb";
            $rcsbPdb = @file_get_contents($rcsbUrl, false, $ctx);
            if ($rcsbPdb !== false) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $rcsbPdb;
                exit;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => "PDB structure not found for: $cleanId"]);
        exit;
    }
}

// 5. Aggregate Database Statistics (MySQL)
$stats = [
    'total_genes' => 0,
    'total_orthologs' => 0,
    'with_orthologs' => 0,
    'no_orthologs' => 0,
    'highly_conserved' => 0,
    'species_counts' => array_fill_keys($SPECIES_KEYS, 0)
];

if ($dbInstance) {
    try {
        $totalResult = executeQuery("SELECT COUNT(*) as total FROM ortho_tb_new");
        $totalRow = fetchRowSingle($totalResult);
        $stats['total_genes'] = (int)($totalRow['total'] ?? 0);

        $sumCases = [];
        foreach ($SPECIES_KEYS as $sKey) {
            $sumCases[] = "SUM(CASE WHEN `$sKey` IS NOT NULL AND `$sKey` != 'No ortholog' AND `$sKey` != '' THEN 1 ELSE 0 END) as `$sKey`";
        }
        $speciesResult = executeQuery("SELECT " . implode(", ", $sumCases) . " FROM ortho_tb_new");
        $speciesSums = fetchRowSingle($speciesResult);
        foreach ($SPECIES_KEYS as $sKey) {
            $stats['species_counts'][$sKey] = (int)($speciesSums[$sKey] ?? 0);
        }

        $noOrthConditions = [];
        foreach ($SPECIES_KEYS as $sKey) {
            $noOrthConditions[] = "(`$sKey` IS NULL OR `$sKey` = 'No ortholog' OR `$sKey` = '')";
        }
        $noResult = executeQuery("SELECT COUNT(*) as total FROM ortho_tb_new WHERE " . implode(" AND ", $noOrthConditions));
        $noRow = fetchRowSingle($noResult);
        $stats['no_orthologs'] = (int)($noRow['total'] ?? 0);
        $stats['with_orthologs'] = $stats['total_genes'] - $stats['no_orthologs'];

        $conservedCases = [];
        foreach ($SPECIES_KEYS as $sKey) {
            $conservedCases[] = "CASE WHEN `$sKey` IS NOT NULL AND `$sKey` != 'No ortholog' AND `$sKey` != '' THEN 1 ELSE 0 END";
        }
        $conResult = executeQuery("SELECT COUNT(*) as total FROM ortho_tb_new WHERE (" . implode(" + ", $conservedCases) . ") >= 5");
        $conRow = fetchRowSingle($conResult);
        $stats['highly_conserved'] = (int)($conRow['total'] ?? 0);

        $orthCountCases = [];
        foreach ($SPECIES_KEYS as $sKey) {
            $orthCountCases[] = "CASE WHEN `$sKey` IS NOT NULL AND `$sKey` != 'No ortholog' AND `$sKey` != '' THEN (LENGTH(`$sKey`) - LENGTH(REPLACE(`$sKey`, ';', '')) + 1) ELSE 0 END";
        }
        $totalResult = executeQuery("SELECT SUM(" . implode(" + ", $orthCountCases) . ") as total FROM ortho_tb_new");
        $totalRow = fetchRowSingle($totalResult);
        $stats['total_orthologs'] = (int)($totalRow['total'] ?? 0);

    } catch (Exception $e) {}
}

// 5b. Aggregate PostgreSQL Statistics for struct_tb
$structTaxnames = [];
$structChartData = [];
if ($pgInstance) {
    try {
        $taxRows = executePgQuery("SELECT DISTINCT taxname FROM struct_tb WHERE taxname IS NOT NULL AND taxname != '' ORDER BY taxname ASC LIMIT 100");
        $uniqueCommonNames = [];
        foreach ($taxRows as $tRow) {
            $rawTax = $tRow['taxname'];
            $commonTax = getCommonName($rawTax);
            if (!isset($uniqueCommonNames[$commonTax])) {
                $uniqueCommonNames[$commonTax] = $rawTax;
                $structTaxnames[] = [
                    'raw' => $rawTax,
                    'label' => $commonTax
                ];
            }
        }

        usort($structTaxnames, function($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        $chartQuery = "SELECT taxname, COUNT(*) as count, AVG(COALESCE(alntmscore, 0)) as avg_score, SUM(COALESCE(alntmscore, 0)) as sum_score 
                       FROM struct_tb 
                       WHERE taxname IS NOT NULL AND taxname != '' 
                       GROUP BY taxname 
                       ORDER BY count DESC";
        $rawChartData = executePgQuery($chartQuery);

        $aggregated = [];
        foreach ($rawChartData as $cRow) {
            $common = getCommonName($cRow['taxname']);
            $cnt = (int)$cRow['count'];
            $sumScore = (float)($cRow['sum_score'] ?? ($cnt * (float)($cRow['avg_score'] ?? 0)));
            if (!isset($aggregated[$common])) {
                $aggregated[$common] = [
                    'taxname' => $common,
                    'count' => 0,
                    'sum_score' => 0.0
                ];
            }
            $aggregated[$common]['count'] += $cnt;
            $aggregated[$common]['sum_score'] += $sumScore;
        }

        uasort($aggregated, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $aggregated = array_slice($aggregated, 0, 12);

        foreach ($aggregated as $item) {
            $avg = $item['count'] > 0 ? ($item['sum_score'] / $item['count']) : 0.0;
            $structChartData[] = [
                'taxname' => $item['taxname'],
                'count' => $item['count'],
                'avg_score' => round($avg, 4)
            ];
        }
    } catch (Exception $e) {}
}

// Sort Species Configuration descending by counts
$SPECIES_CONFIG_DESC = $SPECIES_CONFIG;
if (isset($stats['species_counts']) && !empty($stats['species_counts'])) {
    usort($SPECIES_CONFIG_DESC, function($a, $b) use ($stats) {
        $countA = $stats['species_counts'][$a['key']] ?? 0;
        $countB = $stats['species_counts'][$b['key']] ?? 0;
        return $countB <=> $countA;
    });
}

// 6. Include Page Header
if (file_exists('header.php')) {
    include 'header.php';
}
?>

<!-- NGL 3D Viewer & Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/ngl@2.0.0-dev.37/dist/ngl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Component Specific Styling -->
<style>
    .custom-wide-container {
        width: 100% !important;
        max-width: 98% !important;
        margin: 0 auto;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    .chart-container { position: relative; height: 300px; width: 100%; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
    
    .help-box-component {
        background: #f4fbfe;
        border-left: 4px solid #0d6efd;
        border-radius: 8px;
        padding: 16px;
        color: #2b4655;
        font-size: 0.85rem;
        margin-bottom: 20px;
        display: none;
        border: 1px solid #dee2e6;
        border-left-width: 4px;
    }

    .chart-view-frame {
        position: relative;
        height: 290px;
        width: 100%;
    }

    .species-flex-grid {
        display: grid;
        grid-template-columns: repeat(11, minmax(0, 1fr));
        gap: 8px;
        width: 100%;
    }

    @media (max-width: 1100px) {
        .species-flex-grid {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 10px;
            padding-bottom: 8px;
            -webkit-overflow-scrolling: touch;
        }
        .species-badge-item {
            flex: 0 0 100px;
        }
    }

    .species-badge-item {
        padding: 14px 6px;
        border-radius: 12px;
        border: 1px solid #dee2e6;
        background: #f8f9fa;
        text-align: center;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .species-badge-item:hover {
        transform: translateY(-2px);
        background: #ffffff;
        border-color: #adb5bd;
    }

    .species-badge-item.active {
        background: #eef9fd;
        border-color: #0d6efd;
        box-shadow: inset 0 0 0 1px rgba(13,110,253,0.15);
    }

    .species-badge-icon {
        font-size: 1.9rem;
        line-height: 1.1;
        margin-bottom: 5px;
        transition: transform 0.2s;
    }
    
    .species-badge-item:hover .species-badge-icon {
        transform: scale(1.1);
    }

    .species-badge-text {
        font-size: 0.75rem;
        font-weight: 700;
        color: #212529;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .species-badge-subtext {
        margin-top: 5px;
        font-size: 0.7rem;
        color: #6c757d;
        background: #e9ecef;
        display: inline-block;
        padding: 3px 6px;
        border-radius: 999px;
    }

    .filter-controls-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .filter-controls-row select {
        flex: 1 1 180px;
        min-width: 160px;
        font-size: 0.85rem;
    }

    .conserved-toggle-option {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 6px 12px;
        font-size: 0.85rem;
        color: #212529;
        height: 38px;
    }

    .conserved-toggle-option select {
        border: none;
        background: transparent;
        font-family: inherit;
        font-size: 0.85rem;
        font-weight: 700;
        color: #0d6efd;
        cursor: pointer;
        outline: none;
        padding: 0;
        min-width: unset;
        flex: unset;
        height: auto;
    }

    /* Multi-Organism Checkbox Panel Styling */
    .organism-filter-box {
        background: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 14px 18px;
        margin-bottom: 16px;
    }

    .organism-checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 10px;
        max-height: 160px;
        overflow-y: auto;
        padding: 8px 4px;
    }

    .organism-checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #334155;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }

    .organism-checkbox-item:hover {
        background: #edf2f7;
        border-color: #cbd5e1;
    }

    .organism-checkbox-item input[type="checkbox"] {
        cursor: pointer;
        accent-color: #0d6efd;
        width: 16px;
        height: 16px;
    }

    /* Dynamic Table Styles */
    .dynamic-table-wrapper {
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        max-height: 520px;
        background: white;
    }

    .dynamic-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1100px;
        font-family: inherit;
    }

    .dynamic-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa !important;
        color: #212529 !important;
        padding: 10px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #323539;
        text-align: left;
        vertical-align: middle;
        z-index: 2;
        white-space: nowrap;
    }

    .dynamic-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.82rem;
        vertical-align: middle;
        color: #212529;
        font-family: inherit;
    }

    .dynamic-table tbody tr {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .dynamic-table tbody tr:hover {
        background-color: #f1f7fe !important;
    }

    .gene-title-link {
        color: #0d6efd;
        font-weight: 700;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 6px;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .gene-title-link .gene-link-icon {
        font-size: 0.72rem;
        color: #0d6efd;
        opacity: 0.55;
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .dynamic-table tbody tr:hover .gene-title-link,
    .gene-title-link:hover {
        color: #084298;
        background-color: #dbeafe;
    }

    .hgnc-bold {
        font-weight: 600;
        color: #0d6efd;
        font-size: 0.82rem;
    }

    .no-orth-style {
        color: #adb5bd;
        font-style: italic;
    }

    .multi-orth-style {
        color: #0d6efd;
        font-weight: 600;
    }

    /* Structured Organism Count Tag */
    .species-pill-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        margin: 2px 4px 2px 0;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #1e293b;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .species-pill-tag strong {
        color: #0f172a;
        font-weight: 700;
    }
    .species-pill-tag .count-chip {
        background: #e2e8f0;
        color: #334155;
        padding: 1px 6px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .suggestion-container {
        margin-bottom: 15px;
        display: none;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
    }

    .suggestion-container h4 {
        font-size: 0.92rem;
        color: #212529;
        margin-bottom: 8px;
    }

    .suggestion-chips-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .suggestion-chip-btn {
        background: white;
        border: 1px solid #ced4da;
        color: #0d6efd;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: 0.15s ease;
    }

    .suggestion-chip-btn:hover {
        background: #e9ecef;
    }

    .nav-pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 14px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-status {
        font-size: 0.82rem;
        color: #6c757d;
    }

    .pagination-control-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .p-btn {
        background: white;
        border: 1px solid #dee2e6;
        padding: 5px 11px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #212529;
        cursor: pointer;
    }

    .p-btn:disabled {
        background: #e9ecef;
        color: #adb5bd;
        cursor: not-allowed;
        border-color: #dee2e6;
    }

    .p-btn:not(:disabled):hover {
        background: #f8f9fa;
        border-color: #adb5bd;
    }

    .modal-viewport-overlay {
        position: fixed;
        inset: 0;
        background: rgba(33, 37, 41, 0.5);
        backdrop-filter: blur(4px);
        z-index: 999;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 18px;
    }

    .modal-viewport-overlay.active {
        display: flex;
    }

    .modal-inner-panel {
        width: 100%;
        max-width: 980px;
        max-height: 92vh;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        padding: 24px;
        position: relative;
    }

    .close-modal-btn {
        position: sticky;
        top: 0;
        margin-left: auto;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        font-size: 1.1rem;
        z-index: 10;
        cursor: pointer;
    }

    .panel-header-title {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }

    .panel-header-title h2 {
        font-size: 1.55rem;
        color: #212529;
    }

    .panel-header-title a {
        text-decoration: none;
        color: #0d6efd;
        border: 1px solid #dee2e6;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .narrative-story-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 16px;
        color: #212529;
        line-height: 1.65;
        font-size: 0.9rem;
    }

    .profile-summary-grid, .profile-bio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .profile-card-item {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 14px;
    }

    .profile-card-item .item-lbl {
        font-size: 0.72rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .profile-card-item .item-val {
        font-size: 0.92rem;
        color: #212529;
        font-weight: 700;
        line-height: 1.4;
    }

    .modal-split-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    @media (max-width: 768px) {
        .modal-split-layout {
            grid-template-columns: 1fr;
        }
    }

    .profile-section-box {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 16px;
        background: #fff;
    }

    .profile-section-box h3 {
        font-size: 0.95rem;
        color: #212529;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ascii-tree-output {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 14px;
        overflow-x: auto;
    }

    .ascii-tree {
        min-width: 560px;
        font-family: monospace;
        white-space: pre;
        color: #212529;
        line-height: 1.6;
        font-size: 0.82rem;
    }

    .annotation-control-row {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .annotation-text-area {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 14px;
        color: #212529;
        font-size: 0.85rem;
        line-height: 1.6;
        min-height: 86px;
    }

    .empty-placeholder {
        padding: 44px 16px;
        text-align: center;
        color: #6c757d;
    }

    .empty-placeholder i {
        font-size: 2.5rem;
        margin-bottom: 8px;
        opacity: 0.4;
    }

    .custom-loader-spinner {
        width: 3rem;
        height: 3rem;
        border: 4px solid #e9ecef;
        border-top-color: #0d6efd;
        border-radius: 50%;
        animation: spin-loader 0.8s linear infinite;
        margin-bottom: 12px;
    }
    @keyframes spin-loader {
        to { transform: rotate(360deg); }
    }

    .banner-theme-card {
        background-color: #154795;
        border-radius: 16px;
        padding: 35px;
        color: #ffffff;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .banner-theme-card h1 {
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 2px;
        color: #ffffff;
    }

    .banner-theme-card p {
        font-size: 15px;
        font-weight: 400;
        color: #b8cde4;
        margin-bottom: 0;
        line-height: 1.4;
    }

    .banner-search-box-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        background: #ffffff;
        border-radius: 50px;
        padding: 3px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }

    .banner-search-icon {
        position: absolute;
        left: 20px;
        color: #728096;
        font-size: 1.1rem;
    }

    .banner-search-input {
        border: none;
        background: transparent;
        height: 44px;
        padding-left: 50px;
        padding-right: 15px;
        font-size: 14px;
        color: #212529;
        width: 100%;
        outline: none;
    }

    .banner-search-btn {
        background-color: #23b175;
        border: none;
        color: #ffffff;
        font-size: 15px;
        font-weight: 700;
        border-radius: 50px;
        padding: 0 28px;
        height: 44px;
        transition: background-color 0.2s ease;
        margin-left: 8px;
    }

    .banner-search-btn:hover {
        background-color: #1f9e68;
    }

    .banner-video-btn {
        background-color: #ffffff;
        color: #154795;
        border: 2px solid #ffffff;
        font-size: 14px;
        font-weight: 700;
        border-radius: 50px;
        padding: 10px 24px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        transition: background-color 0.15s, color 0.15s;
    }

    .banner-video-btn:hover {
        background-color: #f1f5f9;
        color: #0f397a;
    }

    .floating-suggestions-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        margin-top: 8px;
        z-index: 1000;
        display: none;
        max-height: 280px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
    }

    .suggestion-item-link {
        display: block;
        padding: 12px 18px;
        color: #2d3748;
        text-decoration: none;
        font-size: 13px;
        text-align: left;
        border-bottom: 1px solid #edf2f7;
        transition: background 0.15s ease;
    }

    .suggestion-item-link:last-child {
        border-bottom: none;
    }

    .suggestion-item-link:hover {
        background-color: #f7fafc;
        color: #154795;
    }

    .suggestion-item-link span.symbol {
        font-weight: 700;
        color: #154795;
    }

    .suggestion-item-link span.meta-tip {
        font-size: 11px;
        color: #a0aec0;
        margin-left: 10px;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 20px;
    }

    /* 3D Structure Multi-Alignment NGL Modal Styles */
    .ngl-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(6px);
        z-index: 1050;
        display: none;
        justify-content: center;
        align-items: center;
        padding: 15px;
    }
    .ngl-modal-overlay.active { display: flex; }

    .ngl-modal-container {
        width: 96vw;
        max-width: 1480px;
        height: 92vh;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: modalScaleIn 0.2s ease-out;
    }
    @keyframes modalScaleIn {
        from { transform: scale(0.96); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .ngl-modal-header {
        background: #0f172a;
        color: #ffffff;
        padding: 14px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #334155;
    }

    .ngl-modal-body {
        display: grid;
        grid-template-columns: 1fr 420px;
        flex: 1;
        overflow: hidden;
        background: #090d16;
    }
    @media (max-width: 992px) {
        .ngl-modal-body { grid-template-columns: 1fr; grid-template-rows: 55% 45%; }
    }

    #nglViewport {
        width: 100%;
        height: 100%;
        position: relative;
        background: radial-gradient(circle at center, #1e293b 0%, #090d16 100%);
        transition: background 0.3s ease;
    }

    .ngl-sidebar-panel {
        background: #ffffff;
        border-left: 1px solid #e2e8f0;
        padding: 16px 18px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .ngl-structure-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 48vh;
        overflow-y: auto;
        padding-right: 4px;
    }

    .ngl-legend-card {
        border-radius: 10px;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.15s ease;
    }
    .ngl-legend-card:hover {
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .ngl-btn-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .ngl-btn-group .btn { font-size: 0.8rem; font-weight: 600; }
</style>

<div class="custom-wide-container pb-4 pt-0">

    <!-- Blue Themed Search Banner -->
    <div class="banner-theme-card">
        <div class="row align-items-center g-4">
            <div class="col-lg-7 text-start">
                <h1>Orthology Explorer</h1>
                <p>Compare alignment patterns, isolate phylogenetic variations, and retrieve annotations dynamically across multi-species gene models.</p>
            </div>

            <div class="col-lg-5 text-lg-end text-start">
                <div class="d-flex align-items-center mb-3">
                    <div class="banner-search-box-wrapper flex-grow-1" style="position: relative;">
                        <i class="fas fa-search banner-search-icon"></i>
                        <input type="text" id="searchInput" class="banner-search-input" placeholder="Search by Gene Name, HGNC, Ensembl, Query..." autocomplete="off" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" />
                        <div id="inlineSuggestions" class="floating-suggestions-dropdown"></div>
                    </div>
                    <button id="bannerSearchBtn" class="banner-search-btn">Search</button>
                </div>
                
                <div class="text-lg-end text-start">
                    <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Orthologs.mp4" target="_blank" class="banner-video-btn">
                        <i class="fas fa-video"></i> Watch Video Tutorial
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Informational Guide Block -->
    <div class="help-box-component" id="helpInfoBox">
        <strong>How to Navigate:</strong> Query the server-side database directly using the input panel. You can search by human identifiers (HGNC ID, Ensembl, or NCBI). Click on species summary cards to instantly filter the dataset, or adjust the "Min. Conserved Species" select box to isolate genes conserved across multiple organisms.
    </div>

    <!-- Data Charts and Visual Analytics: Side-by-Side Grid -->
    <div class="row g-4 mb-4">
        <!-- 1. Ortholog Frequency Chart -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-column me-2 text-primary"></i>Ortholog Frequency Per Species</h5>
                </div>
                <div class="card-body">
                    <div class="chart-view-frame">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Structural Alignment Frequency Chart (PostgreSQL struct_tb - Unified Colors & Clear Visibility) -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-cubes-stacked me-2 text-success"></i>Structural Alignments Per Organism</h5>
                </div>
                <div class="card-body">
                    <div class="chart-view-frame">
                        <canvas id="structBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Species Card Quick-Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-paw me-2"></i>Target Species Summary</h5>
        </div>
        <div class="card-body">
            <div class="species-flex-grid" id="taxaSelectionGrid">
                <?php foreach ($SPECIES_CONFIG_DESC as $sp): ?>
                    <div class="species-badge-item" data-species="<?php echo htmlspecialchars($sp['key']); ?>">
                        <div class="species-badge-icon">
                            <?php echo $sp['icon']; ?>
                        </div>
                        <div class="species-badge-text"><?php echo htmlspecialchars($sp['label']); ?></div>
                        <div class="species-badge-subtext">
                            <?php echo number_format($stats['species_counts'][$sp['key']] ?? 0); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 1: Orthology Alignment Records (ortho_tb_new) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-table me-2"></i>Alignment Records (Orthologs)</h5>
                <span class="badge bg-light text-primary border rounded-pill px-2 py-1 small">
                    <i class="fas fa-mouse-pointer me-1"></i>Click any gene to view profile & tree
                </span>
            </div>
            <span class="badge bg-secondary rounded-pill" id="rowCountTip">0 entries found</span>
        </div>
        <div class="card-body">

            <!-- Live Search Filters Controls -->
            <div class="filter-section">
                <div class="filter-controls-row">
                    <select id="speciesFilter" class="form-select" style="height: 38px;">
                        <option value="all">All target profiles</option>
                        <?php foreach ($SPECIES_CONFIG as $sp): ?>
                            <option value="<?php echo htmlspecialchars($sp['key']); ?>"><?php echo htmlspecialchars($sp['icon'] . ' ' . $sp['label']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="conserved-toggle-option">
                        <label for="minConserved" class="m-0 fw-bold text-muted small">Min. Conserved:</label>
                        <select id="minConserved">
                            <option value="0">Any (0+)</option>
                            <option value="1">1+ species</option>
                            <option value="2">2+ species</option>
                            <option value="3">3+ species</option>
                            <option value="4">4+ species</option>
                            <option value="5">5+ species</option>
                            <option value="6">6+ species</option>
                            <option value="7">7+ species</option>
                            <option value="8">8+ species</option>
                            <option value="9">9+ species</option>
                            <option value="10">10+ species</option>
                            <option value="11">All (11)</option>
                        </select>
                    </div>

                    <button class="btn btn-success rounded-pill" id="exportBtn" title="Export orthology rows to CSV" style="height: 38px;">
                        <i class="fas fa-file-export me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-secondary rounded-pill" id="clearFilterBtn" style="height: 38px;">
                        <i class="fas fa-xmark me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Suggestion Box -->
            <div class="suggestion-container" id="suggestionBox">
                <h4><i class="fas fa-lightbulb"></i> Approximate Matches:</h4>
                <div class="suggestion-chips-grid" id="suggestions"></div>
            </div>

            <!-- Database Data Display Table -->
            <div class="dynamic-table-wrapper" id="tableWrapper">
                <div class="empty-placeholder">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <p>Communicating with remote SQL host...</p>
                </div>
            </div>

            <!-- Dynamic Page Selector Controls -->
            <div class="nav-pagination-bar" id="paginationContainer">
                <div class="pagination-status" id="paginationInfo">Showing 0 of 0 entries</div>
                <div class="pagination-control-buttons">
                    <button class="p-btn" id="prevPageBtn" disabled><i class="fas fa-chevron-left"></i> Prev</button>
                    <span id="pageNumbers"></span>
                    <button class="p-btn" id="nextPageBtn" disabled>Next <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: Structural Alignment Records (struct_tb - Grouped By Gene) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-dna me-2"></i>Structural Alignment Records (Grouped By Gene)</h5>
                <span class="badge bg-primary text-white rounded-pill px-2 py-1 small">
                    <i class="fas fa-cubes me-1"></i>Click row to launch Multi-Species 3D Alignment
                </span>
            </div>
            <span class="badge bg-primary rounded-pill" id="structRowCountTip">0 genes found</span>
        </div>
        <div class="card-body">

            <!-- Checkbox Multi-Organism Selection Component -->
            <div class="organism-filter-box">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark small text-uppercase"><i class="fas fa-filter me-1 text-primary"></i> Filter By Organisms:</span>
                        <span class="badge bg-light text-secondary border" id="organismSelectCounter">All Selected</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" id="checkAllOrganismsBtn" style="font-size: 0.8rem;">
                            <i class="fas fa-check-double me-1"></i> Check All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fw-semibold" id="uncheckAllOrganismsBtn" style="font-size: 0.8rem;">
                            <i class="fas fa-xmark me-1"></i> Uncheck All
                        </button>
                    </div>
                </div>

                <div class="organism-checkbox-grid" id="organismCheckboxContainer">
                    <?php foreach ($structTaxnames as $idx => $tn): ?>
                        <label class="organism-checkbox-item">
                            <input type="checkbox" class="organism-checkbox" value="<?php echo htmlspecialchars($tn['raw']); ?>" checked />
                            <span><?php echo htmlspecialchars($tn['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Structural Table Controls Row -->
            <div class="filter-section py-2">
                <div class="filter-controls-row">
                    <div class="conserved-toggle-option flex-grow-1">
                        <label for="structMinAlnScore" class="m-0 fw-bold text-muted small">Min. Aln TM-score:</label>
                        <select id="structMinAlnScore" class="w-100">
                            <option value="0">All Scores (0.0+)</option>
                            <option value="0.5">â‰¥ 0.5 (Significant Structure Match)</option>
                            <option value="0.7">â‰¥ 0.7 (High Confidence Match)</option>
                            <option value="0.85">â‰¥ 0.85 (Very High Confidence)</option>
                        </select>
                    </div>

                    <button class="btn btn-success rounded-pill" id="structExportBtn" title="Export structural records to CSV" style="height: 38px;">
                        <i class="fas fa-file-export me-1"></i> Export Structural CSV
                    </button>
                    <button class="btn btn-secondary rounded-pill" id="structResetBtn" style="height: 38px;">
                        <i class="fas fa-xmark me-1"></i> Reset Filters
                    </button>
                </div>
            </div>

            <!-- Structural Data Display Table -->
            <div class="dynamic-table-wrapper" id="structTableWrapper">
                <div class="empty-placeholder">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <p>Communicating with PostgreSQL database...</p>
                </div>
            </div>

            <!-- Structural Page Selector Controls -->
            <div class="nav-pagination-bar" id="structPaginationContainer">
                <div class="pagination-status" id="structPaginationInfo">Showing 0 of 0 entries</div>
                <div class="pagination-control-buttons">
                    <button class="p-btn" id="structPrevPageBtn" disabled><i class="fas fa-chevron-left"></i> Prev</button>
                    <span id="structPageNumbers"></span>
                    <button class="p-btn" id="structNextPageBtn" disabled>Next <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Annotation Detail Modal Overlay -->
<div class="modal-viewport-overlay" id="geneModal">
    <div class="modal-inner-panel">
        <button class="close-modal-btn" id="modalClose"><i class="fas fa-times"></i></button>

        <div class="panel-header-title">
            <h2 id="modalGeneTitle" class="fw-bold">Gene Profile</h2>
            <a href="#" target="_blank" rel="noopener noreferrer" id="ensemblGeneLink" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="fas fa-up-right-from-square me-1"></i> Explore Ensembl Portal
            </a>
        </div>

        <div class="narrative-story-box" id="geneStory">
            Select a row from the primary database grid to compile biological reports.
        </div>

        <div class="profile-summary-grid" id="modalSummary"></div>
        <div class="profile-bio-grid" id="bioGrid"></div>

        <div class="modal-split-layout">
            <div class="profile-section-box">
                <h3 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-sitemap me-2"></i>Tree-like ortholog view</h3>
                <div class="ascii-tree-output">
                    <div class="ascii-tree" id="treeView">No gene selected.</div>
                </div>
            </div>

            <div class="profile-section-box">
                <h3 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-database me-2"></i>Live Ensembl annotation</h3>
                <div class="annotation-control-row">
                    <button class="btn btn-primary rounded-pill btn-sm px-3" id="fetchEnsemblBtn">
                        <i class="fas fa-cloud-download-alt me-1"></i> Fetch Ensembl info
                    </button>
                    <span class="card-subtitle-tip text-muted">Fetch real-time annotation from the Ensembl REST service</span>
                </div>
                <div class="annotation-text-area mt-3" id="ensemblInfo">
                    <span class="loading text-muted">Trigger the search button to initiate REST querying.</span>
                </div>
            </div>
        </div>

        <div class="profile-section-box style-card-row mt-4">
            <h3 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-list me-2"></i>Full Ortholog Details</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle m-0" style="font-size: 0.82rem;">
                    <thead class="table-dark">
                        <tr>
                            <th>Species</th>
                            <th>Ortholog IDs</th>
                            <th>Phylogenetic Group</th>
                            <th>Alignment Status</th>
                        </tr>
                    </thead>
                    <tbody id="modalOrthBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 3D Structural Multi-Alignment Superposition Modal (NGL Viewer) -->
<div class="ngl-modal-overlay" id="nglModal">
    <div class="ngl-modal-container">
        <div class="ngl-modal-header">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-primary rounded-3 text-white">
                    <i class="fas fa-cubes fa-lg"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold" id="nglModalTitle">Multi-Species 3D Orthology Superposition</h5>
                    <small class="text-info" id="nglModalSubtitle">Homo sapiens Reference vs All Target Organisms</small>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-light rounded-circle" id="nglModalClose" style="width: 32px; height: 32px; padding: 0;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="ngl-modal-body">
            <!-- 3D Viewport -->
            <div id="nglViewport">
                <div id="nglLoadingSpinner" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; color:white; z-index:10;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="fw-bold" id="nglSpinnerText">Superimposing 3D Protein Structures...</div>
                </div>
            </div>

            <!-- Control & Legend Sidebar -->
            <div class="ngl-sidebar-panel">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                    <h6 class="fw-bold text-dark mb-0" style="font-size:0.9rem;"><i class="fas fa-layer-group me-2 text-primary"></i>Aligned Structures (<span id="nglModelCounter">0</span>)</h6>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" id="nglShowAllBtn" style="font-size: 0.75rem;">Show All</button>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" id="nglHideAllBtn" style="font-size: 0.75rem;">Hide All</button>
                    </div>
                </div>

                <!-- Structures List Accordion/Cards Container -->
                <div class="ngl-structure-list" id="nglStructureList">
                    <!-- Cards will be populated dynamically -->
                </div>

                <!-- Global View Controls with Dark/Light Background Toggle -->
                <div class="border-top pt-2">
                    <label class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:0.75rem;">Global View Tools</label>
                    <div class="ngl-btn-group mb-2">
                        <button class="btn btn-outline-secondary" id="nglResetCamBtn"><i class="fas fa-crosshairs me-1"></i> Center</button>
                        <button class="btn btn-outline-secondary" id="nglSpinToggleBtn"><i class="fas fa-sync me-1"></i> Auto-Spin</button>
                        <button class="btn btn-outline-primary" id="nglThemeToggleBtn"><i class="fas fa-sun me-1"></i> Light BG</button>
                        <button class="btn btn-outline-dark" id="nglScreenshotBtn"><i class="fas fa-camera me-1"></i> Snapshot</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const GLOBAL_STATS = <?php echo json_encode($stats ?? null); ?>;
const STRUCT_CHART_DATA = <?php echo json_encode($structChartData ?? []); ?>;
const SPECIES_CONFIG = <?php echo json_encode($SPECIES_CONFIG); ?>;
const SPECIES_KEYS = <?php echo json_encode($SPECIES_KEYS); ?>;

// Distinct Palettes for 3D Target Structures
const DISTINCT_COLORS = [
    "#f59e0b", "#10b981", "#06b6d4", "#ec4899", "#8b5cf6", 
    "#ef4444", "#6366f1", "#14b8a6", "#d97706", "#84cc16", "#f97316"
];

// Species Color Map Dictionary
const SPECIES_COLOR_MAP = {};
SPECIES_CONFIG.forEach(sp => {
    SPECIES_COLOR_MAP[sp.label] = sp.color;
    SPECIES_COLOR_MAP[sp.key] = sp.color;
});
SPECIES_COLOR_MAP['Fruit Fly'] = '#6366f1';
SPECIES_COLOR_MAP['Drosophila'] = '#6366f1';
SPECIES_COLOR_MAP['Baboon'] = '#a855f7';
SPECIES_COLOR_MAP['Human'] = '#0d6efd';

// Scientific to Common Name Map in JS
const TAX_NAME_MAP = {
    'Caenorhabditis elegans': 'C. elegans',
    'Danio rerio': 'Zebrafish',
    'Drosophila melanogaster': 'Fruit Fly',
    'Drosophila': 'Fruit Fly',
    'Gallus gallus': 'Chicken',
    'Macaca fascicularis': 'Cynomolgus',
    'Macaca mulatta': 'Macaque',
    'Mus musculus': 'Mouse',
    'Oryctolagus cuniculus': 'Rabbit',
    'Pan troglodytes': 'Chimpanzee',
    'Papio anubis': 'Baboon',
    'Papio': 'Baboon',
    'Rattus norvegicus': 'Rat',
    'Homo sapiens': 'Human'
};

function getCommonName(taxname) {
    if (!taxname) return 'N/A';
    const trimmed = String(taxname).trim();
    return TAX_NAME_MAP[trimmed] || trimmed;
}

// --- STATE: Orthology Table ---
let filteredData = [];
let currentPage = 1;
let totalPages = 1;
let totalEntries = 0;
let barChartInstance = null;
let structBarChartInstance = null;

// --- STATE: Structural Table ---
let structData = [];
let structCurrentPage = 1;
let structTotalPages = 1;
let structTotalEntries = 0;

// --- STATE: NGL Multi-Viewer ---
let nglStage = null;
let humanComp = null;
let targetComps = []; // Array of { comp, target, taxname, color, alnScore }
let isSpinning = false;
let isLightBg = false;

// DOM Elements: General & Banner
const helpToggleBtn = document.getElementById("helpToggleBtn");
const helpInfoBox = document.getElementById("helpInfoBox");
const taxaSelectionGrid = document.getElementById("taxaSelectionGrid");
const searchInput = document.getElementById("searchInput");
const inlineSuggestions = document.getElementById("inlineSuggestions");
const bannerSearchBtn = document.getElementById("bannerSearchBtn");

// DOM Elements: Orthology Table
const tableWrapper = document.getElementById("tableWrapper");
const rowCountTip = document.getElementById("rowCountTip");
const speciesFilter = document.getElementById("speciesFilter");
const minConserved = document.getElementById("minConserved");
const clearFilterBtn = document.getElementById("clearFilterBtn");
const exportBtn = document.getElementById("exportBtn");
const suggestionBox = document.getElementById("suggestionBox");
const suggestions = document.getElementById("suggestions");
const prevPageBtn = document.getElementById("prevPageBtn");
const nextPageBtn = document.getElementById("nextPageBtn");
const pageNumbers = document.getElementById("pageNumbers");
const paginationInfo = document.getElementById("paginationInfo");
const paginationContainer = document.getElementById("paginationContainer");

// DOM Elements: Structural Table & Organism Checkbox Filter
const structTableWrapper = document.getElementById("structTableWrapper");
const structRowCountTip = document.getElementById("structRowCountTip");
const structMinAlnScore = document.getElementById("structMinAlnScore");
const structExportBtn = document.getElementById("structExportBtn");
const structResetBtn = document.getElementById("structResetBtn");
const structPrevPageBtn = document.getElementById("structPrevPageBtn");
const structNextPageBtn = document.getElementById("structNextPageBtn");
const structPageNumbers = document.getElementById("structPageNumbers");
const structPaginationInfo = document.getElementById("structPaginationInfo");
const structPaginationContainer = document.getElementById("structPaginationContainer");
const checkAllOrganismsBtn = document.getElementById("checkAllOrganismsBtn");
const uncheckAllOrganismsBtn = document.getElementById("uncheckAllOrganismsBtn");
const organismSelectCounter = document.getElementById("organismSelectCounter");

// Modal Elements
const geneModal = document.getElementById("geneModal");
const modalClose = document.getElementById("modalClose");
const modalGeneTitle = document.getElementById("modalGeneTitle");
const ensemblGeneLink = document.getElementById("ensemblGeneLink");
const modalSummary = document.getElementById("modalSummary");
const bioGrid = document.getElementById("bioGrid");
const modalOrthBody = document.getElementById("modalOrthBody");
const fetchEnsemblBtn = document.getElementById("fetchEnsemblBtn");
const ensemblInfo = document.getElementById("ensemblInfo");
const treeView = document.getElementById("treeView");
const geneStory = document.getElementById("geneStory");

// Unified Search Triggers across Both Tables
let debounceTimer;
searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        currentPage = 1;
        structCurrentPage = 1;
        fetchWorkspaceData();
        fetchStructData();
        fetchSuggestions();
    }, 250);
});

searchInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") {
        currentPage = 1;
        structCurrentPage = 1;
        fetchWorkspaceData();
        fetchStructData();
        if (inlineSuggestions) inlineSuggestions.style.display = "none";
    }
});

document.addEventListener("click", (e) => {
    if (inlineSuggestions && !e.target.closest(".banner-search-box-wrapper")) {
        inlineSuggestions.style.display = "none";
    }
});

if (bannerSearchBtn) {
    bannerSearchBtn.addEventListener("click", () => {
        currentPage = 1;
        structCurrentPage = 1;
        fetchWorkspaceData();
        fetchStructData();
        if (inlineSuggestions) inlineSuggestions.style.display = "none";
    });
}

// -------------------------------------------------------------
// Orthology Logic
// -------------------------------------------------------------
speciesFilter.addEventListener("change", () => {
    currentPage = 1;
    fetchWorkspaceData();
    updateSpeciesActiveState();
});

minConserved.addEventListener("change", () => {
    currentPage = 1;
    fetchWorkspaceData();
});

clearFilterBtn.addEventListener("click", () => {
    searchInput.value = "";
    speciesFilter.value = "all";
    minConserved.value = "0";
    suggestionBox.style.display = "none";
    if (inlineSuggestions) inlineSuggestions.style.display = "none";
    currentPage = 1;
    structCurrentPage = 1;
    fetchWorkspaceData();
    fetchStructData();
    updateSpeciesActiveState();
});

exportBtn.addEventListener("click", () => {
    const q = encodeURIComponent(searchInput.value.trim());
    const sp = encodeURIComponent(speciesFilter.value);
    const mc = encodeURIComponent(minConserved.value);
    window.location.href = `?action=export&q=${q}&species=${sp}&min_conserved=${mc}`;
});

document.querySelectorAll(".species-badge-item").forEach(card => {
    card.addEventListener("click", () => {
        const target = card.dataset.species;
        speciesFilter.value = (speciesFilter.value === target) ? 'all' : target;
        currentPage = 1;
        fetchWorkspaceData();
        updateSpeciesActiveState();
    });
});

function updateSpeciesActiveState() {
    const activeVal = speciesFilter.value;
    document.querySelectorAll(".species-badge-item").forEach(card => {
        card.classList.toggle("active", card.dataset.species === activeVal);
    });
}

async function fetchWorkspaceData() {
    tableWrapper.innerHTML = `
        <div class="empty-placeholder">
            <div class="custom-loader-spinner mx-auto"></div>
            <p class="text-muted fw-bold mt-2">Querying orthology database...</p>
        </div>
    `;

    const q = searchInput.value.trim();
    const species = speciesFilter.value;
    const minConsVal = minConserved.value;

    try {
        const res = await fetch(`?action=search&q=${encodeURIComponent(q)}&species=${encodeURIComponent(species)}&min_conserved=${minConsVal}&page=${currentPage}`);
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(jsonErr) {
            throw new Error("Invalid response from server: " + text.substring(0, 100));
        }
        
        if (result.status === 'success') {
            filteredData = result.data || [];
            totalEntries = result.pagination?.total || 0;
            totalPages = result.pagination?.pages || 1;
            renderWorkspaceTable();
            renderPaginationControls();
        } else {
            throw new Error(result.message || "Failed to load data.");
        }
    } catch (e) {
        tableWrapper.innerHTML = `
            <div class="empty-placeholder">
                <i class="fas fa-triangle-exclamation text-danger"></i>
                <p>Failed to retrieve data. ${escapeHtml(e.message)}</p>
            </div>
        `;
    }
}

async function fetchSuggestions() {
    const q = searchInput.value.trim();
    if (q.length < 2) {
        suggestionBox.style.display = "none";
        if (inlineSuggestions) inlineSuggestions.style.display = "none";
        return;
    }

    try {
        const res = await fetch(`?action=suggestions&q=${encodeURIComponent(q)}`);
        const result = await res.json();
        if (result.status === 'success' && result.data && result.data.length > 0) {
            suggestionBox.style.display = "block";
            suggestions.innerHTML = "";

            if (inlineSuggestions) {
                inlineSuggestions.innerHTML = "";
                inlineSuggestions.style.display = "block";
            }

            result.data.forEach(item => {
                const button = document.createElement("button");
                button.className = "suggestion-chip-btn";
                button.textContent = `${item.Gene_Name} (${item.HGNC_ID})`;
                button.addEventListener("click", () => {
                    searchInput.value = item.Gene_Name;
                    suggestionBox.style.display = "none";
                    if (inlineSuggestions) inlineSuggestions.style.display = "none";
                    currentPage = 1;
                    structCurrentPage = 1;
                    fetchWorkspaceData();
                    fetchStructData();
                });
                suggestions.appendChild(button);

                if (inlineSuggestions) {
                    const link = document.createElement("a");
                    link.href = "#";
                    link.className = "suggestion-item-link";
                    link.innerHTML = `<span class="symbol">${escapeHtml(item.Gene_Name)}</span> <span class="meta-tip">${escapeHtml(item.HGNC_ID)}</span> <span class="meta-tip">${escapeHtml(item.HumanEnsembl)}</span>`;
                    link.addEventListener("click", (e) => {
                        e.preventDefault();
                        searchInput.value = item.Gene_Name;
                        if (inlineSuggestions) inlineSuggestions.style.display = "none";
                        suggestionBox.style.display = "none";
                        currentPage = 1;
                        structCurrentPage = 1;
                        fetchWorkspaceData();
                        fetchStructData();
                    });
                    inlineSuggestions.appendChild(link);
                }
            });
        } else {
            suggestionBox.style.display = "none";
            if (inlineSuggestions) inlineSuggestions.style.display = "none";
        }
    } catch (err) {
        suggestionBox.style.display = "none";
        if (inlineSuggestions) inlineSuggestions.style.display = "none";
    }
}

function renderWorkspaceTable() {
    rowCountTip.textContent = `${totalEntries.toLocaleString()} items found`;

    if (filteredData.length === 0) {
        tableWrapper.innerHTML = `
            <div class="empty-placeholder">
                <i class="fas fa-magnifying-glass"></i>
                <p>No matches encountered in targeted search query.</p>
            </div>
        `;
        return;
    }

    let html = `<table class="dynamic-table">
        <thead>
            <tr>
                <th style="width: 65px;">S.No.</th>
                <th>Gene Name</th>
                <th>HGNC ID</th>
                <th>Human Ensembl</th>`;
    
    SPECIES_CONFIG.forEach(sp => {
        html += `<th>${sp.icon} ${escapeHtml(sp.label)}</th>`;
    });
    
    html += `</tr>
        </thead>
        <tbody>`;

    filteredData.forEach((row, index) => {
        const serialNumber = ((currentPage - 1) * 100) + index + 1;
        html += `<tr data-idx="${index}" title="Click to view detailed profile for ${escapeHtml(row.Gene_Name)}">
            <td class="text-muted fw-semibold">${serialNumber}</td>
            <td>
                <a href="javascript:void(0)" class="gene-title-link">
                    ${escapeHtml(row.Gene_Name)}
                    <i class="fas fa-arrow-up-right-from-square gene-link-icon"></i>
                </a>
            </td>
            <td class="hgnc-bold">${escapeHtml(row.HGNC_ID)}</td>
            <td class="hgnc-bold">${escapeHtml(row.HumanEnsembl)}</td>`;

        SPECIES_KEYS.forEach(key => {
            const val = row[key];
            if (val === "No ortholog" || !val) {
                html += `<td class="no-orth-style">No ortholog</td>`;
            } else {
                const parts = val.split(';');
                if (parts.length > 1) {
                    html += `<td class="multi-orth-style" title="${escapeHtml(val)}">${escapeHtml(parts[0])} (+${parts.length - 1})</td>`;
                } else {
                    html += `<td>${escapeHtml(val)}</td>`;
                }
            }
        });

        html += `</tr>`;
    });

    html += `</tbody></table>`;
    tableWrapper.innerHTML = html;

    tableWrapper.querySelectorAll("tbody tr").forEach(tr => {
        tr.addEventListener("click", () => {
            const idx = tr.dataset.idx;
            const item = filteredData[idx];
            if (item) showGeneDetail(item);
        });
    });
}

function renderPaginationControls() {
    if (totalPages <= 1) {
        paginationContainer.style.display = "none";
        return;
    }
    paginationContainer.style.display = "flex";

    const startIdx = ((currentPage - 1) * 100) + 1;
    const endIdx = Math.min(currentPage * 100, totalEntries);
    paginationInfo.textContent = `Showing ${startIdx.toLocaleString()} to ${endIdx.toLocaleString()} of ${totalEntries.toLocaleString()} entries`;

    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = currentPage === totalPages;

    let numHtml = "";
    const range = 2;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
            numHtml += `<button class="p-btn ${i === currentPage ? 'btn-primary text-white bg-primary' : ''}" onclick="changePage(${i})">${i}</button> `;
        } else if (i === currentPage - range - 1 || i === currentPage + range + 1) {
            numHtml += `<span style="padding:0 4px;color:#bbb;">...</span>`;
        }
    }
    pageNumbers.innerHTML = numHtml;
}

window.changePage = function(p) {
    currentPage = p;
    fetchWorkspaceData();
};

prevPageBtn.addEventListener("click", () => {
    if (currentPage > 1) {
        currentPage--;
        fetchWorkspaceData();
    }
});

nextPageBtn.addEventListener("click", () => {
    if (currentPage < totalPages) {
        currentPage++;
        fetchWorkspaceData();
    }
});

// -------------------------------------------------------------
// Structural Table (Combined By Gene) Logic & Multi-Organisms
// -------------------------------------------------------------
function getSelectedOrganisms() {
    const checkboxes = document.querySelectorAll(".organism-checkbox:checked");
    const totalCheckboxes = document.querySelectorAll(".organism-checkbox").length;
    
    if (checkboxes.length === totalCheckboxes) {
        organismSelectCounter.textContent = "All Selected";
        return "all";
    }
    if (checkboxes.length === 0) {
        organismSelectCounter.textContent = "None Selected";
        return "none";
    }
    organismSelectCounter.textContent = `${checkboxes.length} of ${totalCheckboxes} Selected`;
    return Array.from(checkboxes).map(cb => cb.value).join(",");
}

document.querySelectorAll(".organism-checkbox").forEach(cb => {
    cb.addEventListener("change", () => {
        structCurrentPage = 1;
        fetchStructData();
    });
});

checkAllOrganismsBtn.addEventListener("click", () => {
    document.querySelectorAll(".organism-checkbox").forEach(cb => cb.checked = true);
    structCurrentPage = 1;
    fetchStructData();
});

uncheckAllOrganismsBtn.addEventListener("click", () => {
    document.querySelectorAll(".organism-checkbox").forEach(cb => cb.checked = false);
    structCurrentPage = 1;
    fetchStructData();
});

structMinAlnScore.addEventListener("change", () => {
    structCurrentPage = 1;
    fetchStructData();
});

structResetBtn.addEventListener("click", () => {
    document.querySelectorAll(".organism-checkbox").forEach(cb => cb.checked = true);
    structMinAlnScore.value = "0";
    searchInput.value = "";
    currentPage = 1;
    structCurrentPage = 1;
    fetchWorkspaceData();
    fetchStructData();
});

structExportBtn.addEventListener("click", () => {
    const q = encodeURIComponent(searchInput.value.trim());
    const taxnames = encodeURIComponent(getSelectedOrganisms());
    const ms = encodeURIComponent(structMinAlnScore.value);
    window.location.href = `?action=export_struct&q=${q}&taxnames=${taxnames}&min_aln_score=${ms}`;
});

async function fetchStructData() {
    structTableWrapper.innerHTML = `
        <div class="empty-placeholder">
            <div class="custom-loader-spinner mx-auto"></div>
            <p class="text-muted fw-bold mt-2">Querying PostgreSQL struct_tb (combining gene structures)...</p>
        </div>
    `;

    const q = searchInput.value.trim();
    const taxnames = getSelectedOrganisms();
    const minScore = structMinAlnScore.value;

    if (taxnames === "none") {
        structData = [];
        structTotalEntries = 0;
        structTotalPages = 1;
        renderStructTable();
        renderStructPaginationControls();
        return;
    }

    try {
        const res = await fetch(`?action=search_struct&q=${encodeURIComponent(q)}&taxnames=${encodeURIComponent(taxnames)}&min_aln_score=${minScore}&page=${structCurrentPage}`);
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(jsonErr) {
            throw new Error("Invalid PostgreSQL response: " + text.substring(0, 100));
        }
        
        if (result.status === 'success') {
            structData = result.data || [];
            structTotalEntries = result.pagination?.total || 0;
            structTotalPages = result.pagination?.pages || 1;
            renderStructTable();
            renderStructPaginationControls();
        } else {
            throw new Error(result.message || "Failed to load PostgreSQL data.");
        }
    } catch (e) {
        structTableWrapper.innerHTML = `
            <div class="empty-placeholder">
                <i class="fas fa-triangle-exclamation text-danger"></i>
                <p>Failed to retrieve structural records. ${escapeHtml(e.message)}</p>
            </div>
        `;
    }
}

function renderStructTable() {
    structRowCountTip.textContent = `${structTotalEntries.toLocaleString()} genes found`;

    if (!structData || structData.length === 0) {
        structTableWrapper.innerHTML = `
            <div class="empty-placeholder">
                <i class="fas fa-magnifying-glass"></i>
                <p>No structural alignment matches found for "${escapeHtml(searchInput.value.trim())}".</p>
            </div>
        `;
        return;
    }

    let html = `<table class="dynamic-table">
        <thead>
            <tr>
                <th style="width: 60px;">S.No.</th>
                <th style="width: 180px;">Gene Symbol</th>
                <th style="width: 140px;">HGNC ID</th>
                <th style="width: 160px;">Human Uniprot</th>
                <th>Target Organisms &amp; Models</th>
                <th style="text-align: center; width: 140px;">3D Alignment</th>
            </tr>
        </thead>
        <tbody>`;

    structData.forEach((row, index) => {
        const serialNumber = ((structCurrentPage - 1) * 40) + index + 1;
        const geneSymbol = row.gene_symbol || 'Uncharacterized';
        const targets = row.targets || [];

        // 1. Group Organisms and Count Multiple Models
        const orgMap = {};
        targets.forEach(t => {
            const orgName = t.common_tax || t.taxname || 'Unknown';
            if (!orgMap[orgName]) {
                orgMap[orgName] = { count: 0, targetIds: [] };
            }
            orgMap[orgName].count++;
            if (t.target) orgMap[orgName].targetIds.push(t.target);
        });

        // 2. Generate Clean, Compact Pill Tags e.g. "Rat - 3", "Mouse - 2", "Fruit Fly - 1"
        let orgPillsHtml = '';
        for (const [orgName, item] of Object.entries(orgMap)) {
            const tooltip = `${orgName} (${item.count} model${item.count > 1 ? 's' : ''}): ${item.targetIds.join(', ')}`;
            orgPillsHtml += `
                <span class="species-pill-tag" title="${escapeHtml(tooltip)}">
                    <strong>${escapeHtml(orgName)}</strong>
                    <span class="count-chip">${item.count}</span>
                </span>
            `;
        }

        html += `<tr data-struct-idx="${index}" title="Click to launch 3D Superposition for all ${targets.length} model(s)">
            <td class="text-muted fw-semibold">${serialNumber}</td>
            <td>
                <span class="fw-bold text-primary">${escapeHtml(geneSymbol)}</span>
            </td>
            <td><span class="hgnc-bold">${escapeHtml(row.hgnc_id || 'N/A')}</span></td>
            <td><span class="fw-bold text-dark font-monospace">${escapeHtml(row.query || 'N/A')}</span></td>
            <td><div class="d-flex flex-wrap align-items-center">${orgPillsHtml}</div></td>
            <td style="text-align: center;">
                <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 struct-view-btn" data-idx="${index}" style="font-size: 0.8rem; font-weight: 600;">
                    <i class="fas fa-cubes me-1"></i> Multi-3D
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table>`;
    structTableWrapper.innerHTML = html;

    structTableWrapper.querySelectorAll("tbody tr").forEach(tr => {
        tr.addEventListener("click", () => {
            const idx = tr.dataset.structIdx;
            const item = structData[idx];
            if (item) open3DMultiStructureModal(item);
        });
    });
}

function renderStructPaginationControls() {
    if (structTotalPages <= 1) {
        structPaginationContainer.style.display = "none";
        return;
    }
    structPaginationContainer.style.display = "flex";

    const startIdx = ((structCurrentPage - 1) * 40) + 1;
    const endIdx = Math.min(structCurrentPage * 40, structTotalEntries);
    structPaginationInfo.textContent = `Showing ${startIdx.toLocaleString()} to ${endIdx.toLocaleString()} of ${structTotalEntries.toLocaleString()} genes`;

    prevPageBtn.disabled = structCurrentPage === 1;
    nextPageBtn.disabled = structCurrentPage === structTotalPages;

    let numHtml = "";
    const range = 2;
    for (let i = 1; i <= structTotalPages; i++) {
        if (i === 1 || i === structTotalPages || (i >= structCurrentPage - range && i <= structCurrentPage + range)) {
            numHtml += `<button class="p-btn ${i === structCurrentPage ? 'btn-primary text-white bg-primary' : ''}" onclick="changeStructPage(${i})">${i}</button> `;
        } else if (i === structCurrentPage - range - 1 || i === structCurrentPage + range + 1) {
            numHtml += `<span style="padding:0 4px;color:#bbb;">...</span>`;
        }
    }
    structPageNumbers.innerHTML = numHtml;
}

window.changeStructPage = function(p) {
    structCurrentPage = p;
    fetchStructData();
};

prevPageBtn.addEventListener("click", () => {
    if (structCurrentPage > 1) {
        structCurrentPage--;
        fetchStructData();
    }
});

nextPageBtn.addEventListener("click", () => {
    if (currentPage < totalPages) {
        currentPage++;
        fetchWorkspaceData();
    }
});

// -------------------------------------------------------------
// Combined Multi-Species 3D Superposition Viewer (NGL Engine)
// -------------------------------------------------------------
function ensureNGLInit() {
    if (!nglStage) {
        nglStage = new NGL.Stage("nglViewport", { backgroundColor: "#090d16" });
        window.addEventListener("resize", () => {
            if (nglStage) nglStage.handleResize();
        });
    }
}

async function open3DMultiStructureModal(geneRow) {
    const modal = document.getElementById("nglModal");
    const spinner = document.getElementById("nglLoadingSpinner");
    const spinnerText = document.getElementById("nglSpinnerText");
    const listContainer = document.getElementById("nglStructureList");
    const modelCounter = document.getElementById("nglModelCounter");
    const themeBtn = document.getElementById("nglThemeToggleBtn");

    const geneName = geneRow.gene_symbol || 'Gene';
    const humanUniProt = geneRow.query || 'N/A';
    const targets = geneRow.targets || [];

    document.getElementById("nglModalTitle").textContent = `${geneName} (${geneRow.hgnc_id || 'HGNC'}) - Multi-Species 3D Superposition`;
    document.getElementById("nglModalSubtitle").textContent = `Homo sapiens (${humanUniProt}) superimposed with ${targets.length} target organism structure(s)`;
    modelCounter.textContent = `${targets.length + 1}`;

    modal.classList.add("active");
    spinner.style.display = "block";
    spinnerText.textContent = `Loading ${humanUniProt} reference & ${targets.length} ortholog structures...`;
    listContainer.innerHTML = '';

    // Reset background to dark theme default
    isLightBg = false;
    if (themeBtn) themeBtn.innerHTML = '<i class="fas fa-sun me-1"></i> Light BG';
    document.getElementById("nglViewport").style.background = "radial-gradient(circle at center, #1e293b 0%, #090d16 100%)";

    ensureNGLInit();
    nglStage.setParameters({ backgroundColor: "#090d16" });
    nglStage.removeAllComponents();
    humanComp = null;
    targetComps = [];

    // 1. Render Human Reference Card in Sidebar
    const humanColor = "#0d6efd";
    let sidebarHtml = `
        <div class="ngl-legend-card" style="border-left: 5px solid ${humanColor};">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-primary" style="font-size:0.82rem;"><i class="fas fa-circle me-1" style="color:${humanColor}"></i> Human (Query Ref)</span>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input struct-toggle" type="checkbox" id="toggle_human" checked data-type="human">
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                <span>UniProt: <strong>${escapeHtml(humanUniProt)}</strong></span>
                <a href="?action=get_pdb&type=query&id=${encodeURIComponent(humanUniProt)}" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem;" download><i class="fas fa-download me-1"></i>PDB</a>
            </div>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm struct-style-select" data-type="human" style="font-size:0.78rem;">
                    <option value="cartoon" selected>Cartoon</option>
                    <option value="surface">Surface</option>
                    <option value="backbone">Backbone</option>
                    <option value="licorice">Licorice</option>
                </select>
            </div>
        </div>
    `;

    // 2. Add Placeholders for each Target in Sidebar
    targets.forEach((t, idx) => {
        const tColor = DISTINCT_COLORS[idx % DISTINCT_COLORS.length];
        const orgName = t.common_tax || t.taxname;
        sidebarHtml += `
            <div class="ngl-legend-card" id="card_target_${idx}" style="border-left: 5px solid ${tColor};">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold" style="color: ${tColor}; font-size:0.82rem;"><i class="fas fa-circle me-1" style="color:${tColor}"></i> ${escapeHtml(orgName)}</span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input struct-toggle" type="checkbox" id="toggle_target_${idx}" checked data-target-idx="${idx}">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                    <span>ID: <strong>${escapeHtml(t.target)}</strong></span>
                    <a href="?action=get_pdb&type=target&id=${encodeURIComponent(t.target)}" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:0.75rem;" download><i class="fas fa-download me-1"></i>PDB</a>
                </div>
                <div class="d-flex justify-content-between align-items-center small mb-2 bg-white p-1 px-2 rounded border">
                    <span class="text-muted">Aln TM-score:</span>
                    <strong class="text-primary">${parseFloat(t.alntmscore || 0).toFixed(4)}</strong>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm struct-style-select" data-target-idx="${idx}" style="font-size:0.78rem;">
                        <option value="cartoon" selected>Cartoon</option>
                        <option value="surface">Surface</option>
                        <option value="backbone">Backbone</option>
                        <option value="licorice">Licorice</option>
                    </select>
                </div>
            </div>
        `;
    });

    listContainer.innerHTML = sidebarHtml;
    bindSidebarEvents();

    try {
        // 3. Load Human Reference Structure First
        const humanUrl = `?action=get_pdb&type=query&id=${encodeURIComponent(humanUniProt)}`;
        humanComp = await nglStage.loadFile(humanUrl, { ext: "pdb", defaultRepresentation: false });
        humanComp.addRepresentation("cartoon", { color: humanColor, opacity: 1.0, quality: "high" });

        // 4. Concurrently / Sequentially Load and Superimpose all Target Models
        for (let i = 0; i < targets.length; i++) {
            const t = targets[i];
            const tColor = DISTINCT_COLORS[i % DISTINCT_COLORS.length];
            const tUrl = `?action=get_pdb&type=target&id=${encodeURIComponent(t.target)}`;

            try {
                const comp = await nglStage.loadFile(tUrl, { ext: "pdb", defaultRepresentation: false });

                // Superimpose directly onto Human Reference component
                if (humanComp && comp) {
                    try {
                        if (typeof comp.superpose === "function") {
                            comp.superpose(humanComp, true, "polymer and protein", "polymer and protein");
                        } else if (NGL.Superposition) {
                            const sp = new NGL.Superposition(humanComp.structure, comp.structure, true);
                            sp.transform(comp.structure);
                        }
                    } catch (supErr) {
                        try {
                            if (typeof comp.superpose === "function") {
                                comp.superpose(humanComp, false);
                            }
                        } catch (fallbackErr) {
                            console.warn(`Superimposing fallback notice for ${t.target}:`, fallbackErr);
                        }
                    }
                }

                comp.addRepresentation("cartoon", { color: tColor, opacity: 0.85, quality: "high" });

                targetComps.push({
                    comp: comp,
                    target: t.target,
                    taxname: t.taxname,
                    color: tColor,
                    idx: i
                });
            } catch (targetErr) {
                console.warn(`Could not load target PDB: ${t.target}`, targetErr);
            }
        }

        nglStage.autoView(800);
    } catch (err) {
        alert("Notice: Could not load human reference coordinates. " + (err.message || err));
    } finally {
        spinner.style.display = "none";
    }
}

function bindSidebarEvents() {
    // Structure Visibility Toggles
    document.querySelectorAll(".struct-toggle").forEach(chk => {
        chk.addEventListener("change", (e) => {
            if (e.target.dataset.type === "human") {
                if (humanComp) humanComp.setVisibility(e.target.checked);
            } else {
                const tIdx = parseInt(e.target.dataset.targetIdx);
                const item = targetComps.find(x => x.idx === tIdx);
                if (item && item.comp) item.comp.setVisibility(e.target.checked);
            }
        });
    });

    // Style Selectors
    document.querySelectorAll(".struct-style-select").forEach(sel => {
        sel.addEventListener("change", (e) => {
            const style = e.target.value;
            if (e.target.dataset.type === "human") {
                if (humanComp) {
                    humanComp.removeAllRepresentations();
                    humanComp.addRepresentation(style, { color: "#0d6efd" });
                }
            } else {
                const tIdx = parseInt(e.target.dataset.targetIdx);
                const item = targetComps.find(x => x.idx === tIdx);
                if (item && item.comp) {
                    item.comp.removeAllRepresentations();
                    item.comp.addRepresentation(style, { color: item.color });
                }
            }
        });
    });
}

// Global Superposition Modal Controls
document.getElementById("nglModalClose").addEventListener("click", () => {
    document.getElementById("nglModal").classList.remove("active");
});

document.getElementById("nglShowAllBtn").addEventListener("click", () => {
    if (humanComp) humanComp.setVisibility(true);
    targetComps.forEach(tc => tc.comp && tc.comp.setVisibility(true));
    document.querySelectorAll(".struct-toggle").forEach(chk => chk.checked = true);
});

document.getElementById("nglHideAllBtn").addEventListener("click", () => {
    if (humanComp) humanComp.setVisibility(false);
    targetComps.forEach(tc => tc.comp && tc.comp.setVisibility(false));
    document.querySelectorAll(".struct-toggle").forEach(chk => chk.checked = false);
});

document.getElementById("nglResetCamBtn").addEventListener("click", () => {
    if (nglStage) nglStage.autoView(600);
});

document.getElementById("nglSpinToggleBtn").addEventListener("click", () => {
    isSpinning = !isSpinning;
    if (nglStage) nglStage.setSpin(isSpinning);
});

// Dark/Light 3D Background Toggle Event
document.getElementById("nglThemeToggleBtn").addEventListener("click", () => {
    isLightBg = !isLightBg;
    const bgCol = isLightBg ? "#f8fafc" : "#090d16";
    if (nglStage) {
        nglStage.setParameters({ backgroundColor: bgCol });
    }
    document.getElementById("nglViewport").style.background = isLightBg ? "#f8fafc" : "radial-gradient(circle at center, #1e293b 0%, #090d16 100%)";
    document.getElementById("nglThemeToggleBtn").innerHTML = isLightBg 
        ? '<i class="fas fa-moon me-1"></i> Dark BG' 
        : '<i class="fas fa-sun me-1"></i> Light BG';
});

document.getElementById("nglScreenshotBtn").addEventListener("click", () => {
    if (nglStage) {
        nglStage.makeImage({ factor: 2, antialias: true, trim: false }).then(blob => {
            NGL.download(blob, "multi-species-structural-alignment.png");
        });
    }
});

// -------------------------------------------------------------
// Chart Renderers (Both Ortholog & PostgreSQL struct_tb Charts)
// -------------------------------------------------------------
function renderCharts() {
    // 1. Ortholog Frequency Chart: Clean text labels without icons
    if (document.getElementById("barChart")) {
        const mappedSpeciesData = SPECIES_CONFIG.map(sp => {
            return {
                label: sp.label, // Clean text label without emoji icon
                count: GLOBAL_STATS?.species_counts?.[sp.key] || 0,
                color: sp.color
            };
        });

        mappedSpeciesData.sort((a, b) => b.count - a.count);

        const speciesLabels = mappedSpeciesData.map(item => item.label);
        const speciesCounts = mappedSpeciesData.map(item => item.count);
        const colors = mappedSpeciesData.map(item => item.color);

        const barCtx = document.getElementById("barChart").getContext("2d");
        if (barChartInstance) barChartInstance.destroy();
        barChartInstance = new Chart(barCtx, {
            type: "bar",
            data: {
                labels: speciesLabels,
                datasets: [{
                    label: "Identified Orthologs",
                    data: speciesCounts,
                    backgroundColor: colors.map(c => c + "CC"),
                    borderColor: colors,
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'nearest', intersect: true },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(33, 37, 41, 0.95)',
                        titleFont: { size: 13, weight: 'bold', family: "'Segoe UI', sans-serif" },
                        bodyFont: { size: 12, family: "'Segoe UI', sans-serif" },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const count = context.parsed.y ?? context.raw ?? 0;
                                return ` Orthologs: ${Number(count).toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { 
                        ticks: { 
                            maxRotation: 40, 
                            minRotation: 20, 
                            font: { 
                                size: 10,
                                family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                            } 
                        } 
                    }
                }
            }
        });
    }

    // 2. Structural Chart: Matches EXACT color theme of Ortholog Chart & Clear Visibility
    if (document.getElementById("structBarChart") && STRUCT_CHART_DATA && STRUCT_CHART_DATA.length > 0) {
        const structLabels = STRUCT_CHART_DATA.map(item => getCommonName(item.taxname));
        const structCounts = STRUCT_CHART_DATA.map(item => parseInt(item.count, 10) || 0);
        const structAvgScores = STRUCT_CHART_DATA.map(item => parseFloat(item.avg_score) || 0.0);

        // Map each organism directly to its assigned SPECIES_CONFIG color
        const structColors = structLabels.map(labelName => {
            return SPECIES_COLOR_MAP[labelName] || '#0284c7';
        });

        const structCtx = document.getElementById("structBarChart").getContext("2d");
        if (structBarChartInstance) structBarChartInstance.destroy();
        structBarChartInstance = new Chart(structCtx, {
            type: "bar",
            data: {
                labels: structLabels,
                datasets: [{
                    label: "Structural Alignments",
                    data: structCounts,
                    backgroundColor: structColors.map(c => c + "CC"),
                    borderColor: structColors,
                    borderWidth: 1.5,
                    borderRadius: 6,
                    minBarLength: 5 // Guarantees clear visibility for small quantities (e.g. Baboon)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'nearest', intersect: true },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(33, 37, 41, 0.95)',
                        titleFont: { size: 13, weight: 'bold', family: "'Segoe UI', sans-serif" },
                        bodyFont: { size: 12, family: "'Segoe UI', sans-serif" },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const count = context.parsed.y ?? context.raw ?? 0;
                                return ` Alignments: ${Number(count).toLocaleString()}`;
                            },
                            afterLabel: function(context) {
                                const idx = context.dataIndex;
                                const scoreVal = (structAvgScores && idx in structAvgScores && !isNaN(structAvgScores[idx])) ? Number(structAvgScores[idx]).toFixed(4) : '0.0000';
                                return ` Avg TM-score: ${scoreVal}`;
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { 
                        ticks: { 
                            maxRotation: 40, 
                            minRotation: 20, 
                            font: { 
                                size: 10,
                                family: "'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif"
                            } 
                        } 
                    }
                }
            }
        });
    }
}

function showGeneDetail(gene) {
    const humanId = gene.HumanEnsembl;
    const conservation = parseInt(gene._conservation);
    const totalOrth = parseInt(gene._orthologTotal);
    const speciesWithout = SPECIES_CONFIG.length - conservation;

    modalGeneTitle.textContent = `${gene.Gene_Name} (HGNC: ${gene.HGNC_ID})`;
    ensemblGeneLink.href = `https://www.ensembl.org/id/${encodeURIComponent(humanId)}`;

    modalSummary.innerHTML = `
        <div class="profile-card-item">
            <div class="item-lbl">Gene Symbol</div>
            <div class="item-val">${escapeHtml(gene.Gene_Name)}</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Total Mapped Entries</div>
            <div class="item-val">${totalOrth}</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Species with Orthologs</div>
            <div class="item-val">${conservation} / ${SPECIES_CONFIG.length}</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Absent Alignments</div>
            <div class="item-val">${speciesWithout}</div>
        </div>
    `;

    const groupings = { Primate: 0, Mammal: 0, Vertebrate: 0, Invertebrate: 0 };
    SPECIES_CONFIG.forEach(sp => {
        const raw = gene[sp.key];
        if (raw && raw !== "No ortholog") {
            groupings[sp.group] = (groupings[sp.group] || 0) + 1;
        }
    });

    bioGrid.innerHTML = `
        <div class="profile-card-item">
            <div class="item-lbl">Evolutionary Preservation</div>
            <div class="item-val">${getConservationLabel(conservation)}</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Primate Alignment</div>
            <div class="item-val">${groupings.Primate} species</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Mammalian Divergence</div>
            <div class="item-val">${groupings.Mammal} species</div>
        </div>
        <div class="profile-card-item">
            <div class="item-lbl">Invertebrate / Non-Mammal</div>
            <div class="item-val">${groupings.Vertebrate + groupings.Invertebrate} species</div>
        </div>
    `;

    geneStory.textContent = compileBioStory(gene, conservation, totalOrth, groupings);
    treeView.textContent = buildTreeOutput(gene);
    renderModalOrthologsTable(gene);

    ensemblInfo.innerHTML = `<span class="loading text-muted">Trigger the search button to initiate REST querying.</span>`;
    fetchEnsemblBtn.disabled = false;
    fetchEnsemblBtn.onclick = () => fetchEnsemblInfo(humanId);

    geneModal.classList.add("active");
}

function getConservationLabel(conservation) {
    if (conservation >= 8) return "Broad Evolutionary Preservation";
    if (conservation >= 5) return "Moderate Conservation Signal";
    if (conservation >= 2) return "Restricted Taxonomy Pattern";
    return "Minimal Taxonomy Mapping";
}

function compileBioStory(gene, conservation, total, groupings) {
    if (conservation === 0) {
        return `Human Gene ${gene.Gene_Name} does not exhibit standard ortholog entries across current database records.`;
    }
    let story = `The genomic structure ${gene.Gene_Name} is aligned across ${conservation} tracked organisms. `;
    if (groupings.Primate >= 3) {
        story += "Conservation patterns in primates suggest consensus configurations within near-human ancestors. ";
    }
    if (groupings.Invertebrate > 0) {
        story += "Retention in invertebrates indicates ancient evolutionary foundations. ";
    }
    story += `In total, ${total} matches were cataloged, pointing to ${conservation >= 6 ? 'high baseline conservation' : 'specialized genetic development'}.`;
    return story;
}

function buildTreeOutput(gene) {
    const lines = [`${gene.Gene_Name} (Human)`];
    SPECIES_CONFIG.forEach((sp, idx) => {
        const val = gene[sp.key];
        const isLast = idx === SPECIES_CONFIG.length - 1;
        const branch = isLast ? "â””â”€â”€ " : "â”œâ”€â”€ ";
        
        if (!val || val === "No ortholog") {
            lines.push(`${branch}${sp.icon} ${sp.label} : (No ortholog mapped)`);
        } else {
            lines.push(`${branch}${sp.icon} ${sp.label}`);
            const realParts = val.split(';').map(p => p.trim());
            realParts.forEach((p, pIdx) => {
                const subBranch = pIdx === realParts.length - 1 ? "    â””â”€â”€ " : "    â”œâ”€â”€ ";
                lines.push(`${subBranch}${p}`);
            });
        }
    });
    return lines.join("\n");
}

function renderModalOrthologsTable(gene) {
    let html = "";
    SPECIES_CONFIG.forEach(sp => {
        const val = gene[sp.key];
        const hasOrth = val && val !== "No ortholog";
        let cellContent = "";

        if (hasOrth) {
            const parts = val.split(';').map(p => p.trim());
            cellContent = parts.map(p => {
                const url = makeExternalLink(p);
                return url ? `<div class="orth-link" style="margin-bottom: 4px;"><a href="${url}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">${escapeHtml(p)}</a></div>` : `<div>${escapeHtml(p)}</div>`;
            }).join("");
        } else {
            cellContent = `<span class="no-orth-style">No ortholog</span>`;
        }

        html += `<tr>
            <td>${sp.icon} ${escapeHtml(sp.label)}</td>
            <td>${cellContent}</td>
            <td>${escapeHtml(sp.group)}</td>
            <td style="font-weight:600; color:${hasOrth ? '#198754' : '#6c757d'};">${hasOrth ? 'Aligned' : 'Divergent'}</td>
        </tr>`;
    });
    modalOrthBody.innerHTML = html;
}

function makeExternalLink(id) {
    if (!id) return null;
    if (id.startsWith("ENS")) return `https://www.ensembl.org/id/${encodeURIComponent(id)}`;
    if (id.startsWith("FBgn")) return `https://flybase.org/reports/${encodeURIComponent(id)}`;
    if (id.startsWith("WBGene")) return `https://wormbase.org/species/c_elegans/gene/${encodeURIComponent(id)}`;
    return null;
}

async function fetchEnsemblInfo(ensgId) {
    fetchEnsemblBtn.disabled = true;
    ensemblInfo.innerHTML = `<span class="loading"><i class="fas fa-spinner fa-spin"></i> Querying rest.ensembl.org...</span>`;

    try {
        const response = await fetch("https://rest.ensembl.org/lookup/id/" + encodeURIComponent(ensgId) + "?content-type=application/json");
        if (!response.ok) throw new Error("External service returned " + response.status);
        const data = await response.json();

        const name = data.display_name || data.gene_name || "Unassigned";
        const desc = data.description || "No public functional description currently indexed.";
        const region = data.seq_region_name + ":" + data.start + "-" + data.end + " (" + (data.strand === 1 ? '+' : '-') + ")";
        
        ensemblInfo.innerHTML = "<div><strong>Gene Symbol:</strong> <span class=\"text-primary\" style=\"font-weight:700;\">" + escapeHtml(name) + "</span></div>" +
            "<div style=\"margin-top:4px;\"><strong>Description:</strong> " + escapeHtml(desc) + "</div>" +
            "<div style=\"margin-top:4px;\"><strong>Locus Coordinate:</strong> " + escapeHtml(region) + "</div>" +
            "<div style=\"margin-top:4px;\"><strong>Gene Type:</strong> " + escapeHtml(data.biotype || "Unknown") + "</div>" +
            "<div style=\"margin-top:4px;\"><strong>Assembly:</strong> " + escapeHtml(data.assembly_name || "N/A") + "</div>";
    } catch (e) {
        ensemblInfo.innerHTML = "<div class=\"text-danger\" style=\"font-weight:600;\"><i class=\"fas fa-exclamation-triangle\"></i> REST Lookup failed: " + escapeHtml(e.message) + "</div>";
    } finally {
        fetchEnsemblBtn.disabled = false;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

modalClose.addEventListener("click", () => geneModal.classList.remove("active"));
geneModal.addEventListener("click", (e) => {
    if (e.target === geneModal) geneModal.classList.remove("active");
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        if (geneModal.classList.contains("active")) geneModal.classList.remove("active");
        if (document.getElementById("nglModal").classList.contains("active")) document.getElementById("nglModal").classList.remove("active");
    }
});

// App Initiation
document.addEventListener("DOMContentLoaded", () => {
    fetchWorkspaceData();
    fetchStructData();
    renderCharts();
});
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>
