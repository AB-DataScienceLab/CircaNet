<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CircaNet - TFBS Analysis</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/all.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.30.0/cytoscape.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
body{
    background:#eef2f6;
}

/* ============================================================
   HERO & SEARCH COMPONENT IMPLEMENTATION (VIBRANT BLUE GRADIENT)
   ============================================================ */
.hero-section {
    background: linear-gradient(135deg, #10428d 0%, #1e40af 100%);
    border-radius: 12px; padding: 30px; color: white;
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-top: 20px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    flex-wrap: wrap; gap: 20px;
}
.hero-text { flex: 1; min-width: 300px; padding-right: 20px; }
.hero-text h1 { 
    margin: 0; font-size: 1.85rem; font-weight: 700; color: white; line-height: 1.25; 
    font-family: 'Poppins', sans-serif;
}
.hero-text .page-subtitle-badge {
    display: inline-block;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.35);
    padding: 5px 16px;
    border-radius: 20px;
    color: #ffffff;
}
.hero-text p { 
    margin: 0; font-size: 1rem; color: rgba(255, 255, 255, 0.85); 
    font-family: 'Poppins', sans-serif;
}

/* Search Wrapper Aligned Right */
.search-wrapper-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}
.search-container { 
    position: relative; margin-top: 0; display: flex; 
}
.search-input {
    padding: 12px 20px 12px 45px; border-radius: 50px; border: none;
    width: 280px; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: width 0.3s ease;
    background-color: white !important;
    color: #21201c !important;
    height: 42px;
}
.search-input:focus { outline: none; width: 330px; }
.search-icon { 
    position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; 
}
.search-btn {
    background: #10b981; color: white; border: none; padding: 12px 25px;
    border-radius: 50px; margin-left: 10px; cursor: pointer; font-weight: 600;
    transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    height: 42px;
}
.search-btn:hover { background: #059669; }

/* Watch Video Tutorial Hero Pill Button Style */
.video-tutorial-hero-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 8px 24px !important;
    border-radius: 50px !important;
    background-color: #ffffff !important;
    border: 2px solid #0d6efd !important;
    color: #0d6efd !important;
    font-weight: 700 !important;
    font-size: 0.92rem !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.video-tutorial-hero-btn:hover {
    background-color: #f0f7ff !important;
    border-color: #0d6efd !important;
    color: #0a58ca !important;
    transform: translateY(-1px);
}
.video-tutorial-hero-btn i {
    font-size: 1.05rem;
    color: #0d6efd !important;
}

/* ============================================================
   AUTOSUGGEST DROPDOWN
   ============================================================ */
.autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    z-index: 1050;
    margin-top: 4px;
    display: none;
}
.search-container .autocomplete-suggestions {
    width: 280px;
}
.autocomplete-suggestions .suggestion-item {
    display: block;
    width: 100%;
    padding: 10px 15px;
    border: none;
    background: white;
    text-align: left;
    font-size: 13.5px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
    color: #21201c;
}
.autocomplete-suggestions .suggestion-item:last-child {
    border-bottom: none;
}
.autocomplete-suggestions .suggestion-item:hover {
    background-color: #eff6ff;
    color: #1e40af;
}

.card-custom{
    background:white;
    border-radius:12px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.metric{
    font-size:2rem;
    font-weight:bold;
    color:#10428d;
}

.metric-label{
    color:#777;
}

#network{
    height:700px;
    border:1px solid #ddd;
    border-radius:10px;
}

.logo-box img{
    max-width:100%;
}

.section-title{
    color:#10428d;
    font-weight:700;
    margin-bottom:0;
}

.tf-row {
    cursor: pointer;
}
.tf-row:hover {
    background-color: #f8f9fa !important;
}
</style>

</head>
<body>

<div class="container" id="main-container">

<!-- System Alert Container for Database/Server Warnings -->
<div id="system-alert-box"></div>

<!-- Header Section with Integrated Search & Autocomplete (Vibrant Blue Hero Style) -->
<div class="hero-section">
    <div class="hero-text">
        <h1 class="mb-3">Transcription factors (TF) & Transcription factor binding sites (TFBSs)</h1>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="page-subtitle-badge" id="gene-badge">Loading...</span>
            <span id="gene-id" class="badge bg-light text-dark fw-semibold px-2 py-1">Gene ID: --</span>
            <span id="gene-desc" class="text-white-50 small">Gene Type: --</span>
        </div>
    </div>
    
    <div class="search-wrapper-right">
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="geneSearchInput" class="search-input" placeholder="Search Symbol (e.g. CTCF) or NCBI ID" autocomplete="off">
            <button class="search-btn" id="btnSearch" type="button">Search</button>
            <!-- Dynamic Autocomplete Dropdown List -->
            <div id="searchSuggestions" class="autocomplete-suggestions"></div>
        </div>
        
        <!-- Watch Video Tutorial Pill Button -->
        <div class="mt-2">
            <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/TF_and_TFBS.mp4" target="_blank" class="video-tutorial-hero-btn">
                <i class="fas fa-video me-1"></i> Watch Video Tutorial
            </a>
        </div>
    </div>
