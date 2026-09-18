<?php
// gene_variant_distribution.php
require_once 'db_connect.php';

if (!isset($dbconn) || !$dbconn) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
    die("Database connection failed. Please check db_connect.php connection parameters.");
}

// --- POPULATE GENE DROPDOWN LIST (PRE-LOADED FOR FAST INITIAL RENDER) ---
$gene_list = [
    'PER1', 'PER2', 'PER3', 'CRY1', 'CRY2', 'CLOCK', 'ARNTL', 'NPAS2', 
    'NR1D1', 'NR1D2', 'DBP', 'BHLHE40', 'BHLHE41', 'CSNK1E', 'CSNK1D', 
    'FBXL3', 'FBXL21', 'DEC1', 'DEC2', 'RORA', 'RORB', 'RORC', 'TIMELESS'
];

// Try fetching additional gene symbols from lookup tables if fast
$chk_sql = "SELECT table_name FROM information_schema.tables WHERE table_name IN ('gene_lookup', 'genes', 'master_variants_gene_summary')";
$chk_res = @pg_query($dbconn, $chk_sql);
if ($chk_res && pg_num_rows($chk_res) > 0) {
    while ($r = pg_fetch_assoc($chk_res)) {
        $tname = $r['table_name'];
        $list_res = @pg_query($dbconn, "SELECT DISTINCT gene_symbol FROM {$tname} LIMIT 1000");
        if (!$list_res) {
            $list_res = @pg_query($dbconn, "SELECT DISTINCT entrez_gene_symbol FROM {$tname} LIMIT 1000");
        }
        if ($list_res && pg_num_rows($list_res) > 0) {
            while ($lr = pg_fetch_row($list_res)) {
                if (!empty($lr[0]) && $lr[0] !== '.') {
                    $gene_list[] = trim($lr[0]);
                }
            }
        }
    }
}

// Clean and prioritize PER1 at top of list
$gene_list = array_unique($gene_list);
sort($gene_list);
if (($key = array_search('PER1', $gene_list)) !== false) {
    unset($gene_list[$key]);
}
array_unshift($gene_list, 'PER1');


