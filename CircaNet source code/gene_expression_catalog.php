<?php
/**
 * Gene Expression Catalog - Standalone Module
 * Tables: tissue_wise_tb_v2
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'header.php';
?>

<!-- DataTables & Select2 Integration CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Chart.js dependency -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

<style>
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif !important;
        background-color: #f8f9fa;
        color: #212529;
    }

    .custom-wide-container {
        width: 100% !important;
        max-width: 98% !important;
        margin: 0 auto;
    }

    .card {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6;
    }

    .chart-container { 
        position: relative; 
        height: 280px; 
        width: 100%; 
    }
    .filter-section { 
        background-color: #f8f9fa; 
        padding: 18px; 
        border-radius: 8px; 
        margin-bottom: 20px; 
        border: 1px solid #dee2e6; 
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

    /* Custom Loading Overlay */
    .dashboard-view-wrapper { position: relative; }
    .dashboard-loader-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        display: flex;
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

    .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px; text-transform: capitalize; }
    .dot { width: 12px; height: 12px; border-radius: 2px; display: inline-block; }
</style>

<div class="custom-wide-container pb-4 pt-0">

    <!-- CENTERED HEADER -->
    <div class="text-center mb-5">
        <h1 style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 40px;
            font-weight: 700;
            color: #212529;
            margin-top: 0;
            padding-top: 0;
            margin-bottom: 5px;">
            Gene Expression Catalog
        </h1>

        <p style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #212529;
            line-height: 1.5;
            max-width: 900px;
            margin: 0 auto;">
            Comprehensive directory of human gene specificity indices, maximum expression tiers, and top-expressed organs.
        </p>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="dashboard-view-wrapper">
        
        <!-- Spinner Overlay -->
        <div id="dashboardLoader" class="dashboard-loader-overlay">
            <div class="custom-table-loader">
                <div class="custom-loader-spinner"></div>
                <span class="text-muted fw-bold">Loading 16,545 Catalog Records...</span>
            </div>
        </div>

        <!-- TOP ROW: METRIC CARDS -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-primary mb-0" id="statTotalGenes">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Total Catalog Genes</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-danger mb-0" id="statSpecificGenes">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Tissue-Specific Genes</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-success mb-0" id="statHkGenes">0</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Housekeeping / Broad</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="fw-bold text-warning mb-0" id="statAvgTau">0.000</h3>
                        <div class="text-muted small fw-bold text-uppercase mt-1">Average Tau Index</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VISUALIZATION SUMMARY CHARTS -->
        <div class="row g-4 mb-4">
            <!-- Top Organs Chart -->
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Top Organ Expression Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topTissueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Specificity Breakdown Donut -->
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Specificity Category Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="specDonutChart"></canvas>
                        </div>
                        <div class="mt-3 d-flex justify-content-center gap-3" id="specLegend"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DATA TABLE CARD -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>Comprehensive Gene Registry</h5>
                <span class="badge bg-secondary fs-6"><i class="fas fa-database me-1"></i> 16,545 Total Records</span>
            </div>
            <div class="card-body">
                
                <!-- DRILL-DOWN FILTERS -->
                <div class="filter-section">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="filterTissue" class="form-label small fw-bold text-muted">Filter by Top Tissue:</label>
                            <select id="filterTissue" class="form-select">
                                <option value="">All Tissues</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filterSpecificity" class="form-label small fw-bold text-muted">Filter by Specificity:</label>
                            <select id="filterSpecificity" class="form-select">
                                <option value="">All Specificities</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tableSearchInput" class="form-label small fw-bold text-muted">Search Catalog:</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="tableSearchInput" class="form-control border-start-0" placeholder="Filter by Symbol, Name or ID...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="catalogTable" class="table table-custom table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 60px;">S.No.</th>
                                <th>Gene ID</th>
                                <th>Gene Name</th>
                                <th>Approved Name</th>
                                <th>Top Tissue</th>
                                <th>Max Log2 Exp</th>
                                <th>Tau (&tau;)</th>
                                <th>Gini</th>
                                <th>Specificity</th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
