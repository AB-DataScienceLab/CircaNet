<?php
// Prevent execution timeout & enable output buffering
set_time_limit(120);
ini_set('memory_limit', '512M');
ob_start();

// Include database connection
include 'db_connect.php';

// Bridge $dbconn from db_connect.php to $conn
if (!isset($conn) && isset($dbconn)) {
    $conn = $dbconn;
}

$tableName = 'master_variants';

// ---------------- SESSION-LEVEL TUNING ----------------
// NOTE: these session settings only help once the query itself can use an
// index. They do NOT fix a leading-wildcard LIKE '%term%' scan - see the
// accompanying indexes.sql for the real fix.
if ($conn) {
    @pg_query($conn, "SET statement_timeout = 15000");        // 15s ceiling (was 5s)
    @pg_query($conn, "SET max_parallel_workers_per_gather = 4");
    @pg_query($conn, "SET work_mem = '64MB'");
}

// ---------------- DB HELPER FUNCTIONS ----------------
function execute_db_query($conn, $sql, $params = []) {
    if (is_resource($conn) || (is_object($conn) && get_class($conn) === 'PgSql\Connection')) {
        if (!empty($params)) {
            $pIndex = 1;
            $sqlPg = preg_replace_callback('/\?/', function() use (&$pIndex) {
                return '$' . $pIndex++;
            }, $sql);
            return @pg_query_params($conn, $sqlPg, $params);
        } else {
            return @pg_query($conn, $sql);
        }
    } elseif ($conn instanceof PDO) {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    return false;
}

function fetch_all_assoc($result) {
    if (is_resource($result) || (is_object($result) && get_class($result) === 'PgSql\Result')) {
        return pg_fetch_all($result) ?: [];
    } elseif ($result instanceof PDOStatement) {
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function escape_db_str($conn, $str) {
    if (is_resource($conn) || (is_object($conn) && get_class($conn) === 'PgSql\Connection')) {
        return pg_escape_string($conn, $str);
    } elseif ($conn instanceof PDO) {
        return trim($conn->quote($str), "'");
    }
    return addslashes($str);
}

// ---------------- DISPLAY / OPTION MAPPINGS (mirrors the frontend) ----------------
$field_display_names = [
    'entrez_gene_symbol' => 'Gene Symbol',
    'rs'                 => 'RSID',
    'chrom'               => 'Chromosome',
    'consequence'        => 'Variant Consequence',
    'varianttype'        => 'Variant Type',
    'clnsig'             => 'ClinVar Significance',
    'acmgclassification' => 'ACMG Classification',
    'evo2_prediction'    => 'Evo2 Prediction',
    'am_class'           => 'AlphaMissense Class',
    'hgvsp'              => 'HGVS Protein',
    'hgvsc'              => 'HGVS Coding',
    'uniprot_id'         => 'UniProt ID',
    'associateddiseases' => 'Associated Diseases',
    'af_joint'           => 'Joint Allele Frequency'
];

// Columns that carry a real B-Tree index (see indexes.sql). Equality / prefix
// matches on these stay fast no matter how big the table gets.
$indexed_fields = ['entrez_gene_symbol', 'rs', 'hgvsp'];

// Free-text columns that only support fast substring search once a pg_trgm
// GIN index exists (see indexes.sql). Listed here purely for clarity.
$trigram_fields = ['consequence', 'varianttype', 'hgvsc', 'uniprot_id', 'associateddiseases'];

$clnsig_groups = [
    'Pathogenic/Likely Pathogenic' => ['Pathogenic', 'Likely_pathogenic', 'Pathogenic/Likely_pathogenic', 'Pathogenic|Affects', 'Pathogenic/Likely_pathogenic|risk_factor', 'Pathogenic|other', 'Likely_pathogenic|association'],
    'Benign/Likely Benign'         => ['Benign', 'Likely_benign', 'Benign/Likely_benign', 'Likely_benign|association', 'Benign/Likely_benign|other', 'Benign|risk_factor', 'Benign|association'],
    'VUS'                          => ['Uncertain_significance', 'Uncertain_significance|risk_factor', 'VUS-high', 'VUS-mid', 'VUS-low', 'Uncertain_risk_allele'],
    'Conflict'                     => ['Conflicting_classifications_of_pathogenicity', 'Conflicting_classifications_of_pathogenicity|other', 'Conflicting_classifications_of_pathogenicity|risk_factor'],
    'other'                        => ['not_provided', 'drug_response', 'association', 'risk_factor', 'other', 'protective', 'confers_sensitivity']
];

$clnsig_options   = ['Pathogenic/Likely Pathogenic' => 'Pathogenic / Likely Pathogenic', 'Benign/Likely Benign' => 'Benign / Likely Benign', 'VUS' => 'Uncertain Significance (VUS)', 'Conflict' => 'Conflicting Classifications', 'other' => 'Other / Risk Factor'];
$acmg_options     = ['Pathogenic' => 'Pathogenic', 'Likely Pathogenic' => 'Likely Pathogenic', 'Uncertain' => 'Uncertain Significance', 'Likely Benign' => 'Likely Benign', 'Benign' => 'Benign'];
$evo2_options     = ['Likely_Pathogenic' => 'Likely Pathogenic', 'Likely_Benign' => 'Likely Benign', 'Ambiguous' => 'Ambiguous'];
$am_class_options = ['likely_pathogenic' => 'Likely Pathogenic', 'likely_benign' => 'Likely Benign', 'ambiguous' => 'Ambiguous'];
$af_joint_options = ['rare_001' => 'Ultra Rare (AF < 0.001)', 'rare_01' => 'Rare (AF < 0.01)', 'common_01' => 'Common (AF >= 0.01)', 'non_zero' => 'Observed (AF > 0)'];

// ============================================================
// 1. AUTO-SUGGESTION API HANDLER
// ============================================================
if (isset($_GET['suggest']) && isset($_GET['field']) && isset($_GET['term'])) {
    ob_clean();
    header('Content-Type: application/json');

    $field = trim($_GET['field']);
    $term  = trim($_GET['term']);
    $suggestions = [];

    $allowed_suggest_fields = ['entrez_gene_symbol', 'rs', 'chrom', 'consequence', 'varianttype', 'hgvsp', 'hgvsc', 'uniprot_id'];

    if (in_array($field, $allowed_suggest_fields) && strlen($term) >= 2) {
        // Prefix-only match ('term%', no leading wildcard) so this can use a
        // plain index (varchar_pattern_ops / trigram) instead of a full scan.
        $like_term = $term . '%';

        if (in_array($field, $indexed_fields)) {
            $sql = "SELECT DISTINCT \"$field\" AS val FROM \"$tableName\" WHERE \"$field\" LIKE ? AND \"$field\" IS NOT NULL ORDER BY val ASC LIMIT 10";
            $stmt_res = execute_db_query($conn, $sql, [$like_term]);
        } else {
            $sql = "SELECT DISTINCT \"$field\" AS val FROM \"$tableName\" WHERE \"$field\" ILIKE ? AND \"$field\" IS NOT NULL ORDER BY val ASC LIMIT 10";
            $stmt_res = execute_db_query($conn, $sql, [$like_term]);
        }

        if ($stmt_res) {
            $rows = fetch_all_assoc($stmt_res);
            foreach ($rows as $row) {
                if (!empty($row['val'])) $suggestions[] = $row['val'];
            }
        }
    }

    echo json_encode($suggestions);
    exit;
}

// ============================================================
// 2. PARSE FILTER CRITERIA
// ============================================================
$fieldnames       = isset($_POST['fieldname']) ? $_POST['fieldname'] : [];
$operators        = isset($_POST['operator']) ? $_POST['operator'] : [];
$keywords         = isset($_POST['keyword']) ? $_POST['keyword'] : [];
$logicalOperators = isset($_POST['logical_operator']) ? $_POST['logical_operator'] : [];

$limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
$page   = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;
$offset = ($page - 1) * $limit;

$allowed_fields    = array_keys($field_display_names);
$allowed_operators = ['LIKE', 'NOT LIKE'];
$validConditions   = [];
$summaryParts      = [];
$has_chrom_filter  = false;

for ($i = 0; $i < count($fieldnames); $i++) {
    if (!isset($keywords[$i]) || (trim($keywords[$i]) === '' && $keywords[$i] !== '0')) continue;

    $field     = $fieldnames[$i];
    $operator  = $operators[$i] ?? 'LIKE';
    $logicalOp = ($i > 0) ? (isset($logicalOperators[$i - 1]) ? $logicalOperators[$i - 1] : 'AND') : '';

    if (!in_array($field, $allowed_fields) || !in_array($operator, $allowed_operators)) continue;

    $keyword_raw      = trim($keywords[$i]);
    $currentCondition = '';
    $readableKeyword  = '';

    if ($field === 'chrom') $has_chrom_filter = true;

    if (in_array($field, ['entrez_gene_symbol', 'rs', 'chrom', 'consequence', 'varianttype', 'hgvsp', 'hgvsc', 'uniprot_id', 'associateddiseases'])) {
        $columnName      = "\"" . escape_db_str($conn, $field) . "\"";
        $escaped_keyword = escape_db_str($conn, $keyword_raw);
        $sqlOp           = ($operator === 'LIKE') ? 'LIKE' : 'NOT LIKE';

        if (in_array($field, $indexed_fields) || $field === 'chrom') {
            // Prefix-or-exact match: index-friendly.
            $currentCondition = ($operator === 'LIKE')
                ? "($columnName = '$escaped_keyword' OR $columnName LIKE '$escaped_keyword%')"
                : "$columnName NOT LIKE '$escaped_keyword%'";
        } else {
            // Free-text substring match: needs a pg_trgm GIN index (see indexes.sql)
            // to avoid a sequential scan.
            $currentCondition = "$columnName $sqlOp '%$escaped_keyword%'";
        }
        $readableKeyword = htmlspecialchars($keyword_raw);
    }
    elseif ($field === 'clnsig') {
        if (isset($clnsig_groups[$keyword_raw])) {
            $mapped_values = $clnsig_groups[$keyword_raw];
            $escaped_values = array_map(function ($val) use ($conn) {
                return "'" . escape_db_str($conn, $val) . "'";
            }, $mapped_values);
            $inOp = ($operator === 'LIKE') ? 'IN' : 'NOT IN';
            $currentCondition = "\"clnsig\" $inOp (" . implode(',', $escaped_values) . ")";
            $readableKeyword = htmlspecialchars($clnsig_options[$keyword_raw] ?? $keyword_raw);
        } else {
            $currentCondition = "1=0";
            $readableKeyword = htmlspecialchars($keyword_raw);
        }
    }
    elseif ($field === 'acmgclassification') {
        $escaped_keyword = escape_db_str($conn, $keyword_raw);
        $sqlOp = ($operator === 'LIKE') ? '=' : '!=';
        $currentCondition = "\"acmgclassification\" $sqlOp '$escaped_keyword'";
        $readableKeyword = htmlspecialchars($acmg_options[$keyword_raw] ?? $keyword_raw);
    }
    elseif ($field === 'evo2_prediction') {
        $escaped_keyword = escape_db_str($conn, $keyword_raw);
        $sqlOp = ($operator === 'LIKE') ? '=' : '!=';
        $currentCondition = "\"evo2_prediction\" $sqlOp '$escaped_keyword'";
        $readableKeyword = htmlspecialchars($evo2_options[$keyword_raw] ?? $keyword_raw);
    }
    elseif ($field === 'am_class') {
        $escaped_keyword = escape_db_str($conn, $keyword_raw);
        $sqlOp = ($operator === 'LIKE') ? '=' : '!=';
        $currentCondition = "\"am_class\" $sqlOp '$escaped_keyword'";
        $readableKeyword = htmlspecialchars($am_class_options[$keyword_raw] ?? $keyword_raw);
    }
    elseif ($field === 'af_joint') {
        if ($keyword_raw === 'rare_001') $currentCondition = "\"af_joint\" < 0.001";
        elseif ($keyword_raw === 'rare_01') $currentCondition = "\"af_joint\" < 0.01";
        elseif ($keyword_raw === 'common_01') $currentCondition = "\"af_joint\" >= 0.01";
        elseif ($keyword_raw === 'non_zero') $currentCondition = "\"af_joint\" > 0";
        $readableKeyword = htmlspecialchars($af_joint_options[$keyword_raw] ?? $keyword_raw);
    }

    if (!empty($currentCondition)) {
        $validConditions[] = ['condition' => $currentCondition, 'logical' => $logicalOp];
        $readableField = "<strong>" . htmlspecialchars($field_display_names[$field]) . "</strong>";
        $summaryPiece = ($i > 0) ? " $logicalOp " : "";
        $readableOperator = ($operator === 'LIKE') ? 'MATCHES' : 'DOES NOT MATCH';
        $summaryPiece .= "$readableField $readableOperator <em>\"$readableKeyword\"</em>";
        $summaryParts[] = $summaryPiece;
    }
}

$whereClause = "";
$searchSummaryHtml = "";
if (!empty($validConditions)) {
    if (!empty($summaryParts)) {
        $searchSummaryHtml = '<div class="search-summary"><strong>You filtered by:</strong> ' . implode('', $summaryParts) . '</div>';
    }
    $queryParts = [$validConditions[0]['condition']];
    for ($i = 1; $i < count($validConditions); $i++) {
        $queryParts[] = $validConditions[$i]['logical'];
        $queryParts[] = $validConditions[$i]['condition'];
    }
    $whereClause = "WHERE " . implode(' ', $queryParts);
}

// Nudge users towards a chrom filter when the query is broad - on a table
// partitioned by chrom this lets Postgres prune to a single partition
// instead of scanning all 25.
$partitionHint = (!$has_chrom_filter && empty($validConditions))
    ? "Tip: add a Chromosome filter to search a single partition and get results faster."
    : null;

// ============================================================
// 3. TSV EXPORT HANDLER
// ============================================================
if (isset($_POST['download_tsv']) && $_POST['download_tsv'] == '1') {
    ob_clean();
    $downloadSql = "SELECT chrom, pos, ref, alt, rs, entrez_gene_symbol, hgvsp, hgvsc, varianttype, consequence,
                            clnsig, acmgclassification, evo2_prediction, evo2_score, am_class, am_pathogenicity, af_joint
                     FROM \"$tableName\" $whereClause LIMIT 2000";

    $download_res = execute_db_query($conn, $downloadSql);
    if (!$download_res) { die("Download query failed or timed out."); }

    header('Content-Type: text/tab-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="Variant_Search_Export_' . date('Y-m-d') . '.tsv"');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['Chrom', 'Pos', 'Ref', 'Alt', 'RSID', 'Gene Symbol', 'HGVS Protein', 'HGVS Coding', 'Variant Type', 'Consequence', 'ClinVar Significance', 'ACMG Classification', 'Evo2 Prediction', 'Evo2 Score', 'AM Class', 'AM Pathogenicity', 'Joint AF'], "\t");

    $rows = fetch_all_assoc($download_res);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['chrom'] ?? '', $row['pos'] ?? '', $row['ref'] ?? '', $row['alt'] ?? '',
            $row['rs'] ?? 'N/A', $row['entrez_gene_symbol'] ?? 'N/A', $row['hgvsp'] ?? 'N/A',
            $row['hgvsc'] ?? 'N/A', $row['varianttype'] ?? 'N/A', $row['consequence'] ?? 'N/A',
            $row['clnsig'] ?? 'N/A', $row['acmgclassification'] ?? 'N/A', $row['evo2_prediction'] ?? 'N/A',
            $row['evo2_score'] ?? 'N/A', $row['am_class'] ?? 'N/A', $row['am_pathogenicity'] ?? 'N/A',
            $row['af_joint'] ?? 'N/A'
        ], "\t");
    }
    fclose($output);
    exit;
}

