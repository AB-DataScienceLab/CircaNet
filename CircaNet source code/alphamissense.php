<?php
// 1. Include the database connection and layout
require_once 'conn.php';

// Ensure standard mysqli connection variable is mapped
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo; 
}

// Fetch general database summary aggregates for the statistics row
$stats = ['total_proteins' => 0, 'avg_length' => 0, 'avg_burden' => 0.0, 'avg_pathogenic' => 0.0];
try {
    $stats_sql = "SELECT 
                    COUNT(*) as total_count, 
                    AVG(protein_length) as avg_len, 
                    AVG(max_burden_percent) as avg_burd, 
                    AVG(pathogenic_sub_fraction) as avg_path 
                  FROM alphamissense_metric";
    $stats_result = $conn->query($stats_sql);
    if ($stats_result && $row = $stats_result->fetch_assoc()) {
        $stats['total_proteins'] = $row['total_count'];
        $stats['avg_length']     = $row['avg_len'];
        $stats['avg_burden']     = $row['avg_burd'];
        $stats['avg_pathogenic'] = $row['avg_path'];
    }
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// 2. Sanitize and Validate Input Parameters
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$risk_filter  = isset($_GET['risk_filter']) ? $_GET['risk_filter'] : 'all';

// Whitelist columns to prevent SQL injection during dynamic sorting
$allowed_sort_columns = [
    'gene_symbol', 'uniprot_id', 'protein_length', 'high_risk_residues', 
    'max_burden_percent', 'mean_am_score', 'total_substitutions', 
    'pathogenic_substitutions', 'pathogenic_sub_fraction'
];
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'gene_symbol';
$order   = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

// Normalize column prefixes for SQL select order statements
$sort_by_prefix = ($sort_by === 'gene_symbol') ? 'm.gene_symbol' : (($sort_by === 'uniprot_id') ? 'am.uniprot_id' : 'am.' . $sort_by);

// Dropdown pagination size options
$allowed_limits = [5, 10, 25, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 10;

// Current Page
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// 3. Build the WHERE clause dynamically
$where_clauses = [];
$bind_types = '';
$bind_params = [];

if ($search !== '') {
    // Allows searching by either HGNC Gene Symbol OR Uniprot ID
    $where_clauses[] = "(m.gene_symbol LIKE ? OR am.uniprot_id LIKE ?)";
    $bind_types .= 'ss';
    $bind_params[] = "%" . $search . "%";
    $bind_params[] = "%" . $search . "%";
}

if ($risk_filter === 'high') {
    $where_clauses[] = "am.max_burden_percent >= 50";
} elseif ($risk_filter === 'medium') {
    $where_clauses[] = "am.max_burden_percent >= 10 AND am.max_burden_percent < 50";
} elseif ($risk_filter === 'low') {
    $where_clauses[] = "am.max_burden_percent < 10";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// 4. Get total count of matching records (Joined with main_tb)
$count_query = "SELECT COUNT(*) 
                FROM alphamissense_metric am
                LEFT JOIN main_tb m ON am.uniprot_id = m.uniprot_id 
                $where_sql";
$count_stmt  = $conn->prepare($count_query);

if ($count_stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

if ($search !== '') {
    $count_stmt->bind_param($bind_types, ...$bind_params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_row()[0];
$count_stmt->close();

// Calculate pagination logic
$total_pages = ceil($total_records / $limit);
if ($total_pages < 1) {
    $total_pages = 1;
}
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// 5. Fetch the data for the current page (with LEFT JOIN on main_tb)
$data_query = "SELECT am.*, m.gene_symbol 
               FROM alphamissense_metric am
               LEFT JOIN main_tb m ON am.uniprot_id = m.uniprot_id
               $where_sql 
               ORDER BY $sort_by_prefix $order 
               LIMIT ? OFFSET ?";

$data_stmt = $conn->prepare($data_query);

if ($data_stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

// Prepare parameters for search + limit + offset
$data_types = $bind_types . 'ii';
$data_params = $bind_params;
$data_params[] = $limit;
$data_params[] = $offset;

// Bind all parameters dynamically
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$data_result = $data_stmt->get_result();

$results = [];
while ($row = $data_result->fetch_assoc()) {
    $results[] = $row;
}
$data_stmt->close();

// Prep active table dataset arrays for Chart.js
$chart_labels = [];
$chart_burden = [];
$chart_pathogenic = [];
foreach ($results as $row) {
    $chart_labels[]     = !empty($row['gene_symbol']) ? $row['gene_symbol'] : $row['uniprot_id'];
    $chart_burden[]     = round($row['max_burden_percent'], 2);
    $chart_pathogenic[] = round($row['pathogenic_sub_fraction'], 2);
}

// Helper to retain active autocomplete selection across page reloads
$search_display_text = '';
if ($search !== '') {
    $display_sql = "SELECT DISTINCT am.uniprot_id, m.gene_symbol 
                    FROM alphamissense_metric am
                    LEFT JOIN main_tb m ON am.uniprot_id = m.uniprot_id
                    WHERE m.gene_symbol = ? OR am.uniprot_id = ? 
                    LIMIT 1";
    $display_stmt = $conn->prepare($display_sql);
    $display_stmt->bind_param('ss', $search, $search);
    $display_stmt->execute();
    $display_res = $display_stmt->get_result();
    if ($disp_row = $display_res->fetch_assoc()) {
        $search_display_text = !empty($disp_row['gene_symbol']) 
            ? htmlspecialchars($disp_row['gene_symbol']) . " (" . htmlspecialchars($disp_row['uniprot_id']) . ")" 
            : htmlspecialchars($disp_row['uniprot_id']);
    } else {
        $search_display_text = htmlspecialchars($search);
    }
    $display_stmt->close();
}

// Helper function to build query strings preserving existing filters
function buildUrl($params_to_merge) {
    $current_params = $_GET;
    $merged = array_merge($current_params, $params_to_merge);
    return '?' . http_build_query($merged);
}

// Helper to display clean sorting arrow icons
function getSortIndicator($col, $current_sort, $current_order) {
    if ($current_sort === $col) {
        return $current_order === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    }
    return ' <i class="fas fa-sort text-muted opacity-50"></i>';
}

// 6. Include Page Header
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AlphaMissense Pathogenicity Explorer</title>
    
    <!-- External Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
    .main-content { margin-bottom: 40px; }
    
    /* Pastel blue & orange gradient header style */
    .pastel-header {
        background: #F8F9FA !important;
        color: #2c3e50 !important;
        font-weight: 600;
        border-bottom: 1px solid #dee2e6;
    }

    /* Soft background layout card container */
    .pastel-card {
        background: linear-gradient(180deg, #f3f7fa 0%, #fffdfa 100%) !important;
        border: 1px solid #d2e1ed !important;
    }

    /* System-wide table column header pastel style */
    .table.pastel-table thead th {
        background: #F8F9FA !important;
        color: #2c3e50 !important;
        vertical-align: middle;
        border-bottom: 2px solid #b8ccd9 !important;
        font-weight: 600;
        text-align: center;
    }
    
    .table.pastel-table thead th a {
        color: #2c3e50 !important;
        text-decoration: none;
        display: block;
    }

    .table.pastel-table tbody td {
        vertical-align: middle;
    }

    /* Dynamic Column-wise Background Group Styling */
    .bg-col-primary { background-color: rgba(44, 62, 80, 0.015) !important; }
    .bg-col-burden { background-color: rgba(255, 216, 177, 0.10) !important; }
    .bg-col-score { background-color: rgba(188, 212, 230, 0.12) !important; }
    .bg-col-path { background-color: rgba(220, 53, 69, 0.02) !important; }

    /* Visual indicators for risk metric progress bars */
    .progress-micro {
        height: 6px;
        border-radius: 3px;
        background-color: #e9ecef;
    }

    /* Specific select2 sizing adjustments to match theme */
    .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
    </style>
</head>
<body>

<div class="main-content">

    <!-- Page Title Block -->
    <div class="text-center mb-5">
        <h1 style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 40px;
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;">
            AlphaMissense Pathogenicity Explorer
        </h1>

        <p style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #212529;
            line-height: 1.5;
            margin-bottom: 0;">
            An interactive dashboard exploring predicted structural burden and substitution pathogenicity metrics.
        </p>
    </div>

</div>

        <!-- Dashboard Summary Row (Quick Stats) -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="p-3 bg-light rounded-circle text-primary me-3"><i class="fas fa-dna fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted small mb-1">Total Proteins</h6>
                            <h4 class="mb-0 fw-bold"><?= number_format($stats['total_proteins']); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="p-3 bg-light rounded-circle text-success me-3"><i class="fas fa-ruler-horizontal fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted small mb-1">Avg. Protein Length</h6>
                            <h4 class="mb-0 fw-bold"><?= number_format($stats['avg_length']); ?> <span class="small text-muted fw-normal">aa</span></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="p-3 bg-light rounded-circle text-warning me-3"><i class="fas fa-biohazard fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted small mb-1">Avg. Max Burden</h6>
                            <h4 class="mb-0 fw-bold"><?= number_format($stats['avg_burden'], 2); ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-white h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="p-3 bg-light rounded-circle text-danger me-3"><i class="fas fa-exclamation-triangle fa-lg"></i></div>
                        <div>
                            <h6 class="text-muted small mb-1">Avg. Pathogenic Fraction</h6>
                            <h4 class="mb-0 fw-bold"><?= number_format($stats['avg_pathogenic'], 2); ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Controls & Downloads Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body bg-light">
                <div class="row g-3 align-items-end">
                    <!-- Filtering Form Area (Submits automatically on input changes) -->
                    <div class="col-lg-8">
                        <form id="filterForm" method="GET" action="" class="row g-3">
                            <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by); ?>">
                            <input type="hidden" name="order" value="<?= htmlspecialchars($order); ?>">

                            <!-- Select2 Auto-Suggest Search Input -->
                            <div class="col-md-5">
                                <label for="search" class="form-label fw-bold small">Search Gene Symbol or UniProt ID:</label>
                                <select name="search" id="search" class="form-select" style="width:100%;">
                                    <?php if ($search !== ''): ?>
                                        <option value="<?= htmlspecialchars($search); ?>" selected><?= $search_display_text; ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Risk Filter Selection -->
                            <div class="col-md-4">
                                <label for="risk_filter" class="form-label fw-bold small">Risk Level (Burden %):</label>
                                <select name="risk_filter" id="risk_filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $risk_filter === 'all' ? 'selected' : ''; ?>>All Risks</option>
                                    <option value="high" <?= $risk_filter === 'high' ? 'selected' : ''; ?>>High Risk (&ge; 50%)</option>
                                    <option value="medium" <?= $risk_filter === 'medium' ? 'selected' : ''; ?>>Medium Risk (10% - 49%)</option>
                                    <option value="low" <?= $risk_filter === 'low' ? 'selected' : ''; ?>>Low Risk (&lt; 10%)</option>
                                </select>
                            </div>

                            <!-- Page Size Selection -->
                            <div class="col-md-3">
                                <label for="limit" class="form-label fw-bold small">Show Entries:</label>
                                <select name="limit" id="limit" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($allowed_limits as $opt): ?>
                                        <option value="<?= $opt; ?>" <?= $limit === $opt ? 'selected' : ''; ?>>
                                            <?= $opt; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Action and Download Buttons -->
                    <div class="col-lg-4">
                        <div class="row g-2">
                            <div class="col-12">
                                <a href="?" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-undo"></i> Reset Filters
                                </a>
                            </div>
                            <!-- Export Download Options -->
                            <div class="col-6">
                                <a href="download_alphamissense.php?format=csv&<?= http_build_query($_GET) ?>" class="btn btn-success w-100">
                                    <i class="fas fa-file-csv"></i> Download CSV
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="download_alphamissense.php?format=tsv&<?= http_build_query($_GET) ?>" class="btn btn-dark w-100">
                                    <i class="fas fa-file-code"></i> Download TSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js Visualization Overlay Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header pastel-header">
                <h3 class="mb-0 h5"><i class="fas fa-chart-bar me-2"></i>Distribution updates dynamically.</h3>
            </div>
            <div class="card-body bg-white">
                <div style="position: relative; height:320px; width:100%;">
                    <canvas id="burdenChart"></canvas>
                </div>
            </div>
        </div>

        <!-- AlphaMissense Table Dataset Card -->
        <div class="card shadow-sm pastel-card mb-5">
            <div class="card-header pastel-header">
                <h3 class="mb-0 h5"><i class="fas fa-table me-2"></i>AlphaMissense Pathogenicity Burden</h3>
            </div>
            <div class="card-body bg-white">
                <div class="table-responsive">
                    <table class="table table-bordered pastel-table" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Sr. No.</th>
                                <th class="bg-col-primary">
                                    <a href="<?= buildUrl(['sort_by' => 'gene_symbol', 'order' => ($sort_by === 'gene_symbol' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Gene Symbol <?= getSortIndicator('gene_symbol', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-primary">
                                    <a href="<?= buildUrl(['sort_by' => 'uniprot_id', 'order' => ($sort_by === 'uniprot_id' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        UniProt ID <?= getSortIndicator('uniprot_id', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-burden">
                                    <a href="<?= buildUrl(['sort_by' => 'protein_length', 'order' => ($sort_by === 'protein_length' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Length (aa) <?= getSortIndicator('protein_length', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-burden">
                                    <a href="<?= buildUrl(['sort_by' => 'high_risk_residues', 'order' => ($sort_by === 'high_risk_residues' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        High-Risk Residues <?= getSortIndicator('high_risk_residues', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-burden">
                                    <a href="<?= buildUrl(['sort_by' => 'max_burden_percent', 'order' => ($sort_by === 'max_burden_percent' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Max Burden % <?= getSortIndicator('max_burden_percent', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-score">
                                    <a href="<?= buildUrl(['sort_by' => 'mean_am_score', 'order' => ($sort_by === 'mean_am_score' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Mean AM Score <?= getSortIndicator('mean_am_score', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-path">
                                    <a href="<?= buildUrl(['sort_by' => 'total_substitutions', 'order' => ($sort_by === 'total_substitutions' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Total Subs <?= getSortIndicator('total_substitutions', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-path">
                                    <a href="<?= buildUrl(['sort_by' => 'pathogenic_substitutions', 'order' => ($sort_by === 'pathogenic_substitutions' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Pathogenic Subs <?= getSortIndicator('pathogenic_substitutions', $sort_by, $order); ?>
                                    </a>
                                </th>
                                <th class="bg-col-path" style="min-width: 180px;">
                                    <a href="<?= buildUrl(['sort_by' => 'pathogenic_sub_fraction', 'order' => ($sort_by === 'pathogenic_sub_fraction' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                        Pathogenic Fraction % <?= getSortIndicator('pathogenic_sub_fraction', $sort_by, $order); ?>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) > 0): ?>
                                <?php 
                                $row_index = $offset + 1; // Compute absolute sequential Sr. No. 
                                foreach ($results as $row): 
                                ?>
                                    <tr>
                                        <td class="text-center font-monospace small text-muted"><?= $row_index++; ?></td>
                                        
                                        <!-- Mapped Gene Symbol from main_tb with CircaNet Link -->
                                        <td class="text-center fw-bold bg-col-primary">
                                            <?php if (!empty($row['gene_symbol'])): ?>
                                                <a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=<?= urlencode($row['gene_symbol']); ?>" target="_blank" class="text-decoration-none text-dark">
                                                    <?= htmlspecialchars($row['gene_symbol']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small fw-normal">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- UniProt ID with Dynamic UniProtKB Redirect Link -->
                                        <td class="text-center bg-col-primary">
                                            <a href="https://www.uniprot.org/uniprotkb/<?= urlencode($row['uniprot_id']); ?>/entry" target="_blank" class="text-decoration-none">
                                                <span class="badge bg-secondary font-monospace p-2"><?= htmlspecialchars($row['uniprot_id']); ?></span>
                                            </a>
                                        </td>
                                        <td class="text-end bg-col-burden"><?= number_format($row['protein_length']); ?></td>
                                        <td class="text-end bg-col-burden"><?= number_format($row['high_risk_residues']); ?></td>
                                        
                                        <!-- Max Burden Column with Progress Bar Visual -->
                                        <td class="bg-col-burden">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="small fw-bold"><?= number_format($row['max_burden_percent'], 2); ?>%</span>
                                            </div>
                                            <div class="progress progress-micro">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $row['max_burden_percent']); ?>%" aria-valuenow="<?= $row['max_burden_percent']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                        
                                        <td class="text-end fw-bold text-primary bg-col-score"><?= number_format($row['mean_am_score'], 4); ?></td>
                                        <td class="text-end bg-col-path"><?= number_format($row['total_substitutions']); ?></td>
                                        <td class="text-end text-danger bg-col-path"><?= number_format($row['pathogenic_substitutions']); ?></td>
                                        
                                        <!-- Pathogenic Fraction Column with Color-Coded Progress Bar -->
                                        <td class="bg-col-path">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="small fw-bold"><?= number_format($row['pathogenic_sub_fraction'], 2); ?>%</span>
                                            </div>
                                            <div class="progress progress-micro">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?= min(100, $row['pathogenic_sub_fraction']); ?>%" aria-valuenow="<?= $row['pathogenic_sub_fraction']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <i class="fas fa-exclamation-circle fa-lg me-2"></i>No records found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Area -->
                <div class="row mt-4 align-items-center">
                    <!-- Info Text -->
                    <div class="col-md-6 text-center text-md-start text-muted mb-3 mb-md-0 small">
                        Showing <?= $total_records > 0 ? $offset + 1 : 0; ?> 
                        to <?= min($offset + $limit, $total_records); ?> 
                        of <?= $total_records; ?> entries
                    </div>
                    
                    <!-- Truncated/Sliding Window Pagination Buttons -->
                    <div class="col-md-6">
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                                    <!-- First Page Link -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= buildUrl(['page' => 1]); ?>" aria-label="First">
                                            First
                                        </a>
                                    </li>
                                    
                                    <!-- Previous Page Link -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= buildUrl(['page' => $page - 1]); ?>" aria-label="Previous">
                                            &laquo;
                                        </a>
                                    </li>

                                    <?php
                                    // Sliding window configuration
                                    $adjacents = 2; // Show 2 pages before and 2 pages after the active page
                                    $start_page = max(1, $page - $adjacents);
                                    $end_page = min($total_pages, $page + $adjacents);

                                    // Display direct link to page 1 and ellipsis if page 1 is skipped in range
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="'.buildUrl(['page' => 1]).'">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }

                                    // Print page range window links
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $active_class = ($page === $i) ? 'active' : '';
                                        echo '<li class="page-item '.$active_class.'"><a class="page-link" href="'.buildUrl(['page' => $i]).'">'.$i.'</a></li>';
                                    }

                                    // Display direct link to final page and ellipsis if last pages skipped
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="'.buildUrl(['page' => $total_pages]).'">'.$total_pages.'</a></li>';
                                    }
                                    ?>

                                    <!-- Next Page Link -->
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= buildUrl(['page' => $page + 1]); ?>" aria-label="Next">
                                            &raquo;
                                        </a>
                                    </li>
                                    
                                    <!-- Last Page Link -->
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= buildUrl(['page' => $total_pages]); ?>" aria-label="Last">
                                            Last
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2 with dynamic AJAX lookup on the search select element
        $('#search').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search Gene Symbol or UniProt ID...',
            allowClear: true,
            minimumInputLength: 1, 
            ajax: {
                url: 'search_gene_uniprot.php',
                dataType: 'json',
                delay: 250, 
                data: function (params) {
                    return {
                        term: params.term 
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });

        // Auto-Submit event bindings for seamless filtering without clicking submit button
        $('#search').on('select2:select select2:unselect', function() {
            $('#filterForm').submit();
        });

        // Initialize Chart 1: Bar Comparison Chart (Max Burden % vs Pathogenic Fraction %)
        const ctxBar = document.getElementById('burdenChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Max Burden %',
                        data: <?= json_encode($chart_burden); ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.65)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Pathogenic Fraction %',
                        data: <?= json_encode($chart_pathogenic); ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.65)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Protein Burden vs. Pathogenicity (Active Page)',
                        font: { size: 14, weight: 'bold' }
                    },
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Percentage (%)' }
                    }
                }
            }
        });
    });
</script>

</body>
</html>
<?php
$conn->close();
include 'footer.php';
?>