// --- AJAX ENDPOINT: ANALYZE GENE DISTRIBUTIONS (FETCH ALL GENE VARIANTS FROM 63 CRORE MASTER STORE) ---
if (isset($_GET['action']) && $_GET['action'] === 'analyze') {
    header('Content-Type: application/json');
    @ini_set('memory_limit', '512M');
    
    $gene = (isset($_GET['gene']) && trim($_GET['gene']) !== '') ? trim($_GET['gene']) : 'PER1';

    try {
        @pg_query($dbconn, "SET statement_timeout = 30000"); // 30 sec safety limit for 63 crore master DB

        $totals = ['total' => 0, 'missense' => 0, 'pathogenic' => 0, 'vus' => 0, 'benign' => 0];
        $cons_counts = [];
        $clin_counts = [];
        $table_data = [];

        $gene_upper = strtoupper($gene);
        $gene_lower = strtolower($gene);
        $gene_ucfirst = ucfirst(strtolower($gene));

        // High limit ceiling (50,000) ensures 100% of variants for any gene are retrieved without capping
        $sql = "SELECT rs, consequence, clnsig, entrez_gene_symbol 
                FROM master_variants 
                WHERE entrez_gene_symbol = $1 OR entrez_gene_symbol = $2 OR entrez_gene_symbol = $3
                LIMIT 50000";

        $res = @pg_query_params($dbconn, $sql, [$gene_upper, $gene_lower, $gene_ucfirst]);

        if ($res && pg_num_rows($res) > 0) {
            while ($row = pg_fetch_assoc($res)) {
                $totals['total']++;

                $cons = (!empty($row['consequence']) && $row['consequence'] !== '.') ? $row['consequence'] : '';
                $clnsig = (!empty($row['clnsig']) && $row['clnsig'] !== '.') ? $row['clnsig'] : '';
                $rs = (!empty($row['rs']) && $row['rs'] !== '.') ? $row['rs'] : 'NA';
                $sym = (!empty($row['entrez_gene_symbol']) && $row['entrez_gene_symbol'] !== '.') ? $row['entrez_gene_symbol'] : 'NA';

                $cons_clean = str_replace('_', ' ', $cons);
                $clnsig_clean = str_replace('_', ' ', $clnsig);

                $cons_lower = strtolower($cons);
                $clnsig_lower = strtolower($clnsig);

                // 1. KPI Calculations
                if (strpos($cons_lower, 'missense') !== false) {
                    $totals['missense']++;
                }
                if (strpos($clnsig_lower, 'pathogenic') !== false) {
                    $totals['pathogenic']++;
                }
                if (strpos($clnsig_lower, 'benign') !== false) {
                    $totals['benign']++;
                }
                if (strpos($clnsig_lower, 'uncertain') !== false || strpos($clnsig_lower, 'vus') !== false || empty($clnsig_lower)) {
                    $totals['vus']++;
                }

                // 2. Consequence Aggregation
                if ($cons_clean !== '') {
                    $cons_counts[$cons_clean] = ($cons_counts[$cons_clean] ?? 0) + 1;
                }

                // 3. ClinVar Significance Aggregation
                if ($clnsig_clean !== '') {
                    $clin_counts[$clnsig_clean] = ($clin_counts[$clnsig_clean] ?? 0) + 1;
                }

                // 4. DataTables Output Records
                $table_data[] = [
                    'rs' => $rs,
                    'consequence' => ($cons_clean !== '') ? $cons_clean : 'NA',
                    'clnsig' => ($clnsig_clean !== '') ? $clnsig_clean : 'NA',
                    'symbol' => $sym
                ];
            }
        }

        // Top 5 Consequences
        arsort($cons_counts);
        $top_cons = array_slice($cons_counts, 0, 5, true);
        $consequences = [
            'labels' => array_keys($top_cons),
            'data' => array_values($top_cons)
        ];

        // Top 5 ClinVar Significance
        arsort($clin_counts);
        $top_clin = array_slice($clin_counts, 0, 5, true);
        $clinvar = [
            'labels' => array_keys($top_clin),
            'data' => array_values($top_clin)
        ];

        if ($totals['total'] === 0 && empty($table_data)) {
            echo json_encode(['success' => false, 'message' => "No records identified for gene symbol: $gene."]);
        } else {
            echo json_encode([
                'success' => true,
                'totals' => $totals,
                'consequences' => $consequences,
                'clinvar' => $clinvar,
                'table' => $table_data
            ]);
        }
    } catch (Exception $e) {
        error_log("Error in distribution analyzer endpoint: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred during dataset analysis.']);
    }
    exit;
}

include 'header.php';
?>

<!-- DataTables & Select2 Integration CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Layout Style Rules -->
<style>
    .custom-wide-container {
        width: 100% !important;
        max-width: 98% !important;
        margin: 0 auto;
    }
    
    .chart-container { position: relative; height: 300px; width: 100%; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
    
    /* Select2 Dropdown Integration Styling */
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem 0 0 0.25rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        padding-left: 12px !important;
        color: #212529 !important;
        font-weight: 600;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }

    /* Table Styles */
    .table-custom { font-size: 0.85rem; width: 100% !important; }
    .table-custom th, .table-custom td {
        padding: 8px 10px !important; 
        line-height: 1.3 !important;  
    }
    .table-custom thead th { 
        background-color: #212529 !important;
        color: #fff !important;
        font-size: 0.8rem; 
        text-transform: uppercase; 
        border-bottom: 2px solid #323539; 
    }

    .table-custom .badge {
        padding: 0.35em 0.6em !important;
        font-size: 0.75rem !important;
    }

    /* Legend Colors */
    .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px; text-transform: capitalize; }
    .dot { width: 12px; height: 12px; border-radius: 2px; display: inline-block; }

    /* Custom Loading Overlay */
    .dashboard-view-wrapper {
        position: relative;
    }
    .dashboard-loader-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        border-radius: 8px;
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

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0 !important;
        margin-left: 0 !important;
    }
    .dataTables_wrapper .dataTables_length select {
        padding: 0.375rem 1.75rem 0.375rem 0.75rem !important;
    }
</style>

<!-- Chart.js dependency -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

