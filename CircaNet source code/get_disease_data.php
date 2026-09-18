<?php
// get_disease_data.php

header('Content-Type: application/json');
require_once 'conn.php';

$table_sql = "SELECT HGNC_ID, Approved_symbol, diseaseName, MONDO, Source FROM Disease_tb ORDER BY Approved_symbol ASC";
$result = $conn->query($table_sql);

$data = [];
$serial_number = 1;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // We structure the data as a simple array for each row
        $data[] = [
            $serial_number++,
            htmlspecialchars($row["HGNC_ID"]),
            htmlspecialchars($row["Approved_symbol"]),
            htmlspecialchars($row["diseaseName"]),
            htmlspecialchars($row["MONDO"]),
            htmlspecialchars($row["Source"])
        ];
    }
}

$conn->close();

// DataTables expects the data in a specific JSON format: { "data": [...] }
echo json_encode(['data' => $data]);
?>