let topTissueChart, specDonutChart, catalogDataTable;
const dashboardLoader = document.getElementById('dashboardLoader');

$(document).ready(function() {
    initCharts();
    loadCatalogData();
});

function initCharts() {
    // Bar Chart
    const ctxTissue = document.getElementById('topTissueChart').getContext('2d');
    topTissueChart = new Chart(ctxTissue, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Gene Count',
                data: [],
                backgroundColor: '#0d6efd',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 30 } },
                y: { grid: { color: '#f1f5f9' }, beginAtZero: true }
            }
        }
    });

    // Donut Chart
    const ctxSpec = document.getElementById('specDonutChart').getContext('2d');
    specDonutChart = new Chart(ctxSpec, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: ['#dc3545', '#198754', '#6c757d', '#fd7e14', '#0dcaf0'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { display: false } }
        }
    });
}

function loadCatalogData() {
    fetch('get_data.php?action=get_genes')
        .then(res => res.json())
        .then(res => {
            dashboardLoader.style.display = 'none';

            if (!res.success || !res.data) {
                alert("Failed to load catalog records from database.");
                return;
            }

            const data = res.data;
            processMetricsAndCharts(data);
            populateFilters(data);
            initTable(data);
        })
        .catch(err => {
            dashboardLoader.style.display = 'none';
            console.error("Catalog load error: ", err);
        });
}

function processMetricsAndCharts(data) {
    let specificCount = 0;
    let hkCount = 0;
    let tauSum = 0;
    let tauCount = 0;

    const tissueCounts = {};
    const specCounts = {};

    data.forEach(g => {
        const spec = (g.Specificity || 'Intermediate').trim();
        const specLow = spec.toLowerCase();
        const topT = (g.Top_Tissue || 'Unassigned').replace(/_/g, ' ');
        const tau = parseFloat(g.Tau || 0.0);

        if (specLow.includes('specific')) specificCount++;
        if (specLow.includes('housekeeping') || specLow.includes('broad')) hkCount++;

        if (tau > 0) {
            tauSum += tau;
            tauCount++;
        }

        tissueCounts[topT] = (tissueCounts[topT] || 0) + 1;
        specCounts[spec] = (specCounts[spec] || 0) + 1;
    });

    // 1. Update KPI Card numbers
    document.getElementById('statTotalGenes').innerText = data.length.toLocaleString();
    document.getElementById('statSpecificGenes').innerText = specificCount.toLocaleString();
    document.getElementById('statHkGenes').innerText = hkCount.toLocaleString();
    document.getElementById('statAvgTau').innerText = tauCount > 0 ? (tauSum / tauCount).toFixed(3) : '0.000';

    // 2. Update Top Tissue Bar Chart (Top 8 Tissues)
    const sortedTissues = Object.entries(tissueCounts).sort((a, b) => b[1] - a[1]).slice(0, 8);
    topTissueChart.data.labels = sortedTissues.map(item => item[0]);
    topTissueChart.data.datasets[0].data = sortedTissues.map(item => item[1]);
    topTissueChart.update();

    // 3. Update Specificity Donut Chart
    const specLabels = Object.keys(specCounts);
    const specData = Object.values(specCounts);
    specDonutChart.data.labels = specLabels;
    specDonutChart.data.datasets[0].data = specData;
    specDonutChart.update();

    const specColors = ['#dc3545', '#198754', '#6c757d', '#fd7e14', '#0dcaf0'];
    const specLegend = document.getElementById('specLegend');
    specLegend.innerHTML = '';
    specLabels.forEach((label, idx) => {
        const color = specColors[idx % specColors.length];
        specLegend.innerHTML += `
            <div class="legend-item"><span class="dot" style="background:${color}"></span> ${label} (${specData[idx]})</div>
        `;
    });
}