// ============================================================
// 4. SINGLE-QUERY FAST PAGINATION (ZERO COUNT QUERY)
// ============================================================
ob_clean();
header('Content-Type: application/json');

// Fetch limit+1 rows so we can tell if there's a next page without a
// separate, expensive COUNT(*) query.
$fetchLimit = $limit + 1;

// Only ORDER BY when there's an indexed leading column to sort on - an
// ORDER BY over unindexed columns forces a full sort of the whole result
// set before the LIMIT can be applied.
$orderByClause = "ORDER BY entrez_gene_symbol ASC, pos ASC";

$sql = "SELECT chrom, pos, ref, alt, rs, entrez_gene_symbol, hgvsp, hgvsc, varianttype, consequence,
               clnsig, acmgclassification, evo2_prediction, evo2_score, am_class, am_pathogenicity, af_joint
        FROM \"$tableName\" $whereClause
        $orderByClause
        LIMIT $fetchLimit OFFSET $offset";

$results = execute_db_query($conn, $sql);

if ($results) {
    $rows = fetch_all_assoc($results);
    $hasNextPage = false;

    if (count($rows) > $limit) {
        $hasNextPage = true;
        array_pop($rows);
    }

    echo json_encode([
        'status' => 'success',
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'hasNextPage' => $hasNextPage,
            'hasPrevPage' => ($page > 1),
            'offset' => $offset
        ],
        'summaryHtml' => $searchSummaryHtml,
        'hint' => $partitionHint,
        'field_display_names' => $field_display_names
    ]);
} else {
    $db_error = is_resource($conn) ? pg_last_error($conn) : 'Execution error';

    if (strpos($db_error, 'canceling statement due to statement timeout') !== false) {
        $db_error = "Search timed out. Please narrow your filter (e.g. add a Chromosome or Gene Symbol filter) - see indexes.sql for a permanent fix.";
    }

    echo json_encode([
        'status' => 'error',
        'message' => $db_error
    ]);
}
exit;
?>