<div class="custom-wide-container pb-4 pt-0">

    <!-- CENTERED HEADER TYPOGRAPHY SECTION -->
    <div class="text-center mb-5">
        <h1 style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 40px;
            font-weight: 700;
            color: #212529;
            margin-top: 0;
            padding-top: 0;
            margin-bottom: 5px;">
            Gene Wise Variant Distribution
        </h1>

        <p style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #212529;
            line-height: 1.5;
            max-width: 900px;
            margin: 0 auto;">
            Analyze consequence frequencies and clinical significance profiles of genetic variants within circadian-associated genes across multiple datasets.
        </p>
    </div>

    <!-- UTILITY CONTROL BAR WITH DROPDOWN LIST & DATASET BADGE -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <span class="small fw-bold text-muted text-uppercase d-block">Active Search Gene</span>
                    <span id="currentGeneName" class="h4 fw-bold text-primary m-0">PER1</span>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <select id="geneSelect" class="form-select select2-gene" style="width: 100%;">
                                <?php foreach ($gene_list as $g_sym): ?>
                                    <option value="<?php echo htmlspecialchars($g_sym); ?>" <?php echo (strtoupper($g_sym) === 'PER1') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g_sym); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="searchSubmitBtn" class="btn btn-primary px-4 fw-bold text-nowrap" style="height: 38px; border-radius: 0 0.25rem 0.25rem 0;">
                            <i class="fas fa-search me-1"></i> Analyze
                        </button>
                    </div>
                </div>
                <div class="col-md-3 text-md-end text-start">
                    <span class="small fw-bold text-muted text-uppercase d-block">Master Datastore</span>
                    <span class="badge bg-secondary fs-6 py-2 px-3"><i class="fas fa-database me-1"></i> ~63 Crore Variants</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT AREA WITH AJAX OVERLAY LOADER -->
    <div class="dashboard-view-wrapper">
        
        <!-- Standardized Spinner Block -->
        <div id="dashboardLoader" class="dashboard-loader-overlay">
            <div class="custom-table-loader">
                <div class="custom-loader-spinner"></div>
                <span class="text-muted fw-bold">Querying Master Database (~63 Crore Records)...</span>
            </div>
        </div>

        <div class="row g-4">
            <!-- TOP ROW: SUMMARY CARDS -->
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-primary mb-0" id="missenseBadge">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Missense</div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-primary" id="missenseProgress" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-danger mb-0" id="pathogenicBadge">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Pathogenic (ClinVar)</div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-danger" id="pathogenicProgress" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-warning mb-0" id="vusBadge">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">VUS Records</div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-warning" id="vusProgress" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-success mb-0" id="benignBadge">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Benign / Likely Benign</div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-success" id="benignProgress" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LEFT COLUMN: CONSEQUENCES -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark">Nirvana Annotation</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="consequenceChart"></canvas>
                        </div>
                        <div class="mt-4 row" id="consequenceLegend">
                            <!-- Legend values populated dynamically by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: CLINVAR SIGNIFICANCE -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark">ClinVar Significance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="clinvarChart"></canvas>
                        </div>
                        <div class="mt-4 d-flex flex-wrap justify-content-center gap-3" id="clinvarLegend">
                            <!-- Legend values populated dynamically by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM COLUMN: DATA TABLE PORTLET -->
            <div class="col-12 mb-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark">Consolidated Annotation Records</h5>
                    </div>
                    <div class="card-body">
                        
                        <!-- ADVANCED DRILL-DOWN FILTERS -->
                        <div class="filter-section">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="filterConsequence" class="form-label small fw-bold text-muted">Consequence Filter:</label>
                                    <select id="filterConsequence" class="form-select">
                                        <option value="">All Consequences</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterClinSig" class="form-label small fw-bold text-muted">Clinical Significance:</label>
                                    <select id="filterClinSig" class="form-select">
                                        <option value="">All Significance</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="tableSearchInput" class="form-label small fw-bold text-muted">Search Table:</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" id="tableSearchInput" class="form-control border-start-0" placeholder="Filter current records...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="consolidatedTable" class="table table-custom table-striped table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">S. No.</th>
                                        <th>Gene Name</th>
                                        <th>RSID</th>
                                        <th>Consequence </th>
                                        <th>ClinVar Significance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated dynamically via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS Libraries for DataTables & Select2 Integration -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let consequenceChart, clinvarChart, dataTableObj;
    const geneSelect = $('#geneSelect');
    const submitBtn = document.getElementById('searchSubmitBtn');
    const dashboardLoader = document.getElementById('dashboardLoader');
    
    const currentScriptPath = window.location.pathname;

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        setupGeneDropdown();
        setupTableFilters();
        
        // Initial load for PER1
        updateCharts();
    });

    function initCharts() {
        // Horizontal Bar Chart for Consequences
        const ctxCons = document.getElementById('consequenceChart').getContext('2d');
        consequenceChart = new Chart(ctxCons, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: ['#0d6efd', '#fd7e14', '#6c757d', '#0dcaf0', '#212529'],
                    borderRadius: 4,
                    barThickness: 20
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { display: false } }
                }
            }
        });

        // Donut Chart for ClinVar Significance
        const ctxClin = document.getElementById('clinvarChart').getContext('2d');
        clinvarChart = new Chart(ctxClin, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: ['#dc3545', '#e35d6a', '#fd7e14', '#198754', '#0f5132'],
                    hoverOffset: 12,
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { display: false } }
            }
        });
    }

    // ===== SEARCHABLE SELECT2 DROPDOWN SETUP =====
    function setupGeneDropdown() {
        geneSelect.select2({
            placeholder: "Select or search Gene Symbol...",
            allowClear: false,
            width: '100%'
        });

        // Trigger chart update on dropdown selection change
        geneSelect.on('change', function() {
            updateCharts();
        });

        // Trigger chart update on Analyze button click
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                updateCharts();
            });
        }
    }

    // ===== DRILL-DOWN DATATABLES FILTERS SETUP =====
    function setupTableFilters() {
        $('#filterConsequence').on('change', function() {
            if (dataTableObj) {
                dataTableObj.column(3).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
            }
        });

        $('#filterClinSig').on('change', function() {
            if (dataTableObj) {
                dataTableObj.column(4).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
            }
        });

        $('#tableSearchInput').on('keyup input', function() {
            if (dataTableObj) {
                dataTableObj.search(this.value).draw();
            }
        });
    }

    // ===== DYNAMIC METRIC & GRAPH UPDATER =====
    function updateCharts() {
        let gene = geneSelect.val();
        if (!gene || gene.trim() === '') {
            gene = 'PER1';
        }
        gene = gene.trim().toUpperCase();

        document.getElementById('currentGeneName').innerText = gene;
        dashboardLoader.style.display = 'flex';

        fetch(`${currentScriptPath}?action=analyze&gene=${encodeURIComponent(gene)}`)
            .then(res => res.json())
            .then(res => {
                dashboardLoader.style.display = 'none';

                if (!res.success) {
                    alert(res.message || "An error occurred retrieving dataset records.");
                    return;
                }

                // 1. Update KPI Card numbers
                document.getElementById('missenseBadge').innerText = res.totals.missense.toLocaleString();
                document.getElementById('pathogenicBadge').innerText = res.totals.pathogenic.toLocaleString();
                document.getElementById('vusBadge').innerText = res.totals.vus.toLocaleString();
                document.getElementById('benignBadge').innerText = res.totals.benign.toLocaleString();

                // 2. Update KPI Progress Bars
                const total = res.totals.total || 1;
                document.getElementById('missenseProgress').style.width = ((res.totals.missense / total) * 100) + "%";
                document.getElementById('pathogenicProgress').style.width = ((res.totals.pathogenic / total) * 100) + "%";
                document.getElementById('vusProgress').style.width = ((res.totals.vus / total) * 100) + "%";
                document.getElementById('benignProgress').style.width = ((res.totals.benign / total) * 100) + "%";

                // 3. Update VEP Consequence Chart & Legend
                consequenceChart.data.labels = res.consequences.labels;
                consequenceChart.data.datasets[0].data = res.consequences.data;
                consequenceChart.update();

                const consLegend = document.getElementById('consequenceLegend');
                consLegend.innerHTML = '';
                const consColors = ['#0d6efd', '#fd7e14', '#6c757d', '#0dcaf0', '#212529'];
                res.consequences.labels.forEach((label, idx) => {
                    const color = consColors[idx % consColors.length];
                    consLegend.innerHTML += `
                        <div class="col-6">
                            <div class="legend-item"><span class="dot" style="background:${color}"></span> ${label}: ${res.consequences.data[idx]}</div>
                        </div>
                    `;
                });

                // 4. Update ClinVar Significance Chart & Legend
                clinvarChart.data.labels = res.clinvar.labels;
                clinvarChart.data.datasets[0].data = res.clinvar.data;
                clinvarChart.update();

                const clinLegend = document.getElementById('clinvarLegend');
                clinLegend.innerHTML = '';
                const clinColors = ['#dc3545', '#e35d6a', '#fd7e14', '#198754', '#0f5132'];
                res.clinvar.labels.forEach((label, idx) => {
                    const color = clinColors[idx % clinColors.length];
                    clinLegend.innerHTML += `
                        <div class="legend-item"><span class="dot" style="background:${color}"></span> ${label} (${res.clinvar.data[idx]})</div>
                    `;
                });

                // 5. Build Dropdown Options dynamically & Set Default "Uncertain significance"
                const uniqueCons = [...new Set(res.table.map(r => r.consequence))].filter(Boolean).sort();
                const uniqueSigs = [...new Set(res.table.map(r => r.clnsig))].filter(Boolean).sort();

                let consHtml = '<option value="">All Consequences</option>';
                uniqueCons.forEach(val => { if (val !== 'NA') consHtml += `<option value="${val}">${val}</option>`; });
                $('#filterConsequence').html(consHtml);

                let defaultClinSig = '';
                let sigHtml = '<option value="">All Significance</option>';
                uniqueSigs.forEach(val => {
                    if (val !== 'NA') {
                        // Check if current option is uncertain significance
                        const isUncertain = val.toLowerCase().includes('uncertain');
                        if (isUncertain && !defaultClinSig) {
                            defaultClinSig = val;
                            sigHtml += `<option value="${val}" selected>${val}</option>`;
                        } else {
                            sigHtml += `<option value="${val}">${val}</option>`;
                        }
                    }
                });
                $('#filterClinSig').html(sigHtml);

                $('#tableSearchInput').val('');

                // 6. Initialize / Refresh DataTables
                if ($.fn.DataTable.isDataTable('#consolidatedTable')) {
                    $('#consolidatedTable').DataTable().destroy();
                }

                dataTableObj = $('#consolidatedTable').DataTable({
                    data: res.table,
                    deferRender: true,
                    pageLength: 15,
                    lengthMenu: [10, 15, 25, 50, 100],
                    order: [], 
                    dom: 'lrtip', 
                    columns: [
                        { data: null, defaultContent: '' }, // Index 0: S. No.
                        { data: 'symbol' },                 // Index 1: Gene Name
                        { data: 'rs' },                     // Index 2: RSID
                        { data: 'consequence' },            // Index 3: Consequence
                        { data: 'clnsig' }                  // Index 4: Clinical Significance
                    ],
                    columnDefs: [
                        {
                            targets: 0,
                            searchable: false,
                            orderable: false,
                            render: function (data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            targets: 1,
                            render: function(data, type, row) {
                                return `<a href="gene.php?keyword=${encodeURIComponent(data)}" target="_blank" class="fw-bold text-primary text-decoration-none">
                                            ${data}
                                            <i class="fas fa-external-link-alt ms-1" style="font-size: 0.65rem;"></i>
                                        </a>`;
                            }
                        },
                        {
                            targets: 2,
                            render: function(data, type, row) {
                                if (!data || data === 'NA') return 'NA';
                                let displayRs = String(data).trim();
                                if (!/^rs/i.test(displayRs)) displayRs = 'rs' + displayRs;
                                return `<a href="https://www.ncbi.nlm.nih.gov/snp/${encodeURIComponent(displayRs)}" target="_blank" rel="noopener noreferrer" class="fw-bold text-primary">${displayRs}</a>`;
                            }
                        },
                        {
                            targets: 3,
                            render: function(data, type, row) {
                                return `<span class="badge bg-secondary text-capitalize">${data}</span>`;
                            }
                        },
                        {
                            targets: 4,
                            render: function(data, type, row) {
                                let badgeClass = 'bg-secondary';
                                const txt = data.toLowerCase();
                                if (txt.includes('benign')) {
                                    badgeClass = 'bg-success';
                                } else if (txt.includes('pathogenic')) {
                                    badgeClass = 'bg-danger';
                                } else if (txt.includes('uncertain') || txt.includes('vus') || txt.includes('significance')) {
                                    badgeClass = 'bg-warning text-dark';
                                }
                                return `<span class="badge ${badgeClass} text-capitalize">${data}</span>`;
                            }
                        }
                    ]
                });

                // Apply initial Clinical Significance filter to table if present
                if (defaultClinSig) {
                    dataTableObj.column(4).search('^' + defaultClinSig + '$', true, false).draw();
                }
            })
            .catch(err => {
                dashboardLoader.style.display = 'none';
                console.error("Error executing dynamic distribution update:", err);
            });
    }
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>