<?php
// download_handler.php

// 1. Include database connection file
require_once 'conn.php'; 

// Disable error reporting & timeouts to support large streaming transfers
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');

// Flush output buffers to support direct chunked streaming
while (ob_get_level()) {
    ob_end_clean();
}

// 2. Detect MySQL database connection from global scope
$db_connection = null;
$possible_vars = ['conn', 'db', 'con', 'pdo', 'link'];

foreach ($possible_vars as $var) {
    if (isset($GLOBALS[$var])) {
        $db_connection = $GLOBALS[$var];
        break;
    }
}

// 3. Resource Mapping for Dynamic MySQL Tables (with fallback aliases)
$allowed_tables = [
    'gene_annotation'       => 'Overall_Genes_Data',
    'main_tb'               => 'Overall_Genes_Data',
    'disease_edges_tb'      => 'Disease_Disease_Association_Data',
    'Disease_tb'            => 'Gene_Disease_Association_Data',
    'tissue_wise_tb_v2'     => 'Tissue_Specific_Index_Data',
    'tissue_wise_tb'        => 'Tissue_Specific_Index_Data',
    'gene_wise_tb_v2'       => 'Gene_Expression_Tissue_Data',
    'gene_wise_tb'          => 'Gene_Expression_Tissue_Data',
    'alphamissense_metric'  => 'Alphamissense_Per_Gene_Metric',
    'Prot_string_with_HGNC' => 'Protein_Protein_Interaction_Data',
    'ppi_tb'                => 'Protein_Protein_Interaction_Data',
    'ppi_module_new'        => 'PPI_Modules_Data',
    'ppi_cd_tb'             => 'PPI_Modules_Data',
    'tf_tb'                 => 'Transcription_Factor_Binding_Sites',
    'circust_new'           => 'Rhythmicity_Circust_Data',
    'circust_data'          => 'Rhythmicity_Circust_Data'
];

// 4. Resource Mapping for Static & Parquet Files
$allowed_files = [
    // --- Static Features ---
    'alphafold_pos' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/alphafold_position_wise.tsv',
        'name' => 'alphafold_position_wise.tsv'
    ],
    'residue_llps' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/Residue_wise_llps.tsv',
        'name' => 'Residue_wise_llps.tsv'
    ],
    'merged_ptm' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/Merged_PTM.tsv',
        'name' => 'Merged_PTM.tsv'
    ],
    'iupred_idrs' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/iupred_output_idrs.zip',
        'name' => 'iupred_output_idrs.zip'
    ],
    'global_centrality' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/Global_centrality.zip',
        'name' => 'Global_centrality_data.zip'
    ],
    'gff_files' => [
        'path' => '/var/www/html/anshu/circanet/Download_File/GFF.zip',
        'name' => 'GFF.zip'
    ],

    // --- Chromosome-Wise Parquet Files ---
    'clinvar_chr1'  => ['path' => '/srv/postgresql/imports/parquet/master_chr1.parquet',  'name' => 'master_chr1.parquet'],
    'clinvar_chr2'  => ['path' => '/srv/postgresql/imports/parquet/master_chr2.parquet',  'name' => 'master_chr2.parquet'],
    'clinvar_chr3'  => ['path' => '/srv/postgresql/imports/parquet/master_chr3.parquet',  'name' => 'master_chr3.parquet'],
    'clinvar_chr4'  => ['path' => '/srv/postgresql/imports/parquet/master_chr4.parquet',  'name' => 'master_chr4.parquet'],
    'clinvar_chr5'  => ['path' => '/srv/postgresql/imports/parquet/master_chr5.parquet',  'name' => 'master_chr5.parquet'],
    'clinvar_chr6'  => ['path' => '/srv/postgresql/imports/parquet/master_chr6.parquet',  'name' => 'master_chr6.parquet'],
    'clinvar_chr7'  => ['path' => '/srv/postgresql/imports/parquet/master_chr7.parquet',  'name' => 'master_chr7.parquet'],
    'clinvar_chr8'  => ['path' => '/srv/postgresql/imports/parquet/master_chr8.parquet',  'name' => 'master_chr8.parquet'],
    'clinvar_chr9'  => ['path' => '/srv/postgresql/imports/parquet/master_chr9.parquet',  'name' => 'master_chr9.parquet'],
    'clinvar_chr10' => ['path' => '/srv/postgresql/imports/parquet/master_chr10.parquet', 'name' => 'master_chr10.parquet'],
    'clinvar_chr11' => ['path' => '/srv/postgresql/imports/parquet/master_chr11.parquet', 'name' => 'master_chr11.parquet'],
    'clinvar_chr12' => ['path' => '/srv/postgresql/imports/parquet/master_chr12.parquet', 'name' => 'master_chr12.parquet'],
    'clinvar_chr13' => ['path' => '/srv/postgresql/imports/parquet/master_chr13.parquet', 'name' => 'master_chr13.parquet'],
    'clinvar_chr14' => ['path' => '/srv/postgresql/imports/parquet/master_chr14.parquet', 'name' => 'master_chr14.parquet'],
    'clinvar_chr15' => ['path' => '/srv/postgresql/imports/parquet/master_chr15.parquet', 'name' => 'master_chr15.parquet'],
    'clinvar_chr16' => ['path' => '/srv/postgresql/imports/parquet/master_chr16.parquet', 'name' => 'master_chr16.parquet'],
    'clinvar_chr17' => ['path' => '/srv/postgresql/imports/parquet/master_chr17.parquet', 'name' => 'master_chr17.parquet'],
    'clinvar_chr18' => ['path' => '/srv/postgresql/imports/parquet/master_chr18.parquet', 'name' => 'master_chr18.parquet'],
    'clinvar_chr19' => ['path' => '/srv/postgresql/imports/parquet/master_chr19.parquet', 'name' => 'master_chr19.parquet'],
    'clinvar_chr20' => ['path' => '/srv/postgresql/imports/parquet/master_chr20.parquet', 'name' => 'master_chr20.parquet'],
    'clinvar_chr21' => ['path' => '/srv/postgresql/imports/parquet/master_chr21.parquet', 'name' => 'master_chr21.parquet'],
    'clinvar_chr22' => ['path' => '/srv/postgresql/imports/parquet/master_chr22.parquet', 'name' => 'master_chr22.parquet'],
    'clinvar_chrX'  => ['path' => '/srv/postgresql/imports/parquet/master_chrX.parquet',  'name' => 'master_chrX.parquet'],
    'clinvar_chrY'  => ['path' => '/srv/postgresql/imports/parquet/master_chrY.parquet',  'name' => 'master_chrY.parquet'],
    'clinvar_chrM'  => ['path' => '/srv/postgresql/imports/parquet/master_chrM.parquet',  'name' => 'master_chrM.parquet'],
];

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id     = isset($_GET['id'])     ? $_GET['id']     : '';