</div>

<!-- KPI Metric Cards: Single View -->
<div class="row" id="single-kpi-row">
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-tfs"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            <div class="metric-label">Total TFs</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-tfbs"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            <div class="metric-label">TFBS</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-strong"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            <div class="metric-label">Strong Sites</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-hubs"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            <div class="metric-label">Hub TFs</div>
        </div>
    </div>
</div>

<!-- Interactive Network Area -->
<div class="card-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title" id="network-title">Regulatory Network</h3>
        <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm rounded-pill" id="btnResetNetwork" title="Reset view and rearrange layout">
            <i class="fas fa-rotate-right me-1"></i> Reset View
        </button>
    </div>
    <div id="network"></div>
</div>

<!-- Top Regulatory TFs DataTable Container -->
<div class="card-custom">
    <h3 class="section-title mb-3" id="table-title">Target Transcription Factors</h3>
    <table class="table table-striped" id="tfbsTable" style="width:100%">
        <!-- Table headers generated dynamically via Javascript -->
    </table>
</div>

<!-- Interactive Inspector Panel -->
<div class="row">
    <div class="col-md-6">
        <div class="card-custom">
            <h3 class="section-title mb-3">Selected TF Details</h3>
            <table class="table mb-0">
                <tr>
                    <td>Name</td>
                    <td id="ins-name" class="fw-bold text-primary">--</td>
                </tr>
                <tr>
                    <td>Motif ID</td>
                    <td id="ins-motif">--</td>
                </tr>
                <tr>
                    <td>Binding Sites</td>
                    <td id="ins-sites">--</td>
                </tr>
                <tr>
                    <td>Global Targets</td>
                    <td id="ins-global">--</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td id="ins-status">--</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Sequence Logo Viewer -->
    <div class="col-md-6">
        <div class="card-custom logo-box">
            <h3 class="section-title mb-3">Sequence Logo</h3>
            <div class="text-center p-2 border rounded bg-white position-relative d-flex align-items-center justify-content-center" style="min-height: 156px;">
                <div id="logo-spinner" class="spinner-border text-primary position-absolute" role="status" style="display: none;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <img id="ins-logo" src="" alt="Select a TF to load logo" style="max-height: 140px; display: none; transition: opacity 0.15s ease;">
            </div>
        </div>
    </div>
</div>

<!-- Compare Genes Panel (Statically placed at the bottom for single gene view) -->
<div class="card-custom" id="compare-genes-card">
    <h3 class="section-title mb-3">Compare Genes</h3>
    <div class="row">
        <div class="col-md-5">
            <div class="position-relative">
                <input type="text" id="compare-gene1" class="form-control" placeholder="PER1" autocomplete="off">
                <div id="compareGene1Suggestions" class="autocomplete-suggestions"></div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="position-relative">
                <input type="text" id="compare-gene2" class="form-control" placeholder="CLOCK" autocomplete="off">
                <div id="compareGene2Suggestions" class="autocomplete-suggestions"></div>
            </div>
        </div>
        <div class="col-md-2">
            <button class="btn btn-success w-100" id="btnCompare">Compare</button>
        </div>
    </div>
</div>

</div>

