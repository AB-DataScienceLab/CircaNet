<?php
require_once 'conn.php';
include 'header.php';

// Data for the Chart: Top 10 Pathways by Gene Count
$chart_labels = [];
$chart_data = [];

try {
    $chart_sql = "SELECT Pathway_Name, COUNT(DISTINCT GeneID) as gene_count 
                  FROM Pathway_tb 
                  WHERE Pathway_Name IS NOT NULL AND Pathway_Name != ''
                  GROUP BY Pathway_Name 
                  ORDER BY gene_count DESC, Pathway_Name ASC
                  LIMIT 10";
    
    $chart_result = $conn->query($chart_sql);
    
    if ($chart_result && $chart_result->num_rows > 0) {
        while($row = $chart_result->fetch_assoc()) {
            $label = $row['Pathway_Name'];
            $chart_labels[] = (strlen($label) > 35) ? substr($label, 0, 32) . '...' : $label;
            $chart_data[] = (int)$row['gene_count'];
        }
    }
} catch (Exception $e) {
    error_log("Error in pathway.php chart query: " . $e->getMessage());
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gene Pathway Analysis</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
    .chart-container { position: relative; height: 400px; width: 100%; }
    .network-container { position: relative; height: 600px; width: 100%; border: 1px solid #dee2e6; border-radius: 5px; background-color: #fdfdfd; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .loading-spinner { display: none; text-align: center; padding: 20px; }
    .spinner-border { width: 3rem; height: 3rem; }
    .network-controls { background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }

    /* VISUAL SEPARATION: SR. NO. vs GENE ID */
    /* Column 1: Sr. No. - Gray background and border separation */
    #pathwayDataTable td:nth-child(1), #pathwayDataTable th:nth-child(1) {
        width: 60px !important;
        background-color: #f2f2f2 !important;
        text-align: center;
        border-right: 3px solid #dee2e6 !important; /* Thick separation line */
        color: #666;
    }
    /* Column 2: Gene ID - Blue highlight and bold */
    #pathwayDataTable td:nth-child(2), #pathwayDataTable th:nth-child(2) {
        width: 120px !important;
        font-weight: 800 !important;
        color: #0d6efd !important;
        background-color: #f8fbff !important;
        text-align: center;
    }
    </style>
</head>
<body>

