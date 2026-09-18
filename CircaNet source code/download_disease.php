<?php
// download_disease.php

// 1. Include the database connection
require_once 'conn.php';

// 2. Set HTTP headers to trigger a file download
$filename = "Disease_data_" . date('Y-m-d') . ".tsv";
header('Content-Type: text/tab-separated-values; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Open the output stream
$output = fopen('php://output', 'w');

// 4. Define and write the header row to the TSV file
$headers = array('HGNC ID', 'Approved symbol', 'diseaseName', 'MONDO', 'Source');
fputcsv($output, $headers, "\t");

// 5. Fetch all data from the database
$query = "SELECT HGNC_ID, Approved_symbol, diseaseName, MONDO, Source FROM Disease_tb ORDER BY HGNC_ID ASC";
$result = $conn->query($query);

// 6. Loop through the data and write each row to the TSV file
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        // --- THIS IS THE MODIFIED LINE ---
        // Modify the HGNC_ID value in the array before writing it to the file
        $row['HGNC_ID'] = str_replace('HGNC:', '', $row['HGNC_ID']);
        // ---------------------------------

        // The fputcsv function correctly handles formatting for TSV
        fputcsv($output, $row, "\t");
    }
}

// 7. Close the connection and stop the script
$conn->close();
exit();
?>