function populateFilters(data) {
    const uniqueTissues = [...new Set(data.map(g => (g.Top_Tissue || '').replace(/_/g, ' ')))].filter(Boolean).sort();
    const uniqueSpecs = [...new Set(data.map(g => g.Specificity || ''))].filter(Boolean).sort();

    let tissueHtml = '<option value="">All Tissues</option>';
    uniqueTissues.forEach(t => { tissueHtml += `<option value="${t}">${t}</option>`; });
    $('#filterTissue').html(tissueHtml);

    let specHtml = '<option value="">All Specificities</option>';
    uniqueSpecs.forEach(s => { specHtml += `<option value="${s}">${s}</option>`; });
    $('#filterSpecificity').html(specHtml);

    // Filter events
    $('#filterTissue').on('change', function() {
        catalogDataTable.column(4).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
    });

    $('#filterSpecificity').on('change', function() {
        catalogDataTable.column(8).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
    });

    $('#tableSearchInput').on('keyup input', function() {
        catalogDataTable.search(this.value).draw();
    });
}

function initTable(data) {
    catalogDataTable = $('#catalogTable').DataTable({
        data: data,
        deferRender: true,
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: [[5, 'desc']], // Order by Max Log2 Exp
        dom: 'lrtip',
        columns: [
            { data: null, defaultContent: '' },
            { data: 'Gene_ID' },
            { data: 'Gene_Name' },
            { data: 'Approved_name' },
            { data: 'Top_Tissue' },
            { data: 'Max_TPM' },
            { data: 'Tau' },
            { data: 'Gini' },
            { data: 'Specificity' },
            { data: null, defaultContent: '' }
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
                render: function(data) {
                    return `<a href="https://www.ensembl.org/id/${encodeURIComponent(data)}" target="_blank" class="fw-bold text-primary text-decoration-none">${data}</a>`;
                }
            },
            {
                targets: 2,
                render: function(data, type, row) {
                    const symbol = data || row.Approved_symbol || row.Gene_ID;
                    return `<a href="gene.php?keyword=${encodeURIComponent(symbol)}" target="_blank" class="fw-bold text-primary text-decoration-none">${symbol}</a>`;
                }
            },
            {
                targets: 3,
                render: function(data) {
                    return `<span class="text-muted small">${(data && data !== 'NULL') ? data : '-'}</span>`;
                }
            },
            {
                targets: 4,
                render: function(data) {
                    return `<span class="badge bg-light text-dark border">${(data || '').replace(/_/g, ' ')}</span>`;
                }
            },
            {
                targets: 5,
                render: function(data) {
                    return `<span class="fw-bold">${parseFloat(data || 0).toFixed(2)}</span>`;
                }
            },
            {
                targets: 6,
                render: function(data) {
                    return parseFloat(data || 0).toFixed(3);
                }
            },
            {
                targets: 7,
                render: function(data) {
                    return parseFloat(data || 0).toFixed(3);
                }
            },
            {
                targets: 8,
                render: function(data) {
                    let badgeClass = 'bg-secondary';
                    const s = (data || '').toLowerCase();
                    if (s.includes('housekeeping') || s.includes('broad')) badgeClass = 'bg-success';
                    else if (s.includes('specific')) badgeClass = 'bg-danger';
                    return `<span class="badge ${badgeClass}">${data || 'Intermediate'}</span>`;
                }
            },
            {
                targets: 9,
                searchable: false,
                orderable: false,
                render: function(data, type, row) {
                    const gene = row.Gene_Name || row.Approved_symbol || row.Gene_ID;
                    return `<a href="Cross_tissue.php?gene=${encodeURIComponent(gene)}" class="btn btn-outline-primary btn-sm py-0 px-2 fw-semibold">
                                <i class="bi bi-graph-up me-1"></i> Plot
                            </a>`;
                }
            }
        ]
    });
}
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>