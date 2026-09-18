<?php
// population.php
require_once 'db_connect.php';

if (!isset($dbconn) || !$dbconn) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check db_connect.php parameters.']);
        exit;
    }
    die("Database connection failed. Please check db_connect.php connection parameters.");
}

// Helper function to cleanly convert empty or dot values to floats
function cleanFloat($val) {
    if ($val === null || $val === '' || $val === '.') {
        return 0.0;
    }
    return (float)$val;
}

// --- POPULATE ALL GENE SYMBOLS FROM DATABASE FOR DROPDOWN ---
$gene_list = [];

$g_queries = [
    "SELECT DISTINCT gene_symbol FROM gene_lookup WHERE gene_symbol IS NOT NULL AND gene_symbol != '' AND gene_symbol != '.' ORDER BY gene_symbol ASC",
    "SELECT DISTINCT entrez_gene_symbol FROM gene_lookup WHERE entrez_gene_symbol IS NOT NULL AND entrez_gene_symbol != '' AND entrez_gene_symbol != '.' ORDER BY entrez_gene_symbol ASC",
    "SELECT DISTINCT symbol FROM gene_lookup WHERE symbol IS NOT NULL AND symbol != '' AND symbol != '.' ORDER BY symbol ASC",
    "SELECT DISTINCT entrez_gene_symbol FROM master_variants WHERE entrez_gene_symbol IS NOT NULL AND entrez_gene_symbol != '' AND entrez_gene_symbol != '.' ORDER BY entrez_gene_symbol ASC LIMIT 2000"
];

foreach ($g_queries as $g_sql) {
    $g_res = @pg_query($dbconn, $g_sql);
    if ($g_res && pg_num_rows($g_res) > 0) {
        while ($row = pg_fetch_row($g_res)) {
            if (!empty($row[0]) && $row[0] !== '.') {
                $gene_list[] = trim($row[0]);
            }
        }
        if (count($gene_list) > 10) {
            break; // Stop once comprehensive gene list is retrieved
        }
    }
}

// Fallback to core circadian gene list if database query returned empty
if (empty($gene_list)) {
    $gene_list = [
        'CLOCK', 'PER1', 'PER2', 'PER3', 'CRY1', 'CRY2', 'ARNTL', 'NPAS2', 
        'NR1D1', 'NR1D2', 'DBP', 'BHLHE40', 'BHLHE41', 'CSNK1E', 'CSNK1D', 
        'FBXL3', 'FBXL21', 'DEC1', 'DEC2', 'RORA', 'RORB', 'RORC', 'TIMELESS'
    ];
}

// Check for incoming gene parameter via GET
$initial_gene = 'CLOCK';
if (isset($_GET['gene']) && trim($_GET['gene']) !== '') {
    $initial_gene = trim($_GET['gene']);
} elseif (isset($_GET['keyword']) && trim($_GET['keyword']) !== '') {
    $initial_gene = trim($_GET['keyword']);
}

// Ensure the requested gene exists in the array
$gene_list = array_unique($gene_list);
$found_idx = false;
foreach ($gene_list as $k => $item) {
    if (strcasecmp($item, $initial_gene) === 0) {
        $found_idx = $k;
        $initial_gene = $item; // keep exact casing from list if available
        break;
    }
}

if ($found_idx === false) {
    $gene_list[] = strtoupper($initial_gene);
}

sort($gene_list);


