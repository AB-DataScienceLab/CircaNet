<?php
// get_filter_options.php
require_once 'conn.php';

error_reporting(0);
header('Content-Type: application/json');

// Performance Tuning: Prevent timeouts and memory depletion when loading massive filter datasets
ini_set('memory_limit', '1024M'); 
set_time_limit(240); 

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
}

$field = isset($_GET['field']) ? trim($_GET['field']) : '';
$table = isset($_GET['table']) ? trim($_GET['table']) : 'genetic_variants';

// Secure whitelist for both tables and fields to allow frontend cross-compatibility
$allowed_fields = ['SYMBOL', 'CLNSIG', 'am_class', 'Approved_symbol'];
$allowed_tables = ['genetic_variants', 'Disease_tb', 'disease_tb'];

if (!in_array($field, $allowed_fields) || !in_array($table, $allowed_tables)) {
    echo json_encode([]);
    exit;
}

// Cache Version updated to 'v2' to automatically bypass existing limited cache files on your server
$cache_filename = 'circanet_filter_cache_v2_' . strtolower($table) . '_' . strtolower($field) . '.json';
$cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cache_filename;
$cache_time = 86400; // 24 hours

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    $cached_data = @file_get_contents($cache_file);
    if ($cached_data) {
        echo $cached_data;
        exit;
    }
}

$response = [];
try {
    $field_escaped = "`" . $conn->real_escape_string($field) . "`";
    $table_escaped = "`" . $conn->real_escape_string($table) . "`";
    
    // Query fetching all records without SQL LIMIT configurations
    $sql = "SELECT DISTINCT $field_escaped as val 
            FROM $table_escaped 
            WHERE TRIM($field_escaped) IS NOT NULL 
              AND TRIM($field_escaped) != '' 
              AND TRIM($field_escaped) != '.' 
              AND TRIM($field_escaped) != 'NA'
            ORDER BY val ASC";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $val = trim($row['val']);
            if ($val === '.' || $val === 'NA' || $val === '' || empty($val)) {
                continue;
            }
            $response[] = [
                'value' => $val,
                'text' => str_replace('_', ' ', $val)
            ];
        }
        @file_put_contents($cache_file, json_encode($response));
    }
} catch (Exception $e) {
    error_log("Error in get_filter_options.php: " . $e->getMessage());
}

echo json_encode($response);
?>