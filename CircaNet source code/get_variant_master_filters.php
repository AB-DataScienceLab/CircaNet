<?php
// get_variant_master_filters.php
require_once 'db_connect.php';

header('Content-Type: application/json');
error_reporting(0);

if (!isset($dbconn) || !$dbconn) {
    echo json_encode([]);
    exit;
}

$field = $_GET['field'] ?? 'SYMBOL';
$options = [];

if (strtoupper($field) === 'SYMBOL') {
    // Fast gene symbol list from gene_lookup table (< 1ms)
    $sql = "SELECT entrez_gene_symbol AS val FROM gene_lookup ORDER BY entrez_gene_symbol ASC";
    $res = @pg_query($dbconn, $sql);
    if ($res) {
        while ($row = pg_fetch_assoc($res)) {
            if (!empty($row['val'])) {
                $options[] = ['value' => $row['val'], 'text' => $row['val']];
            }
        }
    }
} elseif (strtoupper($field) === 'CLNSIG') {
    // 4 Grouped ClinVar Options
    $options = [
        ['value' => 'Pathogenic/Likely Pathogenic', 'text' => 'Pathogenic / Likely Pathogenic'],
        ['value' => 'Benign/Likely_Benign',         'text' => 'Benign / Likely Benign'],
        ['value' => 'VUS',                          'text' => 'Uncertain Significance (VUS)'],
        ['value' => 'Other',                        'text' => 'Other / Conflicting / Risk Factor']
    ];
} elseif ($field === 'am_class') {
    $options = [
        ['value' => 'likely_pathogenic', 'text' => 'likely pathogenic'],
        ['value' => 'likely_benign',     'text' => 'likely benign'],
        ['value' => 'ambiguous',         'text' => 'ambiguous']
    ];
}

echo json_encode($options);
?>