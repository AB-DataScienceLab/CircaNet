<?php
require_once 'conn.php';

$pathwayFilter = isset($_GET['pathway']) ? $conn->real_escape_string($_GET['pathway']) : '';
$searchFilter = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename="pathway_data_export.tsv"');

$output = fopen('php://output', 'w');
fwrite($output, "Gene ID\tIdentifier\tURL\tPathway Name\tEvidence Code\tOrganism\n");

$sql = "SELECT * FROM Pathway_tb WHERE 1=1";
if ($pathwayFilter) $sql .= " AND Pathway_Name = '$pathwayFilter'";
if ($searchFilter) $sql .= " AND (Pathway_Name LIKE '%$searchFilter%' OR GeneID LIKE '%$searchFilter%')";

$sql .= " ORDER BY (CASE WHEN (GeneID IS NULL OR GeneID = '') THEN 1 ELSE 0 END) ASC, Pathway_Name ASC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $clean = function($v) { return (empty($v) || $v == NULL) ? "NA" : $v; };
    $line = [
        $clean($row['GeneID']),
        $clean($row['Identifier']),
        $clean($row['URL']),
        $clean($row['Pathway_Name']),
        $clean($row['Evidence_code']),
        "Homo sapiens"
    ];
    fwrite($output, implode("\t", $line) . "\n");
}
fclose($output);
exit;