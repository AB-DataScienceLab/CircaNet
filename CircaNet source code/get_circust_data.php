<?php
header('Content-Type: application/json; charset=utf-8');

// Include your existing database connection/credential file
include 'conn.php';

/**
 * Identify connection variables.
 * If your conn.php creates a PDO instance named $conn, we assign it to $pdo.
 */
if (isset($conn) && $conn instanceof PDO) {
    $pdo = $conn;
}

/**
 * Fallback: If $pdo is not defined by conn.php, we attempt to establish 
 * a PDO connection using variables that may have been imported from conn.php.
 */
if (!isset($pdo)) {
    // Map common naming conventions for database credentials
    $db_host = isset($host) ? $host : (isset($servername) ? $servername : 'localhost');
    $db_user = isset($user) ? $user : (isset($username) ? $username : '');
    $db_pass = isset($pass) ? $pass : (isset($password) ? $password : '');
    $db_name = isset($db) ? $db : (isset($dbname) ? $dbname : '');
    $charset = 'utf8mb4';

    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } catch (\PDOException $e) {
         echo json_encode([
             'status' => 'error',
             'message' => 'Database connection failed: ' . $e->getMessage()
         ]);
         exit;
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. Fetch Distinct Tissues
if ($action === 'tissues') {
    try {
        $stmt = $pdo->query("SELECT DISTINCT tissue FROM circust_new WHERE tissue IS NOT NULL AND tissue != '' ORDER BY tissue ASC");
        $tissues = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'status' => 'success',
            'data' => $tissues
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// 2. Fetch Rhythmic Genes for a Selected Tissue
if ($action === 'genes') {
    $tissue = isset($_GET['tissue']) ? $_GET['tissue'] : '';
    if (empty($tissue)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Tissue parameter is required'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT 
            `S.No.` AS serial, 
            tissue, 
            Gene_Symbol, 
            Mesor, 
            Amplitude, 
            Relative_Amplitude, 
            Acrophase_Rad, 
            R_Squared, 
            P_Value, 
            FDR_q, 
            hgnc_id 
            FROM circust_new 
            WHERE tissue = ? 
            ORDER BY Gene_Symbol ASC");
        $stmt->execute([$tissue]);
        $genes = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $genes
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Standard fallback response
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action requested'
]);