if (empty($action) || empty($id)) {
    die("Error: Invalid download request parameters.");
}

switch ($action) {
    case 'table':
        if (!array_key_exists($id, $allowed_tables)) {
            die("Error: Unauthorized table key.");
        }
        if (!$db_connection) {
            die("Error: Active database connection variable could not be detected from conn.php.");
        }
        downloadTableAsCSV($id, $allowed_tables[$id], $db_connection);
        break;

    case 'file':
        if (!array_key_exists($id, $allowed_files)) {
            die("Error: Unauthorized file key.");
        }
        downloadStaticFile($allowed_files[$id]['path'], $allowed_files[$id]['name']);
        break;

    default:
        die("Error: Unsupported download action.");
}

/**
 * Streams a MySQL table to CSV using unbuffered output.
 */
function downloadTableAsCSV($tableName, $filenamePrefix, $db) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenamePrefix . '_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    if ($db instanceof mysqli) {
        $columnsResult = $db->query("SHOW COLUMNS FROM `$tableName`");
        $headers = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $headers[] = $col['Field'];
            }
            fputcsv($output, $headers);
        }

        $query = "SELECT * FROM `$tableName`";
        $result = $db->query($query, MYSQLI_USE_RESULT);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            $result->free();
        }
    } elseif ($db instanceof PDO) {
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        
        $stmt = $db->query("DESCRIBE `$tableName`");
        $headers = [];
        if ($stmt) {
            while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $headers[] = $col['Field'];
            }
            fputcsv($output, $headers);
        }

        $stmt = $db->query("SELECT * FROM `$tableName`", PDO::FETCH_ASSOC);
        if ($stmt) {
            foreach ($stmt as $row) {
                fputcsv($output, $row);
            }
        }
    } else {
        die("Error: Unsupported connection driver (requires mysqli or PDO).");
    }

    fclose($output);
    exit;
}

/**
 * Downloads files safely in 8 MB chunks (prevents memory limits on files > 2 GB).
 */
function downloadStaticFile($filePath, $downloadName) {
    if (!file_exists($filePath)) {
        http_response_code(404);
        die("Error: The requested file is not available on this server ($downloadName).");
    }
    
    $filesize = filesize($filePath);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    if ($filesize !== false && $filesize > 0) {
        header('Content-Length: ' . $filesize);
    }
    
    // Chunked read (8MB per iteration) to protect PHP RAM
    $chunkSize = 8 * 1024 * 1024;
    $handle = fopen($filePath, 'rb');
    
    if ($handle === false) {
        die("Error: Unable to open file for streaming.");
    }

    while (!feof($handle)) {
        echo fread($handle, $chunkSize);
        flush();
    }

    fclose($handle);
    exit;
}