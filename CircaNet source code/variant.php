<?php
// variant.php

require_once 'db_connect.php';

if (!isset($dbconn) || !$dbconn) {
    die("Database connection failed. Please check db_connect.php connection parameters.");
}

// -------------------------------------------------------------------------
// Connect to secondary partitioned database (circanet_db_new)
// -------------------------------------------------------------------------
$new_db_name = 'circanet_db_new';

$pg_host = $host ?? $dbhost ?? $db_host ?? pg_host($dbconn);
$pg_port = $port ?? $dbport ?? $db_port ?? pg_port($dbconn);
$pg_user = $user ?? $username ?? $dbuser ?? $db_user ?? pg_parameter_status($dbconn, 'session_authorization');
$pg_pass = $password ?? $pass ?? $dbpass ?? $db_pass ?? '';

$conn_attempts = [];

// Attempt 1: Full connection string
$str1 = "dbname={$new_db_name}";
if (!empty($pg_host)) $str1 .= " host={$pg_host}";
if (!empty($pg_port)) $str1 .= " port={$pg_port}";
if (!empty($pg_user)) $str1 .= " user={$pg_user}";
if (!empty($pg_pass)) $str1 .= " password={$pg_pass}";
$conn_attempts[] = $str1;

// Attempt 2: Unix socket / local peer authentication
if (!empty($pg_user)) {
    $conn_attempts[] = "dbname={$new_db_name} user={$pg_user}";
}

// Attempt 3: Local fallback
$conn_attempts[] = "dbname={$new_db_name}";

// Attempt 4: Replace dbname in any connection string variable
foreach (['connection_string', 'conn_str', 'connString', 'db_conn_str'] as $v) {
    if (isset($$v) && is_string($$v)) {
        $conn_attempts[] = preg_replace('/dbname=\S+/', "dbname={$new_db_name}", $$v);
    }
}

$dbconn_new = false;
foreach ($conn_attempts as $conn_str) {
    $dbconn_new = @pg_connect($conn_str);
    if ($dbconn_new) {
        break;
    }
}

if ($dbconn_new) {
    @pg_query($dbconn_new, "SET statement_timeout = 5000; SET work_mem = '64MB';");
}

include 'header.php';

// --- CHART DATA CACHING (Instant sub-millisecond execution) ---
$cache_filename = 'circanet_variant_master_pathogenic_4tools_cache.json';
$cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cache_filename;
$cache_time = 86400 * 7; // Cache valid for 7 days

// Fixed X-Axis labels & fallback estimated counts
$chart_labels = ['AlphaMissense', 'EVO2', 'BIAS-ACMG', 'ClinVar'];
$chart_data = [1250000, 980000, 450000, 320000]; // Default placeholder counts
$use_cache = false;

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    $json_content = @file_get_contents($cache_file);
    if ($json_content) {
        $cached_content = json_decode($json_content, true);
        if ($cached_content && isset($cached_content['data']) && count($cached_content['data']) === 4) {
            $chart_data = $cached_content['data'];
            $use_cache = true;
        }
    }
}