<script>
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTarget = urlParams.get('search');
    const currentScript = window.location.pathname.split('/').pop() || 'tf_new.php';

    let activeTFData = {}; 
    let dt = null;
    let loadedGeneID = null;
    let cyInstance = null; // Cytoscape instance reference for reset actions

    function renderErrorAlert(message) {
        $('#system-alert-box').html(`
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>System Exception:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }

    // --- STANDARD SINGLE GENE RUNTIME ---
    const activeSearch = searchTarget || 'CLOCK';
    buildSingleTableHeaders();
    dt = $('#tfbsTable').DataTable({
        pageLength: 5,
        lengthMenu: [5, 10, 20],
        order: [[2, 'desc']], 
        dom: 'ftp',
        columns: [
            { data: 'motif_alt_id', className: 'text-primary fw-bold tf-row' },
            { data: 'matrix_id', render: d => `<code>${d}</code>` },
            { data: 'sites_on_target', render: d => `<strong>${d}</strong>` }
        ]
    });

    $.getJSON('tf_backend.php?action=get_gene_details&search=' + encodeURIComponent(activeSearch), function(response) {
        if (!response || !response.success || !response.gene_info) {
            const errorStr = (response && response.error) ? response.error : 'No matching record was retrieved from the database.';
            renderErrorAlert(errorStr);
            $('#gene-badge').text('Query Not Found');
            $('#gene-id').text('N/A');
            $('#gene-desc').text(errorStr);
            resetMetricsOnFailure();
            return;
        }

        const info = response.gene_info;
        loadedGeneID = info.NCBI_gene_ID;

        $('#gene-badge').text(info.Gene_Symbol);
        $('#gene-id').text('Gene ID: ' + info.NCBI_gene_ID);
        $('#gene-desc').text('Gene Type: ' + info.Gene_Type);

        $('#metric-tfs').text(response.metrics.total_tfs);
        $('#metric-tfbs').text(response.metrics.total_tfbs);
        $('#metric-strong').text(response.metrics.strong_sites);
        $('#metric-hubs').text(response.metrics.hub_tfs_count);

        dt.clear().rows.add(response.tf_list).draw();

        const elements = [];
        elements.push({
            data: { id: info.NCBI_gene_ID, label: info.Gene_Symbol, size: 70 },
            classes: 'gene'
        });

        response.tf_list.forEach(function(tf) {
            const logoUrl = "https://jaspar.elixir.no/static/logos/all/svg/" + tf.matrix_id + ".svg";

            activeTFData[tf.motif_alt_id] = {
                name: tf.motif_alt_id,
                motif_id: tf.matrix_id,
                sites: tf.sites_on_target,
                global_targets: tf.global_targets,
                logo_url: logoUrl
            };

            elements.push({
                data: { 
                    id: tf.motif_alt_id, 
                    label: tf.motif_alt_id, 
                    size: Math.min(110, 50 + (tf.sites_on_target * 4)) 
                },
                classes: 'tf'
            });

            elements.push({
                data: { source: tf.motif_alt_id, target: info.NCBI_gene_ID, weight: tf.sites_on_target }
            });
        });

        if (elements.length > 1) {
            initSingleNetwork(elements);
        } else {
            $('#network').html('<div class="d-flex align-items-center justify-content-center h-100 text-muted">No interactions found to map.</div>');
        }

        if (response.tf_list.length > 0) {
            loadSingleInspectorTF(response.tf_list[0].motif_alt_id);
        }
    }).fail(function() {
        renderErrorAlert('Could not establish database link. Please check if tf_backend.php is correctly set.');
        $('#gene-badge').text('Server Error');
        $('#gene-desc').text('Could not communicate with the database.');
        resetMetricsOnFailure();
    });

    function resetMetricsOnFailure() {
        $('.metric').text('-');
    }

    // --- INTERACTIVE EVENT LISTENER HOOKS ---

    // Reset Network View Click Handler
    $('#btnResetNetwork').click(function() {
        if (cyInstance) {
            const layout = cyInstance.layout({
                name: 'concentric',
                fit: true,
                padding: 30,
                animate: true,
                animationDuration: 500,
                concentric: function(ele) { return ele.degree(); },
                levelWidth: function() { return 1; }
            });
            layout.run();
        }
    });

    // Main Header Autocomplete Input Handling
    $('#geneSearchInput').on('input', function() {
        const val = $(this).val().trim();
        const container = $('#searchSuggestions');
        if (val.length < 2) {
            container.hide();
            return;
        }

        $.getJSON('tf_backend.php?action=suggest_genes&q=' + encodeURIComponent(val), function(data) {
            container.empty();
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    container.append(`
                        <button type="button" class="suggestion-item search-suggest-item" data-id="${item.id}">
                            <strong>${item.symbol}</strong> <span class="text-muted small">(${item.id})</span>
                        </button>
                    `);
                });
                container.show();
            } else {
                container.hide();
            }
        });
    });

    $(document).on('click', '.search-suggest-item', function() {
        const targetId = $(this).attr('data-id');
        window.location.href = currentScript + '?search=' + encodeURIComponent(targetId);
    });

    // Compare Inputs Autocomplete Setup Helper
    function setupCompareAutocomplete(inputSelector, suggestionsSelector) {
        const input = $(inputSelector);
        const container = $(suggestionsSelector);

        input.on('input', function() {
            const val = $(this).val().trim();
            if (val.length < 2) {
                container.hide();
                return;
            }

            $.getJSON('tf_backend.php?action=suggest_genes&q=' + encodeURIComponent(val), function(data) {
                container.empty();
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        container.append(`
                            <button type="button" class="suggestion-item compare-suggest-item" data-symbol="${item.symbol}" data-id="${item.id}">
                                <strong>${item.symbol}</strong> <span class="text-muted small">(${item.id})</span>
                            </button>
                        `);
                    });
                    container.show();
                } else {
                    container.hide();
                }
            });
        });

        container.on('click', '.compare-suggest-item', function(e) {
            e.preventDefault();
            const symbol = $(this).attr('data-symbol');
            input.val(symbol);
            container.hide();
        });
    }

    setupCompareAutocomplete('#compare-gene1', '#compareGene1Suggestions');
    setupCompareAutocomplete('#compare-gene2', '#compareGene2Suggestions');

    // Global Outside Click to Dismiss Dropdowns
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#geneSearchInput, #searchSuggestions').length) {
            $('#searchSuggestions').hide();
        }
        if (!$(e.target).closest('#compare-gene1, #compareGene1Suggestions').length) {
            $('#compareGene1Suggestions').hide();
        }
        if (!$(e.target).closest('#compare-gene2, #compareGene2Suggestions').length) {
            $('#compareGene2Suggestions').hide();
        }
    });

    $('#btnSearch').click(function() {
        const query = $('#geneSearchInput').val().trim();
        if (query) {
            window.location.href = currentScript + '?search=' + encodeURIComponent(query);
        }
    });

    $('#geneSearchInput').keypress(function(e) {
        if (e.which === 13) {
            $('#btnSearch').click();
        }
    });

    // Comparison Action Button
    $('#btnCompare').click(function() {
        const g1 = $('#compare-gene1').val().trim() || 'PER1';
        const g2 = $('#compare-gene2').val().trim() || 'CLOCK';
        const compareUrl = `tf_compare.php?g1=${encodeURIComponent(g1)}&g2=${encodeURIComponent(g2)}`;
        window.open(compareUrl, '_blank');
    });

    // Sync Table selection event to update inspector details
    $('#tfbsTable tbody').on('click', 'tr', function () {
        const rowData = dt.row(this).data();
        if (rowData && rowData.motif_alt_id) {
            loadSingleInspectorTF(rowData.motif_alt_id);
        }
    });

    // --- RENDERING ROUTINES ---

    function buildSingleTableHeaders() {
        $('#tfbsTable').html(`
            <thead>
                <tr>
                    <th>TF</th>
                    <th>Motif ID</th>
                    <th>Sites on Target</th>
                </tr>
            </thead>
            <tbody></tbody>
        `);
    }

    function loadSingleInspectorTF(tfName) {
        const tf = activeTFData[tfName];
        if (!tf) return;

        $('#ins-name').text(tf.name);
        $('#ins-motif').text(tf.motif_id);
        $('#ins-sites').text(tf.sites);
        $('#ins-global').text(tf.global_targets);

        setInspectorLogo(tf.logo_url);

        const statusBadge = $('#ins-status');
        if (tf.global_targets > 5) {
            statusBadge.html('<span class="badge bg-danger">Hub TF</span>');
        } else {
            statusBadge.html('<span class="badge bg-secondary">Target-Specific</span>');
        }
    }

    function setInspectorLogo(url) {
        const logoImg = $('#ins-logo');
        const spinner = $('#logo-spinner');

        logoImg.hide();
        spinner.show();

        logoImg.off('load').on('load', function() {
            spinner.hide();
            logoImg.fadeIn(150);
        });

        logoImg.off('error').on('error', function() {
            spinner.hide();
            logoImg.attr('alt', 'No JASPAR logo available').show();
        });

        logoImg.attr('src', url);
    }

    // --- CYTOSCAPE MAPPING GENERATION ENGINES ---

    function initSingleNetwork(elements) {
        cyInstance = cytoscape({
            container: document.getElementById('network'),
            elements: elements,
            style: [
                {
                    selector: 'node.gene',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#10428d',
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'color': 'white',
                        'font-size': '14px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-weight': 'bold'
                    }
                },
                {
                    selector: 'node.tf',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#E89D6C',
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'color': '#333',
                        'font-size': '12px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-weight': 'bold',
                        'border-width': 1.5,
                        'border-color': '#b8754c'
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 'mapData(weight, 1, 20, 2, 10)',
                        'line-color': '#cbd5e1',
                        'target-arrow-color': '#94a3b8',
                        'target-arrow-shape': 'none',
                        'curve-style': 'bezier'
                    }
                }
            ],
            layout: {
                name: 'concentric',
                fit: true,
                padding: 30,
                concentric: function(ele) { return ele.degree(); },
                levelWidth: function() { return 1; }
            }
        });

        cyInstance.on('tap', 'node.tf', function(evt){
            loadSingleInspectorTF(evt.target.id());
        });
    }
});
</script>

</body>
</html>
<?php
include 'footer.php';
?>