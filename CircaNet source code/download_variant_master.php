<?php
// download_variant_master.php
require_once 'db_connect.php';

set_time_limit(180);
@pg_query($dbconn, "SET statement_timeout = 60000");

$symbol  = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';
$clnsig  = isset($_GET['clnsig']) ? trim($_GET['clnsig']) : '';
$amClass = isset($_GET['amClass']) ? trim($_GET['amClass']) : '';

$tableName = 'master_variants';

$clnsig_groups = [
    'Pathogenic/Likely Pathogenic' => [
        'Pathogenic', 'Pathogenic/Likely_pathogenic', 'Likely_pathogenic',
        'Likely_pathogenic/Likely_pathogenic,_low_penetrance', 'Likely_pathogenic,_low_penetrance',
        'Pathogenic/Likely_pathogenic,_low_penetrance', 'Pathogenic/Likely_pathogenic|other',
        'Pathogenic/Likely_pathogenic/Pathogenic,_low_penetrance', 'Pathogenic/Likely_pathogenic|risk_factor',
        'Pathogenic,_low_penetrance', 'Pathogenic/Pathogenic,_low_penetrance', 'Pathogenic|risk_factor'
    ],
    'Benign/Likely_Benign' => [
        'Benign', 'Benign|association', 'Benign/Likely_benign', 'Benign/Likely_benign|other',
        'Benign|other', 'Benign|protective', 'Benign|risk_factor', 'Likely_benign', 'Likely_benign|association'
    ],
    'VUS' => [
        'Uncertain_significance', 'Uncertain_risk_allele', 'Uncertain_significance/Uncertain_risk_allele',
        'VUS-high', 'Conflicting_classifications_of_pathogenicity',
        'Conflicting_classifications_of_pathogenicity|other', 'Conflicting_classifications_of_pathogenicity|risk_factor'
    ],
    'Other' => [
        'Affects', 'association', 'drug_response', 'Established_risk_allele', 'Likely_risk_allele',
        'no_classification_for_the_single_variant', 'no_classifications_from_unflagged_records',
        'not_provided', 'other', 'protective', 'protective|risk_factor', 'risk_factor'
    ]
];

$whereClauses = [];
$params = [];
$pIdx = 1;

if (!empty($symbol)) {
    $whereClauses[] = "(entrez_gene_symbol = $" . $pIdx++ . " OR entrez_gene_symbol = $" . $pIdx++ . ")";
    $params[] = $symbol;
    $params[] = strtoupper($symbol);
}

if (!empty($clnsig)) {
    if (isset($clnsig_groups[$clnsig])) {
        $mapped_values = $clnsig_groups[$clnsig];
        $inPlaceholders = [];
        foreach ($mapped_values as $mVal) {
            $inPlaceholders[] = '$' . $pIdx++;
            $params[] = $mVal;
        }
        $whereClauses[] = "clnsig IN (" . implode(', ', $inPlaceholders) . ")";
    } else {
        $whereClauses[] = "clnsig = $" . $pIdx++;
        $params[] = $clnsig;
    }
}

if (!empty($amClass)) {
    $whereClauses[] = "(am_class = $" . $pIdx++ . " OR am_class ILIKE $" . $pIdx++ . ")";
    $params[] = $amClass;
    $params[] = $amClass;
}

$fullWhere = "";
if (!empty($whereClauses)) {
    $fullWhere = "WHERE " . implode(' AND ', $whereClauses);
}

$filename = "variant_master_export_" . date('Ymd_His') . ".tsv";

header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

$headers = [
    'id', 'chrom', 'pos', 'ref', 'alt', 'rs', 'entrez_gene_symbol', 'entrez_gene',
    'consequence', 'varianttype', 'clnsig', 'clndn', 'clnrevstat', 'acmgclassification', 
    'evo2_score', 'evo2_prediction', 'uniprot_id', 'am_class', 'am_pathogenicity', 
    'hgvsg', 'hgvsc', 'hgvsp', 'aachange', 'transcript', 'af_joint_raw', 'af_joint'
];

fwrite($output, implode("\t", $headers) . "\n");

$sql = "SELECT * FROM $tableName $fullWhere ORDER BY chrom ASC, pos ASC LIMIT 500000";
$res = @pg_query_params($dbconn, $sql, $params);

if ($res) {
    while ($row = pg_fetch_assoc($res)) {
        $line = [];
        foreach ($headers as $col) {
            $val = $row[$col] ?? 'NA';
            if ($val === '' || $val === null || $val === '.') {
                $val = 'NA';
            }
            $val = str_replace(["\r", "\n", "\t"], ' ', $val);
            $line[] = $val;
        }
        fwrite($output, implode("\t", $line) . "\n");
    }
}

fclose($output);
exit;
?>