if (!$use_cache) {
    try {
        $active_conn = $dbconn_new ?: $dbconn;
        @pg_query($active_conn, "SET statement_timeout = 3000"); // 3s safety limit
        
        // Fast summary lookup (from either circanet_db_new or circanet_db)
        $summary_sql = "SELECT alphamissense_count, evo2_count, acmg_count, clinvar_count 
                        FROM master_variants_pathogenic_summary WHERE id = 1 LIMIT 1";
        $summary_result = @pg_query($active_conn, $summary_sql);
        
        if (!$summary_result && $dbconn && $active_conn !== $dbconn) {
            $summary_result = @pg_query($dbconn, $summary_sql);
        }
        
        if ($summary_result && pg_num_rows($summary_result) > 0) {
            $row = pg_fetch_assoc($summary_result);
            $chart_data = [
                (int)$row['alphamissense_count'],
                (int)$row['evo2_count'],
                (int)$row['acmg_count'],
                (int)$row['clinvar_count']
            ];
            @file_put_contents($cache_file, json_encode(['data' => $chart_data]));
        }
    } catch (Exception $e) {
        error_log("Error in variant.php 4-tools chart query: " . $e->getMessage());
    }
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variant Browse | CircaNet</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
    .custom-wide-container {
        width: 100% !important;
        max-width: 98% !important;
        margin: 0 auto;
    }
    .chart-container { 
        position: relative; 
        height: 380px; 
        width: 100%; 
    }
    .filter-section { 
        background-color: #f8f9fa; 
        padding: 20px; 
        border-radius: 8px; 
        margin-bottom: 20px; 
        border: 1px solid #e9ecef;
    }
    .main-content { margin-bottom: 40px; }
    .column-selector-wrapper {
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .column-checkbox { margin-right: 20px; margin-bottom: 10px; }
    .column-selector-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .select-all-buttons { display: flex; gap: 10px; }
    
    .top-scrollbar-wrapper { width: 100%; overflow-x: auto; overflow-y: hidden; margin-bottom: 0px; border: none; }
    .top-scrollbar-inner { height: 20px; display: block; }

    .select2-container--default .select2-selection--single {
        height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        padding-left: 12px !important;
        color: #212529 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }

    .dataTables_scrollHead {
        overflow: hidden !important;
        position: relative !important;
        border: 0 !important;
        width: 100% !important;
    }
    .dataTables_scrollHeadInner {
        box-sizing: content-box !important;
    }

    /* Interactive DataTables Loader Overlay */
    .dataTables_wrapper {
        position: relative;
    }
    .dataTables_wrapper .dataTables_processing {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        background: rgba(255, 255, 255, 0.85);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border: none;
    }
    .custom-table-loader {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        padding: 24px 36px;
        border-radius: 8px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        border: 1px solid #dee2e6;
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
    </style>
</head>
<body>

<div class="custom-wide-container pb-4 pt-0"> 
    <div class="main-content">
        <div class="text-center mb-5">
            <h1 style="font-family: 'Segoe UI', sans-serif; font-size: 40px; font-weight: 700; color: #212529; margin-top: 0; padding-top: 0; margin-bottom: 5px;">
                Variant Browse
            </h1>
            <p style="font-family: 'Segoe UI', sans-serif; font-size: 20px; font-weight: 400; color: #212529; line-height: 1.5; margin-bottom: 0;">
                Explore variants in circadian-associated genes with integrated pathogenicity predictions, Nirvana annotation, AlphaMissense predictions, gnomAD annotations, EVO2 predictions, and BIAS-ACMG variant classifications generated using the BIAS framework.
            </p>
        </div>
    </div>

    <!-- Section 1: Pathogenic Variant Distribution Across Annotators -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h3 class="mb-0 fs-5 fw-bold text-dark"><i class="fas fa-chart-bar me-2 text-primary"></i> Pathogenic / Likely Pathogenic Variants Summary (gnomAD AF Known)</h3>
            <span class="badge bg-secondary">Whole Master Dataset (~60 Crore Rows)</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="variantChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Section 2: Tabular View -->
    <div class="card shadow-sm">
        <div class="card-header bg-light"><h3 class="mb-0 fs-5 fw-bold">Detailed Variant Data Table</h3></div>
        <div class="card-body">
            <div class="filter-section">
                <div class="row align-items-end mb-1">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="symbolFilter" class="form-label fw-bold">Symbol:</label>
                        <select id="symbolFilter" class="form-select">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="clnsigFilter" class="form-label fw-bold">Clinical Sig:</label>
                        <select id="clnsigFilter" class="form-select">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="amClassFilter" class="form-label fw-bold">AM Class:</label>
                        <select id="amClassFilter" class="form-select">
                            <option value="">All AM Class</option>
                            <option value="likely_pathogenic" selected>likely pathogenic</option>
                            <option value="likely_benign">likely benign</option>
                            <option value="ambiguous">ambiguous</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 text-lg-end">
                        <label class="form-label d-none d-lg-block">&nbsp;</label>
                        <a href="download_variant_master.php" id="downloadTsvBtn" class="btn btn-success w-100 w-lg-auto"><i class="fas fa-download"></i> Download as TSV</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Quick search: <strong>CHROM</strong>, <strong>Symbol</strong>, and <strong>RSID</strong> can be searched (minimum 2 characters).</small>
                    </div>
                </div>
            </div>

            <!-- Additional Columns Drawer / Selector -->
            <div class="column-selector-wrapper">
                <div class="column-selector-header">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-columns text-primary me-1"></i> Additional Columns</h6>
                    <div class="select-all-buttons">
                        <button class="btn btn-sm btn-outline-primary" id="selectAllColumns">Select All</button>
                        <button class="btn btn-sm btn-outline-secondary" id="deselectAllColumns">Deselect All</button>
                    </div>
                </div>
                <div id="columnSelector" class="d-flex flex-wrap">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <div class="dt-wrapper-custom">
                <!-- Top Scrollbar Sync -->
                <div id="topScrollbar" class="top-scrollbar-wrapper"><div class="top-scrollbar-inner"></div></div>
                
                <table id="variantTable" class="table table-striped table-bordered" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Sr.No.</th>                                 <!-- 0 -->
                            <th>CHROM</th>                                  <!-- 1 -->
                            <th>POS</th>                                    <!-- 2 -->
                            <th>REF</th>                                    <!-- 3 -->
                            <th>ALT</th>                                    <!-- 4 -->
                            <th>Symbol</th>                                 <!-- 5 -->
                            <th>ClinSign</th>                               <!-- 6 -->
                            <th>AM Class</th>                               <!-- 7 -->
                            <th>RSID</th>                                   <!-- 8 -->
                            <th>EVO2</th>                                   <!-- 9 -->
                            <th>BIAS-ACMG</th>                              <!-- 10 -->
                            <th>Variant Class</th>                          <!-- 11 -->
                            <th>Consequence</th>                            <!-- 12 -->
                            
                            <!-- Optional Columns -->
                            <th class="optional-column">Variant ID</th>     <!-- 13 -->
                            <th class="optional-column">HGVSc</th>          <!-- 14 -->
                            <th class="optional-column">HGVSp</th>          <!-- 15 -->
                            <th class="optional-column">AA Change</th>      <!-- 16 -->
                            <th class="optional-column">Transcript</th>     <!-- 17 -->
                            <th class="optional-column">Entrez Gene ID</th> <!-- 18 -->
                            <th class="optional-column">SwissProt</th>      <!-- 19 -->
                            <th class="optional-column">AM Pathogenicity</th><!-- 20 -->
                            <th class="optional-column">EVO2 Score</th>     <!-- 21 -->
                            <th class="optional-column">ClinVar DN</th>     <!-- 22 -->
                            <th class="optional-column">ClinVar Rev Stat</th><!-- 23 -->
                            <th class="optional-column">AF Raw</th>         <!-- 24 -->
                            <th class="optional-column">AF Overall</th>     <!-- 25 -->
                            <th class="optional-column">AF XX</th>          <!-- 26 -->
                            <th class="optional-column">AF XY</th>          <!-- 27 -->
                            <th class="optional-column">AF afr</th>         <!-- 28 -->
                            <th class="optional-column">AF afr XX</th>      <!-- 29 -->
                            <th class="optional-column">AF afr XY</th>      <!-- 30 -->
                            <th class="optional-column">AF ami</th>         <!-- 31 -->
                            <th class="optional-column">AF ami XX</th>      <!-- 32 -->
                            <th class="optional-column">AF ami XY</th>      <!-- 33 -->
                            <th class="optional-column">AF amr</th>         <!-- 34 -->
                            <th class="optional-column">AF amr XX</th>      <!-- 35 -->
                            <th class="optional-column">AF amr XY</th>      <!-- 36 -->
                            <th class="optional-column">AF asj</th>         <!-- 37 -->
                            <th class="optional-column">AF asj XX</th>      <!-- 38 -->
                            <th class="optional-column">AF asj XY</th>      <!-- 39 -->
                            <th class="optional-column">AF eas</th>         <!-- 40 -->
                            <th class="optional-column">AF eas XX</th>      <!-- 41 -->
                            <th class="optional-column">AF eas XY</th>      <!-- 42 -->
                            <th class="optional-column">AF fin</th>         <!-- 43 -->
                            <th class="optional-column">AF fin XX</th>      <!-- 44 -->
                            <th class="optional-column">AF fin XY</th>      <!-- 45 -->
                            <th class="optional-column">AF mid</th>         <!-- 46 -->
                            <th class="optional-column">AF mid XX</th>      <!-- 47 -->
                            <th class="optional-column">AF mid XY</th>      <!-- 48 -->
                            <th class="optional-column">AF nfe</th>         <!-- 49 -->
                            <th class="optional-column">AF nfe XX</th>      <!-- 50 -->
                            <th class="optional-column">AF nfe XY</th>      <!-- 51 -->
                            <th class="optional-column">AF REM</th>         <!-- 52 -->
                            <th class="optional-column">AF SAS</th>         <!-- 53 -->
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="54" class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading master variants...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    const chart_labels = <?= $chart_labels_json ?: '[]'; ?>;
    const chart_data = <?= $chart_data_json ?: '[]'; ?>;
    var currentFilters = { symbol: '', clnsig: '', amClass: '', search: '' };
    var dataTable = null;

    const optionalColumns = [
        { index: 13, name: 'id', label: 'Variant ID' },
        { index: 14, name: 'hgvsc', label: 'HGVSc' },
        { index: 15, name: 'hgvsp', label: 'HGVSp' },
        { index: 16, name: 'aachange', label: 'AA Change' },
        { index: 17, name: 'transcript', label: 'Transcript' },
        { index: 18, name: 'entrez_gene', label: 'Entrez Gene ID' },
        { index: 19, name: 'uniprot_id', label: 'SwissProt' },
        { index: 20, name: 'am_pathogenicity', label: 'AM Pathogenicity' },
        { index: 21, name: 'evo2_score', label: 'EVO2 Score' },
        { index: 22, name: 'clndn', label: 'ClinVar DN' },
        { index: 23, name: 'clnrevstat', label: 'ClinVar Rev Stat' },
        { index: 24, name: 'af_joint_raw', label: 'AF Raw' },
        { index: 25, name: 'af_joint', label: 'AF Overall' },
        { index: 26, name: 'af_joint_xx', label: 'AF XX' },
        { index: 27, name: 'af_joint_xy', label: 'AF XY' },
        { index: 28, name: 'af_joint_afr', label: 'AF afr' },
        { index: 29, name: 'af_joint_afr_xx', label: 'AF afr XX' },
        { index: 30, name: 'af_joint_afr_xy', label: 'AF afr XY' },
        { index: 31, name: 'af_joint_ami', label: 'AF ami' },
        { index: 32, name: 'af_joint_ami_xx', label: 'AF ami XX' },
        { index: 33, name: 'af_joint_ami_xy', label: 'AF ami XY' },
        { index: 34, name: 'af_joint_amr', label: 'AF amr' },
        { index: 35, name: 'af_joint_amr_xx', label: 'AF amr XX' },
        { index: 36, name: 'af_joint_amr_xy', label: 'AF amr XY' },
        { index: 37, name: 'af_joint_asj', label: 'AF asj' },
        { index: 38, name: 'af_joint_asj_xx', label: 'AF asj XX' },
        { index: 39, name: 'af_joint_asj_xy', label: 'AF asj XY' },
        { index: 40, name: 'af_joint_eas', label: 'AF eas' },
        { index: 41, name: 'af_joint_eas_xx', label: 'AF eas XX' },
        { index: 42, name: 'af_joint_eas_xy', label: 'AF eas XY' },
        { index: 43, name: 'af_joint_fin', label: 'AF fin' },
        { index: 44, name: 'af_joint_fin_xx', label: 'AF fin XX' },
        { index: 45, name: 'af_joint_fin_xy', label: 'AF fin XY' },
        { index: 46, name: 'af_joint_mid', label: 'AF mid' },
        { index: 47, name: 'af_joint_mid_xx', label: 'AF mid XX' },
        { index: 48, name: 'af_joint_mid_xy', label: 'AF mid XY' },
        { index: 49, name: 'af_joint_nfe', label: 'AF nfe' },
        { index: 50, name: 'af_joint_nfe_xx', label: 'AF nfe XX' },
        { index: 51, name: 'af_joint_nfe_xy', label: 'AF nfe XY' },
        { index: 52, name: 'af_joint_remaining', label: 'AF REM' },
        { index: 53, name: 'af_joint_sas', label: 'AF SAS' }
    ];

    $(document).ready(function() {
        initializeChart();
        generateColumnSelector();
        initializeDataTable();
        loadFilterOptions();
        updateDownloadLink();
    });

    function updateScrollbarSync() {
        if (!dataTable) return;
        setTimeout(function() {
            dataTable.columns.adjust();
            const scrollBody = $('.dataTables_scrollBody');
            const table = scrollBody.find('table');
            const tableContentWidth = table.outerWidth();
            const containerWidth = scrollBody.width();

            $('#topScrollbar .top-scrollbar-inner').css('width', tableContentWidth + 'px');
            if (tableContentWidth > containerWidth) {
                $('#topScrollbar').show();
            } else {
                $('#topScrollbar').hide();
            }
        }, 50);
    }

    function generateColumnSelector() {
        const container = $('#columnSelector');
        optionalColumns.forEach(col => {
            const checkbox = $(`
                <div class="form-check column-checkbox">
                    <input class="form-check-input column-toggle" type="checkbox" value="${col.index}" id="col_${col.index}">
                    <label class="form-check-label" for="col_${col.index}">${col.label}</label>
                </div>
            `);
            container.append(checkbox);
        });

        $('#selectAllColumns').on('click', function() { 
            $('.column-toggle').prop('checked', true).trigger('change'); 
            updateScrollbarSync();
        });
        $('#deselectAllColumns').on('click', function() { 
            $('.column-toggle').prop('checked', false).trigger('change'); 
            updateScrollbarSync();
        });

        $('.column-toggle').on('change', function() {
            const colIndex = parseInt($(this).val());
            dataTable.column(colIndex).visible($(this).is(':checked'));
            updateScrollbarSync();
        });
    }

    function initializeChart() {
        const ctx = document.getElementById('variantChart');
        if (ctx && chart_labels.length > 0) {
            const singlePastelFill = '#83C5BE';
            const singlePastelBorder = '#59A098';

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chart_labels,
                    datasets: [{
                        label: 'Pathogenic / Likely Pathogenic Variant Count',
                        data: chart_data,
                        backgroundColor: singlePastelFill,
                        borderColor: singlePastelBorder,
                        borderWidth: 1.5,
                        borderRadius: 6,
                        barThickness: 65
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` Pathogenic Variants: ${context.raw.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Variant Counts',
                                font: { size: 14, weight: 'bold' }
                            },
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1e6) return (value / 1e6).toFixed(1) + 'M';
                                    if (value >= 1e3) return (value / 1e3).toFixed(0) + 'K';
                                    return value;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Pathogenicity Prediction Source (af_joint IS NOT NULL)',
                                font: { size: 14, weight: 'bold' }
                            },
                            ticks: {
                                font: { size: 13, weight: 'bold' }
                            }
                        }
                    }
                }
            });
        }
    }

function initializeDataTable() {
        dataTable = $('#variantTable').DataTable({
            "processing": true,
            "serverSide": true,
            "deferRender": true,
            "autoWidth": false,
            "language": {
                "processing": '<div class="custom-table-loader"><div class="custom-loader-spinner"></div><span class="text-muted fw-bold">Querying Variant Dataset ...</span></div>',
                // --- RECTIFIED CLEAN DISPLAY LABELS ---
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoFiltered": "", // Removes the "(filtered from 630,000,000 total entries)" entirely
                "infoEmpty": "No matching variants found",
                "zeroRecords": "No matching records found"
            },
            "ajax": {
                "url": "get_variant_master_data.php",
                "type": "POST",
                "data": function(d) {
                    d.symbol = $('#symbolFilter').val();
                    d.clnsig = $('#clnsigFilter').val();
                    d.amClass = $('#amClassFilter').val();
                    currentFilters = { symbol: d.symbol, clnsig: d.clnsig, amClass: d.amClass, search: d.search.value };
                }
            },
            "columnDefs": [
                {
                    "targets": 5,
                    "render": function(data, type, row) {
                        if (!data || data === "." || data === "NA") return 'NA';
                        const geneName = encodeURIComponent(data.trim());
                        return `<a href="gene.php?keyword=${geneName}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
                {
                    "targets": 6,
                    "render": function(data, type, row) {
                        if (!data || data === "." || data === "NA") return 'NA';
                        return String(data).replace(/_/g, ' ').replace(/\|/g, ' | ');
                    }
                },
                {
                    "targets": 7,
                    "render": function(data, type, row) {
                        if (!data || data === "." || data === "NA") {
                            return '<span class="badge bg-secondary rounded-pill">NA</span>';
                        }
                        const text = data.toLowerCase().replace(/_/g, ' ').trim();
                        let badgeClass = "bg-secondary";
                        if (text.includes("likely benign")) badgeClass = "bg-success";
                        else if (text.includes("likely pathogenic")) badgeClass = "bg-danger";
                        else if (text.includes("ambiguous")) badgeClass = "bg-warning text-dark";
                        
                        return `<span class="badge ${badgeClass} rounded-pill">${data}</span>`;
                    }
                },
                {
                    "targets": 8,
                    "render": function(data, type, row) {
                        if (!data || data === "." || data === "NA") return 'NA';
                        let displayRs = String(data).trim();
                        if (!/^rs/i.test(displayRs)) displayRs = 'rs' + displayRs;
                        return `<a href="https://www.ncbi.nlm.nih.gov/snp/${encodeURIComponent(displayRs)}" target="_blank" rel="noopener noreferrer" class="fw-bold text-primary">${displayRs}</a>`;
                    }
                },
                { "targets": optionalColumns.map(c => c.index), "visible": false },
                {
                    "targets": "_all", 
                    "render": function (data, type, row, meta) {
                        if ([5, 6, 7, 8].includes(meta.col)) return data; 
                        if (!data || data === ".") return "NA";
                        return data;
                    }
                }
            ],
            "columns": [
                { "data": 0, "orderable": false },
                { "data": 1 }, { "data": 2 }, { "data": 3 }, { "data": 4 }, { "data": 5 },
                { "data": 6 }, { "data": 7 }, { "data": 8 }, { "data": 9 }, { "data": 10 },
                { "data": 11 }, { "data": 12 },
                
                // Optional columns
                { "data": 13 }, { "data": 14 }, { "data": 15 }, { "data": 16 }, { "data": 17 },
                { "data": 18 }, { "data": 19 }, { "data": 20 }, { "data": 21 }, { "data": 22 },
                { "data": 23 }, { "data": 24 }, { "data": 25 }, { "data": 26 }, { "data": 27 },
                { "data": 28 }, { "data": 29 }, { "data": 30 }, { "data": 31 }, { "data": 32 },
                { "data": 33 }, { "data": 34 }, { "data": 35 }, { "data": 36 }, { "data": 37 },
                { "data": 38 }, { "data": 39 }, { "data": 40 }, { "data": 41 }, { "data": 42 },
                { "data": 43 }, { "data": 44 }, { "data": 45 }, { "data": 46 }, { "data": 47 },
                { "data": 48 }, { "data": 49 }, { "data": 50 }, { "data": 51 }, { "data": 52 },
                { "data": 53 }
            ],
            "pageLength": 25,
            "order": [[1, 'asc'], [2, 'asc']],
            "scrollX": true,
            "initComplete": function() {
                const tableBody = $(this).closest('.dataTables_scroll').find('.dataTables_scrollBody');
                const tableHead = $(this).closest('.dataTables_scroll').find('.dataTables_scrollHead');
                const topScrollbar = $('#topScrollbar');
                
                let isSyncing = false;
                function syncHorizontalScroll(scrollLeft) {
                    if (isSyncing) return;
                    isSyncing = true;
                    tableBody.scrollLeft(scrollLeft);
                    tableHead.scrollLeft(scrollLeft);
                    topScrollbar.scrollLeft(scrollLeft);
                    isSyncing = false;
                }

                topScrollbar.off('scroll').on('scroll', function() { syncHorizontalScroll($(this).scrollLeft()); });
                tableBody.off('scroll').on('scroll', function() { syncHorizontalScroll($(this).scrollLeft()); });

                updateScrollbarSync();
            },
            "drawCallback": function() { updateScrollbarSync(); }
        });

        $('#symbolFilter, #clnsigFilter, #amClassFilter').on('change', () => {
            dataTable.draw();
            updateDownloadLink();
        });
    }

    function updateDownloadLink() {
        const symbol = $('#symbolFilter').val() || '';
        const clnsig = $('#clnsigFilter').val() || '';
        const amClass = $('#amClassFilter').val() || '';
        
        const tsvUrl = `download_variant_master.php?symbol=${encodeURIComponent(symbol)}&clnsig=${encodeURIComponent(clnsig)}&amClass=${encodeURIComponent(amClass)}`;
        $('#downloadTsvBtn').attr('href', tsvUrl);
    }

    function loadFilterOptions() {
        const urlParams = new URLSearchParams(window.location.search);
        const symbolParam = urlParams.get('symbol') || '';

        const loadOption = (field, elementId, placeholder, defaultValue = '') => {
            $.get(`get_variant_master_filters.php?field=${field}`)
            .done(data => {
                const $filter = $(`#${elementId}`);
                $filter.empty().append('<option value=""></option>');
                
                if (data && data.length > 0) {
                    data.forEach(item => {
                        if (item.value !== '.' && item.value !== 'NA' && item.value !== '') {
                            const selected = (item.value === defaultValue) ? 'selected' : '';
                            $filter.append(`<option value="${item.value}" ${selected}>${item.text}</option>`);
                        }
                    });
                }
                
                $filter.select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%'
                });

                if (defaultValue) {
                    $filter.val(defaultValue).trigger('change');
                }
            });
        };
        loadOption('SYMBOL', 'symbolFilter', 'All Symbol', symbolParam);
        loadOption('CLNSIG', 'clnsigFilter', 'All Clinical Sig.');
        loadOption('am_class', 'amClassFilter', 'All AM Class', 'likely_pathogenic');
    }
</script>

</body>
</html>
<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>