<?php
require_once 'conn.php';
include 'header.php';

// Fetch all valid distinct community names (all ~555) on page load
$all_communities = [];
try {
    $preset_sql = "SELECT DISTINCT CD_CommunityName 
                   FROM ppi_module_new 
                   WHERE CD_CommunityName IS NOT NULL 
                     AND CD_CommunityName != '' 
                     AND LOWER(TRIM(CD_CommunityName)) NOT IN ('(none)', 'na', 'n/a', 'none')
                   ORDER BY CD_CommunityName ASC";
    $preset_result = $conn->query($preset_sql);
    if ($preset_result && $preset_result->num_rows > 0) {
        while ($row = $preset_result->fetch_assoc()) {
            $all_communities[] = $row['CD_CommunityName'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching communities: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Protein-Protein Interaction Network</title>
    
    <!-- Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
    .network-container { position: relative; height: 600px; width: 100%; border: 1px solid #dee2e6; border-radius: 5px; background-color: #fdfdfd; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .main-content { margin-bottom: 40px; }
    .loading-spinner { display: none; text-align: center; padding: 20px; }
    .spinner-border { width: 3rem; height: 3rem; }
    .network-controls { background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .network-stats { font-size: 0.9em; color: #6c757d; margin-top: 10px; }
    .btn-network { margin-right: 10px; margin-bottom: 5px; }
    .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
    
    .pastel-header {
        background: #ffffff !important;
        color: #2c3e50 !important;
        font-weight: 600;
        border-bottom: 1px solid #dee2e6;
    }

    .pastel-card {
        background: linear-gradient(180deg, #f3f7fa 0%, #fffdfa 100%) !important;
        border: 1px solid #d2e1ed !important;
    }

    .table.pastel-table thead th {
        background: #ffffff !important;
        color: #2c3e50 !important;
        vertical-align: middle;
        border-bottom: 2px solid #b8ccd9 !important;
        font-weight: 600;
    }

    /* Expand / Collapse toggle styling */
    .toggle-expand-btn {
        color: #0d6efd;
        cursor: pointer;
        text-decoration: underline;
        font-weight: 600;
        font-size: 0.85rem;
        margin-left: 5px;
        white-space: nowrap;
    }
    .toggle-expand-btn:hover {
        color: #0a58ca;
    }
    .member-cell-content {
        word-break: break-word;
        max-width: 320px;
    }
    </style>
</head>
<body>

<div class="container py-4"> 
    <div class="main-content">
        <!-- Header with Title on Left and Video Button on Right -->
        <div class="row align-items-center mb-1">
            <div class="col-md-8 col-lg-9 text-start">
                <h1 style="
                    font-family: 'Segoe UI', sans-serif;
                    font-size: 40px;
                    font-weight: 700;
                    color: #212529;
                    margin-bottom: 5px;">
                    Interaction &amp; Module
                </h1>

                <p style="
                    font-family: 'Segoe UI', sans-serif;
                    font-size: 20px;
                    font-weight: 400;
                    color: #212529;
                    line-height: 1.5;
                    margin-bottom: 0;">
                    Explore hierarchical protein communities derived from protein-protein interaction networks and compare functional modules across multiple organizational levels.
                </p>
            </div>

            <div class="col-md-4 col-lg-3 text-md-end text-start mt-3 mt-md-0">
                <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Interation_module.mp4" target="_blank" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-video me-2"></i> Watch Video Tutorial
                </a>
            </div>
        </div>

        <!-- Overview Diagram -->
        <div class="row mb-5">
            <div class="col-12 text-center">
                <img src="https://datascience.imtech.res.in/anshu/circanet/GA/GA5.png" alt="Interaction & Module Overview Diagram" class="img-fluid w-100" style="max-width: 100%; height: auto;" />
            </div>
        </div>

        <!-- Interactive Protein Network Card -->
        <div class="card shadow-sm mb-5">
            <div class="card-header pastel-header">
                <h3 class="mb-0 h5">Interactive Protein Network</h3>
            </div>
            <div class="card-body">
                <div class="network-controls">
                     <div class="row align-items-end">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <label for="communityNetworkFilter" class="form-label fw-bold small">Compare Community Names (Select up to 2):</label>
                            <select id="communityNetworkFilter" class="form-select" style="width: 100%;" multiple="multiple">
                                <?php foreach ($all_communities as $comm): ?>
                                    <option value="<?= htmlspecialchars($comm); ?>" <?= ($comm === 'Metabolic Pathways' || $comm === 'Metabolism of RNA') ? 'selected="selected"' : ''; ?>>
                                        <?= htmlspecialchars($comm); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <button id="loadNetworkBtn" class="btn btn-primary btn-network w-100"><i class="fas fa-project-diagram"></i> Load Network</button>
                            <button id="refreshNetworkBtn" class="btn btn-secondary btn-network w-100" style="display:none;"><i class="fas fa-sync"></i> Refresh Network</button>
                        </div>
                        <div class="col-md-3 text-md-end mb-2 mb-md-0">
                            <button id="clearNetworkBtn" class="btn btn-outline-danger btn-network w-100" style="display:none;"><i class="fas fa-times"></i> Clear</button>
                            <div class="network-stats mt-1" id="networkStats">Network not loaded</div>
                        </div>
                     </div>
                </div>
                
                <div class="loading-spinner" id="networkLoading">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2">Building interaction network...</p>
                </div>

                <div id="networkPlaceholder" class="alert alert-info text-center m-3">
                    <i class="fas fa-project-diagram fa-2x mb-2"></i>
                    <p class="mb-0">Click "Load Network" to generate the network map</p>
                </div>
                
                <div class="position-relative">
                    <div id="networkContainer" class="network-container" style="display: none;"></div>
                    
                    <div id="networkLegend" class="position-absolute bg-white p-3 border rounded shadow-sm" style="bottom: 15px; right: 15px; z-index: 10; display: none; min-width: 200px; opacity: 0.95;">
                        <h6 class="mb-2 fw-bold text-dark small"><i class="fas fa-info-circle text-muted"></i> Network Legend</h6>
                        
                        <div class="d-flex align-items-center mb-2 small text-muted">
                            <span class="d-inline-block me-2" style="width: 14px; height: 14px; background: linear-gradient(135deg, #ffc107, #28a745); clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"></span>
                            <span><strong>Community (Hexagon)</strong></span>
                        </div>
                        
                        <div class="d-flex align-items-center small text-muted">
                            <span class="d-inline-block rounded-circle bg-info me-2" style="width: 12px; height: 12px; border: 1px solid #117a8b;"></span>
                            <span><strong>Gene Node (Dot)</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Community Detection Dataset (ppi_module_new) -->
        <div class="card shadow-sm pastel-card mb-5">
            <div class="card-header pastel-header">
                <h3 class="mb-0 h5">Community Detection Dataset</h3>
            </div>
            <div class="card-body">
                <!-- Filters and Download Button Row -->
                <div class="row mb-3 align-items-end">
                    <div class="col-md-5 col-lg-4 mb-2 mb-md-0">
                        <label for="cdCommunityFilter" class="form-label fw-bold small">Filter Table by Community Name:</label>
                        <select id="cdCommunityFilter" class="form-select" style="width: 100%;">
                            <option value="">All Communities</option>
                            <?php foreach ($all_communities as $comm): ?>
                                <option value="<?= htmlspecialchars($comm); ?>"><?= htmlspecialchars($comm); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <button id="downloadCdTsvBtn" class="btn btn-success w-100"><i class="fas fa-download"></i> Download as TSV</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="ppiCdTable" class="table table-striped table-bordered pastel-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Community Name</th>
                                <th>Annotated Members Size</th>
                                <th>Annotated Members</th>
                                <th>Member List</th>
                                <th>Annotated Members Overlap</th>
                                <th>Member List Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detailed Interaction Dataset (Prot_string_with_HGNC) -->
        <div class="card shadow-sm mb-5">
            <div class="card-header pastel-header">
                <h3 class="mb-0 h5">Detailed Interaction Dataset</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="ppiTable" class="table table-striped table-bordered pastel-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>String ID A</th>
                                <th>String ID B</th>
                                <th>Protein A (HGNC)</th>
                                <th>Protein B (HGNC)</th>
                                <th>UniprotID_A</th>
                                <th>UniprotID_B</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $.fn.dataTable.ext.errMode = 'none';

    var networkChart = null;
    var networkLoaded = false;
    var currentFilters = { protein: '', min_score: '', search: '' };
    let ppiTable = null;
    let ppiCdTable = null;

    // Helper function for Expand / Collapse (More / Less) in table cells
    function renderExpandableCell(data, cutoff = 80) {
        if (!data || data.trim() === '' || data === '-') return '-';
        const str = data.toString().trim();
        if (str.length <= cutoff) {
            return `<div class="member-cell-content">${str}</div>`;
        }
        const shortText = str.substring(0, cutoff);
        return `<div class="member-cell-content">
            <span class="text-short">${shortText}...</span>
            <span class="text-full" style="display:none;">${str}</span>
            <a href="javascript:void(0);" class="toggle-expand-btn" onclick="toggleCellText(this)">more</a>
        </div>`;
    }

    function toggleCellText(btn) {
        const $btn = $(btn);
        const $short = $btn.siblings('.text-short');
        const $full = $btn.siblings('.text-full');

        if ($full.is(':visible')) {
            $full.hide();
            $short.show();
            $btn.text('more');
        } else {
            $short.hide();
            $full.show();
            $btn.text('less');
        }
    }

    $(document).ready(function() {
        initializeDataTables();
        initializeNetworkControls();
        initializeSelect2();
        setupDownloadButton();
        
        loadNetworkData();
    });

    function initializeDataTables() {
        // Detailed Interaction Dataset
        ppiTable = $('#ppiTable').DataTable({
            destroy: true,
            processing: true,
            serverSide: true,
            ajax: {
                url: "get_ppi_data_serverside.php",
                type: "POST",
                data: function(d) {
                    d.protein = $('#proteinFilter').val();
                    d.min_score = '';
                    currentFilters = { 
                        protein: d.protein, 
                        min_score: d.min_score, 
                        search: d.search.value 
                    };
                }
            },
            columns: [
                { data: 0, orderable: false }, 
                { 
                    data: 1,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://string-db.org/network/' + encodeURIComponent(data.trim()) + '" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { 
                    data: 2,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://string-db.org/network/' + encodeURIComponent(data.trim()) + '" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { 
                    data: 3,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=' + encodeURIComponent(data.trim()) + '" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { 
                    data: 4,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=' + encodeURIComponent(data.trim()) + '" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { 
                    data: 5,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://www.uniprot.org/uniprotkb/' + encodeURIComponent(data.trim()) + '/entry" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { 
                    data: 6,
                    render: function(data, type, row) {
                        if (data && data.trim() !== '' && data !== '-') {
                            return '<a href="https://www.uniprot.org/uniprotkb/' + encodeURIComponent(data.trim()) + '/entry" target="_blank" rel="noopener noreferrer">' + data + '</a>';
                        }
                        return data || '-';
                    }
                },                   
                { data: 7 }                    
            ],
            pageLength: 25,
            order: [[7, 'desc']]
        });

        // Community Detection Dataset
        ppiCdTable = $('#ppiCdTable').DataTable({
            destroy: true,
            processing: true,
            serverSide: true,
            ajax: {
                url: "get_ppi_cd_data_serverside.php",
                type: "POST",
                data: function(d) {
                    d.protein = $('#proteinFilter').val(); 
                    d.community = $('#cdCommunityFilter').val(); 
                }
            },
            columns: [
                { data: 0, orderable: false }, 
                { data: 1 },                   
                { data: 2 },                   
                { 
                    data: 3,
                    render: function(data, type, row) {
                        return renderExpandableCell(data, 80);
                    }
                },                   
                { 
                    data: 4,
                    render: function(data, type, row) {
                        return renderExpandableCell(data, 80);
                    }
                },                   
                { data: 5 },                   
                { data: 6 }                    
            ],
            pageLength: 10,
            order: [[1, 'asc']]
        });

        $('#proteinFilter').on('change', function() {
            ppiTable.draw();
            ppiCdTable.draw();
        });

        $('#cdCommunityFilter').on('change', function() {
            ppiCdTable.draw();
        });
    }

    function initializeSelect2() {
        // Multi-select community comparison filter initialized across all 555 options
        $('#communityNetworkFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'Users can select up to two communities from the table below.',
            allowClear: true,
            maximumSelectionLength: 2
        });

        // Single community table filter
        $('#cdCommunityFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search or select a community...',
            allowClear: true
        });
    }

    function initializeNetworkControls() {
        $('#loadNetworkBtn').on('click', loadNetworkData);
        $('#refreshNetworkBtn').on('click', refreshNetworkFromTable);
        $('#clearNetworkBtn').on('click', clearNetwork);
    }

    function loadNetworkData() {
        $('#networkPlaceholder').hide();
        $('#networkContainer').hide();
        $('#networkLoading').show();

        const selectedCommunity = $('#communityNetworkFilter').val();

        $.post('get_ppi_network_data.php', { 
            ...currentFilters, 
            community: selectedCommunity, 
            limit: 100 
        })
        .done(function(data) {
            if (data.success) {
                if (data.nodes.length === 0) {
                    showNetworkMessage('No matching interactions found in the database to build a network.');
                } else {
                    renderNetwork(data);
                    updateNetworkStats(data.stats);
                    networkLoaded = true;
                    $('#networkLegend').show();
                    $('#loadNetworkBtn').hide();
                    $('#refreshNetworkBtn, #clearNetworkBtn').show();
                }
            } else {
                showNetworkError(data.error || 'Failed to load network data.');
            }
        }).fail(() => showNetworkError('Network request failed. Please check backend connection.'))
          .always(() => $('#networkLoading').hide());
    }

    function refreshNetworkFromTable() {
        if (networkLoaded) loadNetworkData();
    }

    function renderNetwork(data) {
        try {
            $('#networkPlaceholder').hide();
            $('#networkContainer').show();
            
            const container = document.getElementById('networkContainer');
            const options = {
                nodes: {
                    borderWidth: 2,
                    size: 18,
                    font: { size: 12, color: '#333333' }
                },
                edges: {
                    width: 1,
                    color: { color: '#dddddd', highlight: '#999999' },
                    smooth: { type: 'continuous' }
                },
                interaction: {
                    hover: true,
                    dragNodes: true,
                    dragView: true,
                    zoomView: true
                },
                physics: { 
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -50,
                        centralGravity: 0.01,
                        springLength: 90,
                        springConstant: 0.08,
                        damping: 0.4
                    },
                    stabilization: { 
                        enabled: true,
                        iterations: 200, 
                        updateInterval: 25
                    }
                }
            };
            
            networkChart = new vis.Network(container, { 
                nodes: new vis.DataSet(data.nodes), 
                edges: new vis.DataSet(data.edges) 
            }, options);
            
            networkChart.on("stabilizationIterationsDone", function () {
                networkChart.setOptions({ physics: false });
            });
            
        } catch (e) {
            showNetworkError('Could not render the interactive network visualization.');
        }
    }

    function clearNetwork() {
        if (networkChart) {
            networkChart.destroy();
            networkChart = null;
        }
        $('#networkContainer').hide();
        $('#networkLegend').hide();
        $('#networkPlaceholder').html('<i class="fas fa-project-diagram fa-2x mb-2"></i><p class="mb-0">Click "Load Network" to generate the network map</p>').show();
        $('#loadNetworkBtn').show();
        $('#refreshNetworkBtn, #clearNetworkBtn').hide();
        networkLoaded = false;
        updateNetworkStats();
    }

    function showNetworkMessage(message) {
        $('#networkContainer').hide();
        $('#networkPlaceholder').html(`<i class="fas fa-info-circle fa-2x mb-2 text-info"></i><p class="mb-0">${message}</p>`).show();
    }

    function showNetworkError(error) {
        $('#networkContainer').hide();
        $('#networkPlaceholder').html(`<i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i><p class="mb-0 text-danger">Error: ${error}</p>`).show();
    }

    function updateNetworkStats(stats) {
        const statsText = stats ? `${stats.total_nodes} nodes, ${stats.total_edges} connections` : 'Network not loaded';
        $('#networkStats').text(statsText);
    }

    function setupDownloadButton() {
        $('#downloadCdTsvBtn').on('click', function(e) {
            e.preventDefault();
            const params = $.param({
                protein: $('#proteinFilter').val(),
                community: $('#cdCommunityFilter').val()
            });
            window.location.href = `download_ppi_cd.php?${params}`;
        });
    }
</script>
</body>
</html>
<?php
$conn->close();
include 'footer.php';
?>