<?php
require_once 'conn.php';

// Dynamic AJAX search endpoint for the Approved Symbol auto-suggest
if (isset($_GET['action']) && $_GET['action'] === 'search_symbols') {
    header('Content-Type: application/json');
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    $results = [];
    
    try {
        if ($term !== '') {
            $stmt = $conn->prepare("SELECT DISTINCT Approved_symbol 
                                    FROM Disease_tb 
                                    WHERE Approved_symbol LIKE ? 
                                      AND Approved_symbol IS NOT NULL 
                                      AND Approved_symbol != '' 
                                    ORDER BY Approved_symbol ASC 
                                    LIMIT 30");
            $likeTerm = '%' . $term . '%';
            $stmt->bind_param('s', $likeTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $results[] = [
                    'id' => $row['Approved_symbol'],
                    'text' => $row['Approved_symbol']
                ];
            }
            $stmt->close();
        } else {
            // Load top 30 default options when the user opens the dropdown without typing
            $res = $conn->query("SELECT DISTINCT Approved_symbol 
                                 FROM Disease_tb 
                                 WHERE Approved_symbol IS NOT NULL 
                                   AND Approved_symbol != '' 
                                 ORDER BY Approved_symbol ASC 
                                 LIMIT 30");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $results[] = [
                        'id' => $row['Approved_symbol'],
                        'text' => $row['Approved_symbol']
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in disease.php symbol autocomplete: " . $e->getMessage());
    }
    
    echo json_encode($results);
    exit;
}

include 'header.php';

// Data for the Chart
$chart_labels = [];
$chart_data = [];

try {
    // Excluded 'Disgenet' and 'ClinGen' from the graphical summary query
    $chart_sql = "SELECT diseaseName, COUNT(DISTINCT Approved_symbol) as gene_count 
                  FROM Disease_tb 
                  WHERE diseaseName IS NOT NULL 
                    AND diseaseName != '' 
                    AND Source NOT IN ('Disgenet', 'ClinGen')
                  GROUP BY diseaseName 
                  ORDER BY gene_count DESC, diseaseName ASC
                  LIMIT 10";
    
    $chart_result = $conn->query($chart_sql);
    
    if ($chart_result && $chart_result->num_rows > 0) {
        while($row = $chart_result->fetch_assoc()) {
            $label = $row['diseaseName'];
            $chart_labels[] = (strlen($label) > 35) ? substr($label, 0, 32) . '...' : $label;
            $chart_data[] = (int)$row['gene_count'];
        }
    }
} catch (Exception $e) {
    error_log("Error in disease.php chart query: " . $e->getMessage());
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

// Capture symbol passed via query string (e.g. disease.php?symbol=A1BG)
// so the table can be pre-filtered when arriving from another page's
// "disease" icon/link. Empty string if not present.
$prefill_symbol = isset($_GET['symbol']) ? trim($_GET['symbol']) : '';
$prefill_symbol_json = json_encode($prefill_symbol);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gene Disease Analysis</title>
    
    <!-- Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    .chart-container { position: relative; height: 400px; width: 100%; }
    .network-container { position: relative; height: 600px; width: 100%; border: 1px solid #dee2e6; border-radius: 5px; background-color: #fdfdfd; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .main-content { margin-bottom: 40px; }
    .loading-spinner { display: none; text-align: center; padding: 20px; }
    .spinner-border { width: 3rem; height: 3rem; }
    .network-controls { background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .network-stats { font-size: 0.9em; color: #6c757d; margin-top: 10px; }
    .btn-network { margin-right: 10px; margin-bottom: 5px; }
    .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
    </style>
</head>
<body>

<div class="container pb-4 pt-0"> 
    <div class="main-content">
        <!-- Header with Title on Left and Video Button on Right -->
        <div class="row align-items-center mb-5">
            <div class="col-md-8 col-lg-9 text-start">
                <h1 style="
                    font-family: 'Segoe UI', sans-serif;
                    font-size: 40px;
                    font-weight: 700;
                    color: #212529;
                    margin-top: 0;
                    padding-top: 0;
                    margin-bottom: 5px;">
                    Gene-Disease Associations
                </h1>

    <p style="
        font-family: 'Segoe UI', sans-serif;
        font-size: 20px;
        font-weight: 400;
        color: #212529;
        line-height: 1.5;
        margin-bottom: 0;">
        Browse curated associations between circadian-associated genes and diseases integrated with MONDO ontology identifiers.
    </p>
</div>
            <div class="col-md-4 col-lg-3 text-md-end text-start mt-3 mt-md-0">
                <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Gene-Disease_Associations.mp4" target="_blank" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold" style="border-width: 2px;">
                    <i class="fas fa-video me-2"></i> Watch Video Tutorial
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-light">
                <h3 class="mb-0">Graphical Summary: Top 10 Diseases by Gene Count</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($chart_data)): ?>
                    <div class="chart-container">
                        <canvas id="diseaseChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> No chart data available.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Dynamic Network: Gene-Disease Associations</h3>
                <small class="text-muted">Updates based on filtered table data</small>
            </div>
            <div class="card-body">
                <div class="network-controls">
                     <div class="row align-items-center">
                        <div class="col-md-8">
                            <button id="loadNetworkBtn" class="btn btn-primary btn-network"><i class="fas fa-project-diagram"></i> Load Network Visualization</button>
                            <button id="refreshNetworkBtn" class="btn btn-secondary btn-network" style="display:none;"><i class="fas fa-sync"></i> Refresh Network</button>
                            <button id="clearNetworkBtn" class="btn btn-outline-danger btn-network" style="display:none;"><i class="fas fa-times"></i> Clear Network</button>
                        </div>
                        <div class="col-md-4 text-md-end"><div class="network-stats" id="networkStats">Network not loaded</div></div>
                    </div>
                </div>
                <div class="loading-spinner" id="networkLoading">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2">Building network visualization...</p>
                </div>
                <div id="networkContainer" class="network-container" style="display: none;">
                    <div class="alert alert-info text-center m-3" id="networkPlaceholder"><i class="fas fa-project-diagram fa-2x mb-2"></i><p class="mb-0">Click "Load Network Visualization" to see the gene-disease relationships</p></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h3 class="mb-0">Detailed Disease Data Table</h3>
            </div>
            <div class="card-body">
                <div class="filter-section">
                    <div class="row align-items-end">
                        <div class="col-md-4 mb-3">
                            <label for="symbolFilter" class="form-label fw-bold">Filter by Symbol:</label>
                            <select id="symbolFilter" class="form-select" style="width: 100%;">
                                <option value="">All Symbols</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="diseaseFilter" class="form-label fw-bold">Filter by Disease:</label>
                            <select id="diseaseFilter" class="form-select" style="width: 100%;">
                                <option value="">All Diseases</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 text-md-end">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <a href="download_disease.php" class="btn btn-success w-100 w-md-auto"><i class="fas fa-download"></i> Download as TSV</a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="geneDiseaseTable" class="table table-striped table-bordered" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Sr. No.</th>
                                <th>HGNC ID</th>
                                <th>Approved Symbol</th>
                                <th>Disease Name</th>
                                <th>MONDO</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $.fn.dataTable.ext.errMode = 'none';

    const chart_labels = <?= $chart_labels_json ?: '[]'; ?>;
    const chart_data = <?= $chart_data_json ?: '[]'; ?>;
    const prefill_symbol = <?= $prefill_symbol_json ?: '""'; ?>; // symbol from ?symbol= query string, if any

    var networkChart = null;
    var networkLoaded = false;
    var currentFilters = { symbol: '', disease: '', source: '', search: '' };
    let diseaseTable = null;

    $(document).ready(function() {
        initializeChart();
        initializeSelect2();
        prefillSymbolFilter();   // set the dropdown's value BEFORE the table is created
        initializeDataTable();   // table's first ajax call now already includes the symbol
        initializeNetworkControls();
    });

    function initializeChart() {
        const ctx = document.getElementById('diseaseChart');
        if (ctx && chart_labels.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chart_labels,
                    datasets: [{
                        label: 'Associated Gene Count',
                        data: chart_data,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: { display: true, text: 'Gene Count' }
                        }
                    }
                }
            });
        }
    }

    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('#geneDiseaseTable')) {
            $('#geneDiseaseTable').DataTable().destroy();
        }

        diseaseTable = $('#geneDiseaseTable').DataTable({
            destroy: true,
            processing: true,
            serverSide: true,
            ajax: {
                url: "get_disease_data_serverside.php",
                type: "POST",
                data: function(d) {
                    d.symbol = $('#symbolFilter').val();
                    d.disease = $('#diseaseFilter').val();
                    d.source = ''; // Set to empty as source filter was removed
                    currentFilters = { symbol: d.symbol, disease: d.disease, source: '', search: d.search.value };
                }
            },
            columns: [
                { data: 0, orderable: false },
                { 
                    data: 1,
                    render: function(data, type, row) {
                        if (!data) return '';
                        let cleanId = data.toString().trim();
                        if (!cleanId.toUpperCase().startsWith('HGNC:')) {
                            if (/^\d+$/.test(cleanId)) {
                                cleanId = 'HGNC:' + cleanId;
                            }
                        }
                        return `<a href="https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/${encodeURIComponent(cleanId)}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
                { 
                    data: 2,
                    render: function(data, type, row) {
                        if (!data) return '';
                        let cleanSymbol = data.toString().trim();
                        return `<a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?symbol=${encodeURIComponent(cleanSymbol)}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
                { data: 3 },
                { 
                    data: 4,
                    render: function(data, type, row) {
                        if (!data) return '';
                        let cleanMondo = data.toString().trim();
                        if (cleanMondo === '') return '';
                        return `<a href="https://monarchinitiative.org/${encodeURIComponent(cleanMondo)}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
                { data: 5 }
            ],
            pageLength: 25,
            order: [[2, 'asc']],
            drawCallback: function(settings) {
                if (networkLoaded) debounce(refreshNetworkFromTable, 1000)();
            }
        });

        $('#symbolFilter, #diseaseFilter').on('change', function() {
            diseaseTable.draw();
        });
    }

    function initializeSelect2() {
        // Auto-suggest implementation for Filter by Symbol (pointing to the inline PHP handler)
        $('#symbolFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search for an Approved Symbol',
            allowClear: true,
            ajax: {
                url: 'disease.php?action=search_symbols',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        $('#diseaseFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search for a disease',
            allowClear: true,
            ajax: {
                url: 'search_diseases.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });
    }

    // Pre-fill the Symbol filter when the page was opened with ?symbol=XXXX
    // in the URL (e.g. clicking the "disease" icon on gene.php).
    // Select2 requires the <option> to exist in the underlying <select>
    // before it can be marked selected, so we construct and append it here
    // rather than just calling .val(). This runs BEFORE initializeDataTable(),
    // so the table's very first ajax request already reads this value from
    // $('#symbolFilter').val() inside its data() callback - no extra
    // redraw/reload needed, and no dependency on any change-event handler.
    function prefillSymbolFilter() {
        if (prefill_symbol) {
            const opt = new Option(prefill_symbol, prefill_symbol, true, true);
            $('#symbolFilter').append(opt).trigger('change');
        }
    }

    function initializeNetworkControls() {
        $('#loadNetworkBtn').on('click', loadNetworkData);
        $('#refreshNetworkBtn').on('click', refreshNetworkFromTable);
        $('#clearNetworkBtn').on('click', clearNetwork);
    }

    function loadNetworkData() {
        $('#networkLoading').show();
        $.post('get_disease_network_data.php', { ...currentFilters, limit: 200 })
        .done(function(data) {
            if (data.success) {
                if (data.nodes.length === 0) {
                    showNetworkMessage('No network data available for the current filters.');
                } else {
                    renderNetwork(data);
                    updateNetworkStats(data.stats);
                    networkLoaded = true;
                    $('#loadNetworkBtn').hide();
                    $('#refreshNetworkBtn, #clearNetworkBtn').show();
                }
            } else {
                showNetworkError(data.error || 'Failed to load network data.');
            }
        }).fail(() => showNetworkError('The network request failed. Please try again.'))
          .always(() => $('#networkLoading').hide());
    }

    function refreshNetworkFromTable() {
        if (networkLoaded) loadNetworkData();
    }

    function renderNetwork(data) {
        try {
            const container = document.getElementById('networkContainer');
            $('#networkPlaceholder').hide();
            $('#networkContainer').show();
            const options = {
                groups: {
                    gene: {
                        color: { background: '#97C2FC', border: '#2B7CE9' },
                        font: { color: '#343a40' }
                    },
                    disease: {
                        color: { background: '#FFB347', border: '#FF8C00' },
                        font: { color: '#343a40' }
                    }
                },
                edges: { width: 1, color: { color: '#cccccc' } },
                physics: { stabilization: { iterations: 200 } }
            };
            networkChart = new vis.Network(container, { nodes: new vis.DataSet(data.nodes), edges: new vis.DataSet(data.edges) }, options);
        } catch (e) {
            showNetworkError('Failed to render the network visualization.');
        }
    }

    function clearNetwork() {
        if (networkChart) networkChart.destroy();
        networkChart = null;
        $('#networkContainer').hide();
        $('#networkPlaceholder').show();
        $('#loadNetworkBtn').show();
        $('#refreshNetworkBtn, #clearNetworkBtn').hide();
        networkLoaded = false;
        updateNetworkStats();
    }

    function showNetworkMessage(message) {
        $('#networkContainer').show();
        $('#networkPlaceholder').html(`<i class="fas fa-info-circle fa-2x mb-2 text-info"></i><p class="mb-0">${message}</p>`).show();
    }

    function showNetworkError(error) {
        $('#networkContainer').show();
        $('#networkPlaceholder').html(`<i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i><p class="mb-0 text-danger">Error: ${error}</p>`).show();
    }

    function updateNetworkStats(stats) {
        const statsText = stats ? `${stats.total_nodes} nodes, ${stats.total_edges} connections` : 'Network not loaded';
        $('#networkStats').text(statsText);
    }

    function debounce(func, wait) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
</script>
</body>
</html>
<?php
$conn->close();
include 'footer.php';
?>