<div class="container py-4"> 
    <div class="main-content">
        <div class="text-center mb-5">
            <h1>Gene Pathway Analysis</h1>
            <p class="lead">Interactive dashboard for exploring Gene-Pathway relationships.</p>
        </div>

        <!-- Dynamic Chart -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0 h5"><i class="fas fa-chart-bar me-2"></i>Top 10 Pathways by Gene Count</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="pathwayChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Dynamic Network -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="mb-0 h5"><i class="fas fa-project-diagram me-2"></i>Pathway Interaction Network</h3>
                <small class="text-muted" id="networkStatusText">Updates based on table filters</small>
            </div>
            <div class="card-body">
                <div class="network-controls">
                     <div class="row align-items-center">
                        <div class="col-md-8">
                            <button id="loadNetworkBtn" class="btn btn-primary"><i class="fas fa-play"></i> Enable Dynamic Network</button>
                            <button id="clearNetworkBtn" class="btn btn-outline-danger" style="display:none;"><i class="fas fa-times"></i> Disable Network</button>
                        </div>
                        <div class="col-md-4 text-md-end"><div id="networkStats" class="text-muted small"></div></div>
                    </div>
                </div>
                <div class="loading-spinner" id="networkLoading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Re-calculating network nodes...</p>
                </div>
                <div id="networkContainer" class="network-container" style="display: none;"></div>
                <div id="networkPlaceholder" class="alert alert-info text-center m-3">
                    <p class="mb-0">The network visualization is currently disabled. Click "Enable Dynamic Network" to start.</p>
                </div>
            </div>
        </div>

        <!-- Table Explorer -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h3 class="mb-0 h5"><i class="fas fa-table me-2"></i>Pathway Data Explorer</h3>
            </div>
            <div class="card-body">
                <div class="filter-section">
                    <div class="row align-items-end">
                        <div class="col-lg-8 col-md-6 mb-3">
                            <label class="form-label fw-bold">Filter by Pathway Name:</label>
                            <select id="pathwayFilter" class="form-select" style="width: 100%;">
                                <option value="">Type to search a pathway...</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6 mb-3 text-lg-end">
                            <a href="download_pathway.php" id="downloadTsvBtn" class="btn btn-success w-100"><i class="fas fa-download"></i> Download as TSV</a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pathwayDataTable" class="table table-striped table-bordered" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Sr. No.</th>
                                <th>Gene ID</th>
                                <th>Identifier</th>
                                <th>Pathway Name</th>
                                <th>Evidence</th>
                                <th>Organism</th>
                                <th>Reactome URL</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $.fn.dataTable.ext.errMode = 'none';
    var mainTable;
    var networkChart = null;
    var networkEnabled = false;
    var networkTimer;

    $(document).ready(function() {
        // Init Chart
        new Chart(document.getElementById('pathwayChart'), {
            type: 'bar',
            data: {
                labels: <?= $chart_labels_json ?>,
                datasets: [{
                    label: 'Gene Count',
                    data: <?= $chart_data_json ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
        });

        // Init Table
        mainTable = $('#pathwayDataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "get_pathway_data_serverside.php",
                type: "POST",
                data: function(d) { d.pathway = $('#pathwayFilter').val(); }
            },
            columns: [
                { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 }, { data: 5 },
                { data: 6, orderable: false, render: function(data) {
                    return data === "NA" ? "NA" : `<a href="${data}" target="_blank" class="btn btn-sm btn-outline-primary">Open Reactome</a>`;
                }}
            ],
            pageLength: 25,
            drawCallback: function() {
                updateDownloadLink();
                // DYNAMIC NETWORK UPDATE: 
                // If network is enabled, refresh it whenever table filter/search changes
                if (networkEnabled) {
                    clearTimeout(networkTimer);
                    networkTimer = setTimeout(loadNetwork, 800); // Debounce to prevent server lag while typing
                }
            }
        });

        // Select2 Fix
        $('#pathwayFilter').select2({
            theme: 'bootstrap-5',
            ajax: { 
                url: 'search_pathways.php', 
                dataType: 'json', 
                delay: 250,
                data: function (params) { return { term: params.term }; },
                processResults: function (data) { return { results: data }; }
            },
            allowClear: true,
            placeholder: 'Search Pathway...'
        }).on('change', () => mainTable.draw());

        $('#loadNetworkBtn').on('click', function() {
            networkEnabled = true;
            $(this).hide();
            $('#clearNetworkBtn').show();
            $('#networkPlaceholder').hide();
            $('#networkContainer').show();
            loadNetwork();
        });

        $('#clearNetworkBtn').on('click', function() {
            networkEnabled = false;
            if (networkChart) networkChart.destroy();
            $(this).hide();
            $('#loadNetworkBtn').show();
            $('#networkPlaceholder').show();
            $('#networkContainer').hide();
            $('#networkStats').text('');
        });
    });

    function updateDownloadLink() {
        let p = $('#pathwayFilter').val() || '';
        let s = mainTable.search() || '';
        $('#downloadTsvBtn').attr('href', `download_pathway.php?pathway=${encodeURIComponent(p)}&search=${encodeURIComponent(s)}`);
    }

    function loadNetwork() {
        if (!networkEnabled) return;
        
        $('#networkLoading').show();
        $.post('get_pathway_network_data.php', { 
            pathway: $('#pathwayFilter').val(), 
            search: mainTable.search() 
        })
        .done(function(data) {
            if (data.success && data.nodes.length > 0) {
                const container = document.getElementById('networkContainer');
                networkChart = new vis.Network(container, { 
                    nodes: new vis.DataSet(data.nodes), 
                    edges: new vis.DataSet(data.edges) 
                }, {
                    groups: { 
                        gene: { color: { background: '#D2E5FF', border: '#2B7CE9' }, font: { color: '#000' } }, 
                        pathway: { color: { background: '#FFA500', border: '#FF8C00' }, shape: 'diamond' } 
                    },
                    physics: { stabilization: true, barnesHut: { gravitationalConstant: -2000 } }
                });
                $('#networkStats').text(`${data.stats.total_nodes} nodes connected`);
            } else {
                $('#networkStats').text('No connections found for current filters.');
            }
        }).always(() => $('#networkLoading').hide());
    }
</script>
<?php include 'footer.php';?>
</body>
</html>