// --- AJAX ENDPOINT: ANALYZE GENE ---
if (isset($_GET['action']) && $_GET['action'] === 'analyze') {
    header('Content-Type: application/json');
    @ini_set('memory_limit', '512M');

    $gene = (isset($_GET['gene']) && trim($_GET['gene']) !== '') ? trim($_GET['gene']) : 'CLOCK';

    try {
        @pg_query($dbconn, "SET statement_timeout = 15000"); // 15 sec safety limit

        $variants = [];
        $gene_upper = strtoupper($gene);
        $gene_lower = strtolower($gene);
        $gene_ucfirst = ucfirst(strtolower($gene));

        // Query on master_variants including clnsig and evo2_prediction
        $sql_var = "
            SELECT 
                entrez_gene_symbol, rs, id, clnsig, acmgclassification, evo2_score, evo2_prediction,
                af_joint_raw, af_joint_xx, af_joint_xy,
                af_joint_afr, af_joint_afr_xx, af_joint_afr_xy,
                af_joint_ami, af_joint_ami_xx, af_joint_ami_xy,
                af_joint_amr, af_joint_amr_xx, af_joint_amr_xy,
                af_joint_asj, af_joint_asj_xx, af_joint_asj_xy,
                af_joint_eas, af_joint_eas_xx, af_joint_eas_xy,
                af_joint_fin, af_joint_fin_xx, af_joint_fin_xy,
                af_joint_mid, af_joint_mid_xx, af_joint_mid_xy,
                af_joint_nfe, af_joint_nfe_xx, af_joint_nfe_xy,
                af_joint_remaining, af_joint_remaining_xx, af_joint_remaining_xy
            FROM master_variants 
            WHERE entrez_gene_symbol = $1 OR entrez_gene_symbol = $2 OR entrez_gene_symbol = $3
            LIMIT 1500
        ";

        $res_var = @pg_query_params($dbconn, $sql_var, [$gene_upper, $gene_lower, $gene_ucfirst]);

        if ($res_var === false) {
            $err = pg_last_error($dbconn);
            echo json_encode(['success' => false, 'message' => 'Database Query Error: ' . $err]);
            exit;
        }

        while ($row_var = pg_fetch_assoc($res_var)) {
            $v_gene = (!empty($row_var['entrez_gene_symbol']) && $row_var['entrez_gene_symbol'] !== '.') ? $row_var['entrez_gene_symbol'] : 'NA';
            
            // Format RS with "rs" prefix if numeric or not already prefixed
            $v_rsid = (!empty($row_var['rs']) && $row_var['rs'] !== '.') ? trim($row_var['rs']) : 'NA';
            if ($v_rsid !== 'NA' && $v_rsid !== '') {
                if (!preg_match('/^rs/i', $v_rsid)) {
                    $v_rsid = 'rs' . $v_rsid;
                }
            }
            
            // ClinVar ID fetched from clnsig column
            $v_clnsig = (!empty($row_var['clnsig']) && $row_var['clnsig'] !== '.') ? $row_var['clnsig'] : 'NA';
            $v_acmg = (!empty($row_var['acmgclassification']) && $row_var['acmgclassification'] !== '.') ? $row_var['acmgclassification'] : 'NA';
            
            // EVO2 Score fetched from evo2_prediction column
            $v_evo2_pred = (!empty($row_var['evo2_prediction']) && $row_var['evo2_prediction'] !== '.') ? $row_var['evo2_prediction'] : 'NA';

            $variants[] = [
                'gene_name' => $v_gene,
                'rsid' => $v_rsid,
                'clinvar_id' => $v_clnsig,
                'acmg' => $v_acmg,
                'evo2' => $v_evo2_pred,
                'evo2_pred' => $v_evo2_pred,
                'raw_af' => cleanFloat($row_var['af_joint_raw'] ?? null),
                'xx_af' => cleanFloat($row_var['af_joint_xx'] ?? null),
                'xy_af' => cleanFloat($row_var['af_joint_xy'] ?? null),
                
                'afr' => cleanFloat($row_var['af_joint_afr'] ?? null),
                'afr_xx' => cleanFloat($row_var['af_joint_afr_xx'] ?? null),
                'afr_xy' => cleanFloat($row_var['af_joint_afr_xy'] ?? null),

                'ami' => cleanFloat($row_var['af_joint_ami'] ?? null),
                'ami_xx' => cleanFloat($row_var['af_joint_ami_xx'] ?? null),
                'ami_xy' => cleanFloat($row_var['af_joint_ami_xy'] ?? null),

                'amr' => cleanFloat($row_var['af_joint_amr'] ?? null),
                'amr_xx' => cleanFloat($row_var['af_joint_amr_xx'] ?? null),
                'amr_xy' => cleanFloat($row_var['af_joint_amr_xy'] ?? null),

                'asj' => cleanFloat($row_var['af_joint_asj'] ?? null),
                'asj_xx' => cleanFloat($row_var['af_joint_asj_xx'] ?? null),
                'asj_xy' => cleanFloat($row_var['af_joint_asj_xy'] ?? null),

                'eas' => cleanFloat($row_var['af_joint_eas'] ?? null),
                'eas_xx' => cleanFloat($row_var['af_joint_eas_xx'] ?? null),
                'eas_xy' => cleanFloat($row_var['af_joint_eas_xy'] ?? null),

                'fin' => cleanFloat($row_var['af_joint_fin'] ?? null),
                'fin_xx' => cleanFloat($row_var['af_joint_fin_xx'] ?? null),
                'fin_xy' => cleanFloat($row_var['af_joint_fin_xy'] ?? null),

                'mid' => cleanFloat($row_var['af_joint_mid'] ?? null),
                'mid_xx' => cleanFloat($row_var['af_joint_mid_xx'] ?? null),
                'mid_xy' => cleanFloat($row_var['af_joint_mid_xy'] ?? null),

                'nfe' => cleanFloat($row_var['af_joint_nfe'] ?? null),
                'nfe_xx' => cleanFloat($row_var['af_joint_nfe_xx'] ?? null),
                'nfe_xy' => cleanFloat($row_var['af_joint_nfe_xy'] ?? null),

                'rem' => cleanFloat($row_var['af_joint_remaining'] ?? null),
                'rem_xx' => cleanFloat($row_var['af_joint_remaining_xx'] ?? null),
                'rem_xy' => cleanFloat($row_var['af_joint_remaining_xy'] ?? null)
            ];
        }

        // Fast PHP Memory Sorting: Prioritize rows with populated EVO2 data to the top
        usort($variants, function($a, $b) {
            $hasEvo2A = ($a['evo2'] !== 'NA' && $a['evo2'] !== '.' && !empty($a['evo2'])) ? 1 : 0;
            $hasEvo2B = ($b['evo2'] !== 'NA' && $b['evo2'] !== '.' && !empty($b['evo2'])) ? 1 : 0;

            if ($hasEvo2A !== $hasEvo2B) {
                return $hasEvo2B - $hasEvo2A; // Rows with EVO2 data FIRST
            }
            return ($b['raw_af'] <=> $a['raw_af']); // High AF second
        });

        if (empty($variants)) {
            echo json_encode(['success' => false, 'message' => "No genetic variants identified for gene symbol: $gene."]);
        } else {
            echo json_encode([
                'success' => true,
                'gene' => $gene,
                'variants' => $variants
            ]);
        }
    } catch (Exception $e) {
        error_log("Error during gene analysis request: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred processing the data: ' . $e->getMessage()]);
    }
    exit;
}

include 'header.php';
?>

<!-- Select2 Integration CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Custom CSS styles scoped to this page's layout components -->
<style>
    .custom-wide-container {
        width: 100% !important;
        max-width: 98% !important;
        margin: 0 auto;
    }

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

    /* Metrics Figures */
    .sex-card { flex: 1; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid transparent; }
    .female-box { background: #fff5f8; border-color: #ffd1e1; color: #d4537e; }
    .male-box { background: #f0f7ff; border-color: #b3d7ff; color: #185fa5; }
    .sex-val { font-size: 1.5rem; font-weight: 800; display: block; }
    .sex-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }

    /* Radar Containers */
    .radar-header { color: #64748b; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; margin-bottom: 15px; }
    .radar-wrapper { height: 320px; position: relative; }
    
    /* Unified Table Styling */
    .table-custom { 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        font-size: 0.825rem !important; 
        width: 100% !important; 
        letter-spacing: 0.15px;
    }
    .table-custom thead th { 
        background-color: #212529 !important;
        color: #ffffff !important;
        text-transform: uppercase; 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        font-size: 0.785rem !important; 
        white-space: nowrap; 
        font-weight: 700 !important;
        padding: 9px 8px; 
        border: 1px solid #373b3e !important;
        vertical-align: middle;
    }
    .table-custom thead th.pop-group-header {
        background-color: #343a40 !important;
        color: #f8f9fa !important;
        border-left: 2px solid #6c757d !important;
    }
    .table-custom tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
    .table-custom tbody tr:hover { background-color: #f8f9fa; }
    .table-custom tbody tr.selected-row { background-color: #eef9fd !important; border-left: 4px solid #0d6efd; }
    .table-custom tbody td { 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        font-size: 0.825rem !important;
        white-space: nowrap; 
        padding: 8px 10px; 
        border: 1px solid #dee2e6;
        vertical-align: middle;
        color: #212529;
    }
    .badge-sig { 
        padding: 3px 8px; 
        border-radius: 4px; 
        font-weight: 600; 
        font-size: 0.775rem !important; 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        display: inline-block; 
        text-transform: uppercase; 
    }

    /* Sortable Headers */
    .sortable-hdr { cursor: pointer; position: relative; user-select: none; }
    .sortable-hdr:hover { background: #343a40 !important; color: #ffffff !important; }
    .sort-icon { font-size: 0.65rem; margin-left: 4px; color: #adb5bd; }
    .sort-active { color: #38bdf8 !important; font-weight: 800; }

    /* Population Specific Columns Separator styling */
    .pop-group-header {
        border-left: 2px solid #adb5bd !important;
    }

    .legend-custom { display: flex; justify-content: center; gap: 15px; font-size: 0.7rem; font-weight: 600; margin-top: 10px; padding: 8px; background: #f8fafc; border-radius: 10px; }
    .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }

    /* Control Bar Styles */
    .controls-panel { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 15px; flex-wrap: wrap; }
    .pageSizeSelect { width: 75px; display: inline-block; }
    .tableSearchInput { max-width: 250px; }

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
</style>

<!-- Chart.js dependency loaded -->
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
            Population-wise Variant Distribution
        </h1>

        <p style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #212529;
            line-height: 1.5;
            max-width: 900px;
            margin: 0 auto;">
            Explore sex-disaggregated allele frequencies along with geographical distribution.
        </p>
    </div>

    <!-- UTILITY CONTROL BAR WITH DROPDOWN LIST -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <span class="small fw-bold text-muted text-uppercase d-block">Active Search Gene</span>
                    <span id="legendGeneName" class="h4 fw-bold text-primary m-0"><?php echo htmlspecialchars(strtoupper($initial_gene)); ?></span>
                </div>
                <div class="col-md-9">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <select id="geneSelect" class="form-select select2-gene" style="width: 100%;">
                                <?php foreach ($gene_list as $g_sym): ?>
                                    <option value="<?php echo htmlspecialchars($g_sym); ?>" <?php echo (strcasecmp($g_sym, $initial_gene) === 0) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g_sym); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="searchSubmitBtn" class="btn btn-primary px-4 fw-bold text-nowrap" style="height: 38px; border-radius: 0 0.25rem 0.25rem 0;" onclick="updateDashboard()">
                            <i class="fas fa-chart-pie me-1"></i> Analyze
                        </button>
                    </div>
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
                <span class="text-muted fw-bold">Analyzing Dataset Records...</span>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT PANEL: Selected Variant sex-disaggregated allele frequencies -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark">Selected Variant Sex-Disaggregated AF</h5>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between h-100">
                        <div>
                            <p class="text-muted small">Specific allele frequency values mapped to the biological sex for the currently highlighted variant row.</p>
                        </div>
                        <div class="d-flex gap-2 my-4">
                            <div class="sex-card female-box shadow-sm">
                                <span class="fw-bold fs-5">&#9792;</span>
                                <span class="sex-val" id="statXX">0.000000</span>
                                <span class="sex-label">Female AF (XX)</span>
                            </div>
                            <div class="sex-card male-box shadow-sm">
                                <span class="fw-bold fs-5">&#9794;</span>
                                <span class="sex-val" id="statXY">0.000000</span>
                                <span class="sex-label">Male AF (XY)</span>
                            </div>
                        </div>
                        <div class="small text-muted border-top pt-2">
                            Active Locus: <strong id="activeVariantLocus" class="text-primary">-</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Radar Charts -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <!-- RADAR 1: Population Distribution -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0 fw-bold text-dark" id="radarTitle1">Population Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="radar-wrapper">
                                    <canvas id="radarPop"></canvas>
                                </div>
                                <div class="legend-custom mt-3">
                                    <span><span class="dot" style="background:#10428d"></span> Variant Global AF</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- RADAR 2: Sex-Stratified AF -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0 fw-bold text-dark" id="radarTitle2">Sex-Disaggregated AF</h5>
                            </div>
                            <div class="card-body">
                                <div class="radar-wrapper">
                                    <canvas id="radarSex"></canvas>
                                </div>
                                <div class="legend-custom mt-3">
                                    <span><span class="dot" style="background:#d4537e"></span>XX (Female)</span>
                                    <span><span class="dot" style="background:#185fa5"></span>XY (Male)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOWER SECTION: FULL WIDTH SPECIFIC VARIANT EVIDENCE DETAILS TABLE -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm mb-5">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark">Variant Frequency Matrix &amp; Annotation Table</h5>
                    </div>
                    <div class="card-body">

                        <!-- Table Controls Area -->
                        <div class="filter-section">
                            <div class="controls-panel">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small text-muted">Show</span>
                                    <select id="pageSizeSelect" class="form-select form-select-sm pageSizeSelect" onchange="changePageSize(this.value)">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span class="small text-muted">entries</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small text-muted">Filter:</span>
                                    <input type="text" id="tableSearch" class="form-control form-control-sm tableSearchInput" placeholder="Search rows..." oninput="handleTableSearch(this.value)">
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 550px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                            <table class="table table-custom align-middle m-0">
                                <thead>
                                    <tr class="text-center align-middle">
                                        <th rowspan="2">Select</th>
                                        <th rowspan="2">S.No.</th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('gene_name')">Gene Name <span id="sort_gene_name" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('rsid')">RSID <span id="sort_rsid" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('clinvar_id')">ClinVar ID <span id="sort_clinvar_id" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('acmg')">BIAS-ACMG <span id="sort_acmg" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('evo2')">EVO2 Score <span id="sort_evo2" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('raw_af')">Raw AF <span id="sort_raw_af" class="sort-icon sort-active">&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('xx_af')">XX AF <span id="sort_xx_af" class="sort-icon">&#9650;&#9660;</span></th>
                                        <th rowspan="2" class="sortable-hdr" onclick="handleSort('xy_af')">XY AF <span id="sort_xy_af" class="sort-icon">&#9650;&#9660;</span></th>
                                        
                                        <th colspan="3" class="pop-group-header">AFR (African)</th>
                                        <th colspan="3" class="pop-group-header">AMI (Amish)</th>
                                        <th colspan="3" class="pop-group-header">AMR (Admixed American)</th>
                                        <th colspan="3" class="pop-group-header">ASJ (Ashkenazi Jewish)</th>
                                        <th colspan="3" class="pop-group-header">EAS (East Asian)</th>
                                        <th colspan="3" class="pop-group-header">FIN (Finnish)</th>
                                        <th colspan="3" class="pop-group-header">MID (Middle Eastern)</th>
                                        <th colspan="3" class="pop-group-header">NFE (Non-Finnish European)</th>
                                        <th colspan="3" class="pop-group-header">REM (Remaining Populations)</th>
                                    </tr>
                                    <tr class="text-center">
                                        <!-- AFR -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- AMI -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- AMR -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- ASJ -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- EAS -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- FIN -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- MID -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- NFE -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                        <!-- REM -->
                                        <th class="pop-group-header">Global</th><th>XX</th><th>XY</th>
                                    </tr>
                                </thead>
                                <tbody id="variantTable">
                                    <tr>
                                        <td colspan="37" class="text-center text-muted py-5">
                                            <i class="fas fa-search me-2"></i>Search and analyze a gene symbol above to compile population variants.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer Elements -->
                        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                            <div id="tableInfo" class="small text-muted">Showing 0 to 0 of 0 variants</div>
                            <nav aria-label="Page navigation">
                                <ul id="tablePagination" class="pagination pagination-sm m-0">
                                    <!-- Dynamic rendering of page control nodes -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let radarPop, radarSex;
    const popLabels = ['AFR', 'AMI', 'AMR', 'ASJ', 'EAS', 'FIN', 'MID', 'NFE', 'REM'];
    const geneSelect = $('#geneSelect');
    const dashboardLoader = document.getElementById('dashboardLoader');

    // Dynamic state management variables for the variants UI table
    let allVariants = [];
    let filteredVariants = [];
    let currentPage = 1;
    let pageSize = 25;
    let currentSortColumn = 'raw_af';
    let currentSortDirection = 'desc';
    let searchQuery = '';
    
    // Tracks the active highlighted variant globally
    let selectedVariant = null;

    const currentScriptPath = window.location.pathname;

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        setupGeneDropdown();
        
        // Load default dashboard for selected gene
        updateDashboard();
    });

    // Helper function to safely escape HTML special characters
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setupGeneDropdown() {
        geneSelect.select2({
            placeholder: "Select or search Gene Symbol...",
            allowClear: false,
            width: '100%'
        });

        geneSelect.on('change', function() {
            updateDashboard();
        });
    }

    function initCharts() {
        const ctxPop = document.getElementById('radarPop').getContext('2d');
        const ctxSex = document.getElementById('radarSex').getContext('2d');

        const commonOptions = {
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    ticks: { display: false },
                    pointLabels: { font: { size: 9, weight: '700' }, color: '#64748b' },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            },
            plugins: { legend: { display: false } }
        };

        radarPop = new Chart(ctxPop, {
            type: 'radar',
            data: { 
                labels: popLabels, 
                datasets: [{
                    label: 'Global AF',
                    data: Array(popLabels.length).fill(0),
                    borderColor: '#10428d',
                    backgroundColor: 'rgba(16, 66, 141, 0.12)',
                    borderWidth: 2,
                    pointRadius: 3
                }]
            },
            options: commonOptions
        });

        radarSex = new Chart(ctxSex, {
            type: 'radar',
            data: { 
                labels: popLabels, 
                datasets: [
                    {
                        label: 'XX',
                        data: Array(popLabels.length).fill(0),
                        borderColor: '#d4537e',
                        backgroundColor: 'rgba(212, 83, 126, 0.12)',
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'XY',
                        data: Array(popLabels.length).fill(0),
                        borderColor: '#185fa5',
                        backgroundColor: 'rgba(24, 95, 165, 0.12)',
                        borderWidth: 2,
                        pointRadius: 3
                    }
                ]
            },
            options: commonOptions
        });
    }

    // Helper formatting function for allele frequencies
    function formatAF(value) {
        if (!value || value === 0) return '0.00000';
        if (value < 0.0001) {
            return value.toExponential(3); // Scientific notation for rare variant values
        }
        return parseFloat(value).toFixed(5);
    }

    // ===== DYNAMIC METRIC UPDATER =====
    function updateDashboard() {
        let gene = geneSelect.val();
        if (!gene || gene.trim() === '') {
            gene = 'CLOCK';
        }
        gene = gene.trim().toUpperCase();

        dashboardLoader.style.display = 'flex';

        fetch(`${currentScriptPath}?action=analyze&gene=${encodeURIComponent(gene)}`)
            .then(res => res.json())
            .then(res => {
                dashboardLoader.style.display = 'none';

                if (!res.success) {
                    alert(res.message || "No data was returned.");
                    return;
                }

                document.getElementById('legendGeneName').innerText = res.gene;

                // Assign raw data to local state engine
                allVariants = res.variants || [];
                currentPage = 1;

                applyTableChanges();
            })
            .catch(err => {
                dashboardLoader.style.display = 'none';
                console.error("Error retrieving dashboard details:", err);
            });
    }

    // Update charts and metric panels with the selected single variant
    function updateVisualizationCards() {
        if (!selectedVariant) {
            document.getElementById('statXX').innerText = '0.000000';
            document.getElementById('statXY').innerText = '0.000000';
            document.getElementById('activeVariantLocus').innerText = '-';

            radarPop.data.datasets[0].data = Array(popLabels.length).fill(0);
            radarPop.update();

            radarSex.data.datasets[0].data = Array(popLabels.length).fill(0);
            radarSex.data.datasets[1].data = Array(popLabels.length).fill(0);
            radarSex.update();
            return;
        }

        const v = selectedVariant;

        // Update card metrics
        document.getElementById('statXX').innerText = parseFloat(v.xx_af).toFixed(6);
        document.getElementById('statXY').innerText = parseFloat(v.xy_af).toFixed(6);
        document.getElementById('activeVariantLocus').innerText = `${v.gene_name} (${v.rsid !== 'NA' ? v.rsid : v.clinvar_id})`;

        // Compile standard populations global AF order
        const globalPopArray = [v.afr, v.ami, v.amr, v.asj, v.eas, v.fin, v.mid, v.nfe, v.rem];
        radarPop.data.datasets[0].data = globalPopArray;
        radarPop.update();

        // Compile stratified XX & XY array data order
        const xxPopArray = [v.afr_xx, v.ami_xx, v.amr_xx, v.asj_xx, v.eas_xx, v.fin_xx, v.mid_xx, v.nfe_xx, v.rem_xx];
        const xyPopArray = [v.afr_xy, v.ami_xy, v.amr_xy, v.asj_xy, v.eas_xy, v.fin_xy, v.mid_xy, v.nfe_xy, v.rem_xy];

        radarSex.data.datasets[0].data = xxPopArray;
        radarSex.data.datasets[1].data = xyPopArray;
        radarSex.update();
    }

    // Handles user row selection
    window.selectRowVariant = function(indexInFiltered) {
        if (filteredVariants[indexInFiltered]) {
            selectedVariant = filteredVariants[indexInFiltered];
            updateVisualizationCards();
            
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, filteredVariants.length);
            const paginatedData = filteredVariants.slice(startIndex, endIndex);
            renderTableRows(paginatedData, startIndex);
        }
    };

    // ===== CLIENT SIDE SEARCH, SORT, PAGINATION ENGINE =====
    function applyTableChanges() {
        // Step 1: Filter
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            filteredVariants = allVariants.filter(v => 
                (v.gene_name && v.gene_name.toLowerCase().includes(q)) ||
                (v.rsid && v.rsid.toLowerCase().includes(q)) ||
                (v.clinvar_id && v.clinvar_id.toLowerCase().includes(q)) ||
                (v.acmg && v.acmg.toLowerCase().includes(q)) ||
                (v.evo2 && v.evo2.toLowerCase().includes(q))
            );
        } else {
            filteredVariants = [...allVariants];
        }

        // Step 2: Sort (prioritizing rows with valid EVO2 score / prediction data)
        filteredVariants.sort((a, b) => {
            let valA = a[currentSortColumn];
            let valB = b[currentSortColumn];

            let hasEvo2A = (a.evo2 && a.evo2 !== 'NA' && a.evo2 !== '.') ? 1 : 0;
            let hasEvo2B = (b.evo2 && b.evo2 !== 'NA' && b.evo2 !== '.') ? 1 : 0;

            // Maintain rows with valid EVO2 data near top of display list
            if (hasEvo2A !== hasEvo2B) {
                return hasEvo2B - hasEvo2A; // Prioritize valid EVO2 entries
            }

            if (valA === null || valA === undefined) valA = (typeof valB === 'number') ? -999999 : '';
            if (valB === null || valB === undefined) valB = (typeof valA === 'number') ? -999999 : '';

            if (typeof valA === 'string') {
                return currentSortDirection === 'asc' 
                    ? valA.localeCompare(valB) 
                    : valB.localeCompare(valA);
            } else {
                return currentSortDirection === 'asc' 
                    ? valA - valB 
                    : valB - valA;
            }
        });

        // Step 3: Paginate
        const totalEntries = filteredVariants.length;
        const totalPages = Math.ceil(totalEntries / pageSize);
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalEntries);
        const paginatedData = filteredVariants.slice(startIndex, endIndex);

        // Step 4: ALWAYS select the first visible row on the current page by default
        if (paginatedData.length > 0) {
            selectedVariant = paginatedData[0];
        } else {
            selectedVariant = null;
        }

        // Step 5: Render DOM & Cards
        renderTableRows(paginatedData, startIndex);
        renderPaginationControls(totalEntries, totalPages);
        updateSortIcons();
        updateVisualizationCards();
    }

    function renderTableRows(data, startIndex) {
        const vTable = document.getElementById('variantTable');
        vTable.innerHTML = '';

        if (data.length === 0) {
            vTable.innerHTML = `<tr><td colspan="37" class="text-center text-muted py-5">No matching variants found.</td></tr>`;
            return;
        }

        data.forEach((v, index) => {
            let badgeBg = '#f1f5f9', badgeColor = '#475569';
            const text = v.acmg.toLowerCase();
            if (text.includes('benign')) {
                badgeBg = '#dcfce7'; badgeColor = '#198754';
            } else if (text.includes('pathogenic')) {
                badgeBg = '#fee2e2'; badgeColor = '#dc3545';
            } else if (text.includes('uncertain') || text.includes('vus')) {
                badgeBg = '#ffedd5'; badgeColor = '#fd7e14';
            }

            // EVO2 score rendering from evo2_prediction
            const rawEvo2 = (v.evo2 && v.evo2 !== '.' && v.evo2 !== '') ? escapeHtml(v.evo2) : 'NA';

            const serialNumber = startIndex + index + 1;
            
            // First visible row is always selected by default
            const isSelected = (selectedVariant === v);
            const rowClass = isSelected ? 'selected-row' : '';
            const isChecked = isSelected ? 'checked' : '';
            
            const filterIndex = startIndex + index;

            let rsidHtml = 'NA';
            if (v.rsid && v.rsid !== 'NA' && v.rsid !== '.' && v.rsid !== '') {
                let cleanRsid = v.rsid.trim();
                if (!cleanRsid.toLowerCase().startsWith('rs')) {
                    cleanRsid = 'rs' + cleanRsid;
                }
                rsidHtml = `<a href="https://www.ncbi.nlm.nih.gov/snp/${encodeURIComponent(cleanRsid)}" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="text-decoration-none text-primary fw-semibold" 
                               onclick="event.stopPropagation();">
                                ${escapeHtml(cleanRsid)}
                            </a>`;
            }

            vTable.innerHTML += `
                <tr class="${rowClass}" onclick="selectRowVariant(${filterIndex})">
                    <td class="text-center bg-light" onclick="event.stopPropagation(); selectRowVariant(${filterIndex})">
                        <input type="radio" name="variantSelector" class="form-check-input" ${isChecked}>
                    </td>
                    <td class="text-center bg-light">${serialNumber}</td>
                    
                    <td class="text-center bg-light">
                        <a href="gene.php?keyword=${encodeURIComponent(v.gene_name)}" 
                           target="_blank" 
                           class="fw-semibold text-primary text-decoration-none" 
                           onclick="event.stopPropagation();">
                            ${escapeHtml(v.gene_name)}
                        </a>
                    </td>
                    
                    <td class="text-center">${rsidHtml}</td>
                    <td class="text-center">${escapeHtml(v.clinvar_id)}</td>
                    <td class="text-center"><span class="badge-sig" style="background:${badgeBg}; color:${badgeColor};">${escapeHtml(v.acmg)}</span></td>
                    <td class="text-center">${rawEvo2}</td>
                    <td class="text-end">${formatAF(v.raw_af)}</td>
                    <td class="text-end">${formatAF(v.xx_af)}</td>
                    <td class="text-end">${formatAF(v.xy_af)}</td>
                    
                    <!-- AFR -->
                    <td class="pop-group-header text-end">${formatAF(v.afr)}</td><td class="text-end">${formatAF(v.afr_xx)}</td><td class="text-end">${formatAF(v.afr_xy)}</td>
                    <!-- AMI -->
                    <td class="pop-group-header text-end">${formatAF(v.ami)}</td><td class="text-end">${formatAF(v.ami_xx)}</td><td class="text-end">${formatAF(v.ami_xy)}</td>
                    <!-- AMR -->
                    <td class="pop-group-header text-end">${formatAF(v.amr)}</td><td class="text-end">${formatAF(v.amr_xx)}</td><td class="text-end">${formatAF(v.amr_xy)}</td>
                    <!-- ASJ -->
                    <td class="pop-group-header text-end">${formatAF(v.asj)}</td><td class="text-end">${formatAF(v.asj_xx)}</td><td class="text-end">${formatAF(v.asj_xy)}</td>
                    <!-- EAS -->
                    <td class="pop-group-header text-end">${formatAF(v.eas)}</td><td class="text-end">${formatAF(v.eas_xx)}</td><td class="text-end">${formatAF(v.eas_xy)}</td>
                    <!-- FIN -->
                    <td class="pop-group-header text-end">${formatAF(v.fin)}</td><td class="text-end">${formatAF(v.fin_xx)}</td><td class="text-end">${formatAF(v.fin_xy)}</td>
                    <!-- MID -->
                    <td class="pop-group-header text-end">${formatAF(v.mid)}</td><td class="text-end">${formatAF(v.mid_xx)}</td><td class="text-end">${formatAF(v.mid_xy)}</td>
                    <!-- NFE -->
                    <td class="pop-group-header text-end">${formatAF(v.nfe)}</td><td class="text-end">${formatAF(v.nfe_xx)}</td><td class="text-end">${formatAF(v.nfe_xy)}</td>
                    <!-- REM -->
                    <td class="pop-group-header text-end">${formatAF(v.rem)}</td><td class="text-end">${formatAF(v.rem_xx)}</td><td class="text-end">${formatAF(v.rem_xy)}</td>
                </tr>
            `;
        });
    }

    function renderPaginationControls(totalEntries, totalPages) {
        const infoEl = document.getElementById('tableInfo');
        const start = totalEntries === 0 ? 0 : (currentPage - 1) * pageSize + 1;
        const end = Math.min(start + pageSize - 1, totalEntries);
        infoEl.innerText = `Showing ${start} to ${end} of ${totalEntries} variants`;

        const paginationUl = document.getElementById('tablePagination');
        paginationUl.innerHTML = '';

        if (totalPages <= 1) return;

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="setPage(${currentPage - 1})">&laquo;</a>`;
        paginationUl.appendChild(prevLi);

        const maxRange = 5;
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + maxRange - 1);
        if (endPage - startPage + 1 < maxRange) {
            startPage = Math.max(1, endPage - maxRange + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="setPage(${i})">${i}</a>`;
            paginationUl.appendChild(li);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="setPage(${currentPage + 1})">&raquo;</a>`;
        paginationUl.appendChild(nextLi);
    }

    function updateSortIcons() {
        const headers = ['gene_name', 'rsid', 'clinvar_id', 'acmg', 'evo2', 'raw_af', 'xx_af', 'xy_af'];
        headers.forEach(h => {
            const el = document.getElementById(`sort_${h}`);
            if (!el) return;
            if (h === currentSortColumn) {
                el.textContent = currentSortDirection === 'asc' ? '\u25B2' : '\u25BC';
                el.className = 'sort-icon sort-active';
            } else {
                el.textContent = '\u25B2\u25BC';
                el.className = 'sort-icon';
            }
        });
    }

    // ===== CONTROLS EVENT HANDLERS =====
    window.setPage = function(page) {
        currentPage = page;
        applyTableChanges();
    };

    window.changePageSize = function(size) {
        pageSize = parseInt(size);
        currentPage = 1;
        applyTableChanges();
    };

    window.handleTableSearch = function(val) {
        searchQuery = val.trim();
        currentPage = 1;
        applyTableChanges();
    };

    window.handleSort = function(column) {
        if (currentSortColumn === column) {
            currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortColumn = column;
            currentSortDirection = 'desc';
        }
        currentPage = 1;
        applyTableChanges();
    };
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>