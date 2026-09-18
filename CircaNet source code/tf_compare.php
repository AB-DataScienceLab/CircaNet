<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CircaNet - Comparative TFBS Analysis</title>

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
   HERO BANNER & VIDEO TUTORIAL BUTTON (CLEAN & CENTERED)
   ============================================================ */
.hero-section {
    background: linear-gradient(135deg, #10428d 0%, #1e40af 100%);
    border-radius: 12px; padding: 30px; color: white;
    display: flex; justify-content: space-between; align-items: center; /* Vertically centered alignment */
    margin-top: 20px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    flex-wrap: wrap; gap: 20px;
}
.hero-text { flex: 1; min-width: 300px; padding-right: 20px; }
.hero-text h1 { 
    margin: 0; font-size: 2.2rem; font-weight: 700; color: white; line-height: 1.2; 
    font-family: 'Poppins', sans-serif;
}
.hero-text p { 
    margin: 0; font-size: 1rem; color: rgba(255, 255, 255, 0.85); 
    font-family: 'Poppins', sans-serif;
}

/* Watch Video Tutorial Hero Pill Button Style */
.video-tutorial-hero-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 10px 26px !important;
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
    margin-bottom:15px;
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

<!-- Header Section (Dynamic comparison title on the left, Centered Tutorial button on the right) -->
<div class="hero-section">
    <div class="hero-text">
        <h1 id="gene-title" style="font-family: 'Poppins', sans-serif; font-weight: 700; color: white;">Loading...</h1>
        <p id="gene-id" class="mt-2 mb-1" style="font-size: 1rem; color: rgba(255, 255, 255, 0.85); font-weight: 500;">Comparative Genomic Analysis Mode</p>
        <p id="gene-desc" class="mb-0 text-white-50" style="font-size: 0.9rem;">Evaluating cross-regulation metrics between targets.</p>
    </div>
</div>

<!-- Compare Genes Panel (Moved below the hero-section comparison banner) -->
<div class="card-custom" id="compare-genes-card">
    <h3 class="section-title">Compare Genes</h3>
    <div class="row">
        <div class="col-md-5">
            <div class="position-relative">
                <input type="text" id="compare-gene1" class="form-control" placeholder="PER2" autocomplete="off">
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

<!-- KPI Metric Cards: Compare View -->
<div class="row" id="compare-kpi-row">
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-shared-tfs">-</div>
            <div class="metric-label">Shared TFs</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-u1-tfs">-</div>
            <div class="metric-label" id="label-u1">Unique to Gene 1</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-u2-tfs">-</div>
            <div class="metric-label" id="label-u2">Unique to Gene 2</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom text-center">
            <div class="metric" id="metric-total-comb">-</div>
            <div class="metric-label">Total Combined TFs</div>
        </div>
    </div>
</div>

<!-- Interactive Network Area -->
<div class="card-custom">
    <h3 class="section-title" id="network-title">Regulatory Network</h3>
    <div id="network"></div>
</div>

<!-- Top Regulatory TFs DataTable Container -->
<div class="card-custom">
    <h3 class="section-title" id="table-title">Target Transcription Factors</h3>
    <table class="table table-striped" id="tfbsTable" style="width:100%">
        <!-- Table headers generated dynamically via Javascript -->
    </table>
</div>

<!-- Interactive Inspector Panel -->
<div class="row">
    <div class="col-md-6">
        <div class="card-custom">
            <h3 class="section-title">Selected TF Details</h3>
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
                    <td id="ins-label-g1">Sites on G1</td>
                    <td id="ins-val-g1">--</td>
                </tr>
                <tr>
                    <td id="ins-label-g2">Sites on G2</td>
                    <td id="ins-val-g2">--</td>
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
            <h3 class="section-title">Sequence Logo</h3>
            <div class="text-center p-2 border rounded bg-white position-relative d-flex align-items-center justify-content-center" style="min-height: 156px;">
                <div id="logo-spinner" class="spinner-border text-primary position-absolute" role="status" style="display: none;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <img id="ins-logo" src="" alt="Select a TF to load logo" style="max-height: 140px; display: none; transition: opacity 0.15s ease;">
            </div>
        </div>
    </div>
</div>

</div>

<script>
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const g1Param = urlParams.get('g1') || 'PER2';
    const g2Param = urlParams.get('g2') || 'CLOCK';
    const currentScript = window.location.pathname.split('/').pop() || 'tf_compare.php';

    let activeTFData = {}; 
    let dt = null;

    function renderErrorAlert(message) {
        $('#system-alert-box').html(`
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>System Exception:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
    }

    // --- COMPARISON VIEW MODE RUNTIME ---
    $('#gene-title').text(`Comparison: ${g1Param} vs ${g2Param}`);
    $('#gene-id').text('Comparative Genomic Analysis Mode');
    $('#gene-desc').text(`Evaluating cross-regulation metrics between targets.`);
    $('#network-title').text(`Combined Comparative Network Map`);
    $('#table-title').text(`Consolidated Comparative Targets Mapping`);

    // Populate compare form fields with current query parameters
    $('#compare-gene1').val(g1Param);
    $('#compare-gene2').val(g2Param);

    // Initialize DataTable Structure for Comparison
    buildCompareTableHeaders(g1Param, g2Param);
    dt = $('#tfbsTable').DataTable({
        pageLength: 5,
        lengthMenu: [5, 10, 20],
        order: [[4, 'asc']], 
        dom: 'ftp',
        columns: [
            { data: 'motif_alt_id', className: 'text-primary fw-bold tf-row' },
            { data: 'matrix_id', render: d => `<code>${d}</code>` },
            { data: 'sites_g1', render: d => `<strong>${d}</strong>` },
            { data: 'sites_g2', render: d => `<strong>${d}</strong>` },
            { 
                data: 'relation', 
                render: function(d) {
                    if (d === 'Shared') return '<span class="badge bg-warning text-dark">Shared TF</span>';
                    if (d === 'G1_Unique') return `<span class="badge bg-primary">Unique to ${g1Param}</span>`;
                    return `<span class="badge bg-success">Unique to ${g2Param}</span>`;
                }
            }
        ]
    });

    // Parallel requests to parse targets
    const req1 = $.getJSON(`tf_backend.php?action=get_gene_details&search=${encodeURIComponent(g1Param)}`);
    const req2 = $.getJSON(`tf_backend.php?action=get_gene_details&search=${encodeURIComponent(g2Param)}`);

    $.when(req1, req2).done(function(r1, r2) {
        const data1 = r1[0];
        const data2 = r2[0];

        if (!data1.success || !data2.success) {
            const errorMsg = (!data1.success ? data1.error : data2.error) || 'One or both comparative genes were not found.';
            renderErrorAlert(errorMsg);
            $('#network').html(`<div class="d-flex align-items-center justify-content-center h-100 text-danger">Error: ${errorMsg}</div>`);
            return;
        }

        const tfs1 = data1.tf_list || [];
        const tfs2 = data2.tf_list || [];

        if (tfs1.length === 0 && tfs2.length === 0) {
            $('#network').html('<div class="d-flex align-items-center justify-content-center h-100 text-muted">No transcription factors found for either gene to compare.</div>');
            return;
        }

        // Find overlapping sets
        const tfMap1 = {};
        const tfMap2 = {};
        tfs1.forEach(t => tfMap1[t.motif_alt_id] = t);
        tfs2.forEach(t => tfMap2[t.motif_alt_id] = t);

        const allTfNames = Array.from(new Set([...Object.keys(tfMap1), ...Object.keys(tfMap2)]));
        
        let sharedCount = 0;
        let u1Count = 0;
        let u2Count = 0;
        const comparativeList = [];

        // OPTIMIZATION: Sort all TFs by combined weight so we can cap the Cytoscape elements to top 120
        const sortedTfNames = allTfNames.slice().sort((nameA, nameB) => {
            const scoreA = (tfMap1[nameA] ? tfMap1[nameA].sites_on_target : 0) + (tfMap2[nameA] ? tfMap2[nameA].sites_on_target : 0);
            const scoreB = (tfMap1[nameB] ? tfMap1[nameB].sites_on_target : 0) + (tfMap2[nameB] ? tfMap2[nameB].sites_on_target : 0);
            return scoreB - scoreA;
        });

        const elements = [];
        // Add central comparative targets
        elements.push({ data: { id: 'G1_NODE', label: g1Param, size: 85 }, classes: 'gene1' });
        elements.push({ data: { id: 'G2_NODE', label: g2Param, size: 85 }, classes: 'gene2' });

        const renderLimit = 120; // Limits Cytoscape elements to top 120 TFs for instantaneous rendering

        sortedTfNames.forEach((name, idx) => {
            const isG1 = name in tfMap1;
            const isG2 = name in tfMap2;

            let relation = '';
            let sitesG1 = 0;
            let sitesG2 = 0;
            let matrixId = '';

            if (isG1 && isG2) {
                relation = 'Shared';
                sharedCount++;
                sitesG1 = tfMap1[name].sites_on_target;
                sitesG2 = tfMap2[name].sites_on_target;
                matrixId = tfMap1[name].matrix_id;
            } else if (isG1) {
                relation = 'G1_Unique';
                u1Count++;
                sitesG1 = tfMap1[name].sites_on_target;
                matrixId = tfMap1[name].matrix_id;
            } else {
                relation = 'G2_Unique';
                u2Count++;
                sitesG2 = tfMap2[name].sites_on_target;
                matrixId = tfMap2[name].matrix_id;
            }

            const rowObj = {
                motif_alt_id: name,
                matrix_id: matrixId,
                sites_g1: sitesG1,
                sites_g2: sitesG2,
                relation: relation
            };

            // DataTable always loads the entire dataset so search remains fully functional
            comparativeList.push(rowObj);

            // Populate local registry data for click details
            const logoUrl = "https://jaspar.elixir.no/static/logos/all/svg/" + matrixId + ".svg";
            
            activeTFData[name] = {
                name: name,
                motif_id: matrixId,
                logo_url: logoUrl,
                relation: relation,
                sites_g1: sitesG1,
                sites_g2: sitesG2
            };

            // Capped Cytoscape node definition for performance
            if (idx < renderLimit) {
                let nodeClass = 'tf-shared';
                if (relation === 'G1_Unique') nodeClass = 'tf-unique1';
                if (relation === 'G2_Unique') nodeClass = 'tf-unique2';

                elements.push({
                    data: {
                        id: name,
                        label: name,
                        size: Math.min(100, 50 + ((sitesG1 + sitesG2) * 3))
                    },
                    classes: nodeClass
                });

                // Edges matching
                if (isG1) {
                    elements.push({ data: { source: name, target: 'G1_NODE', weight: sitesG1 } });
                }
                if (isG2) {
                    elements.push({ data: { source: name, target: 'G2_NODE', weight: sitesG2 } });
                }
            }
        });

        // Update comparative KPIs
        $('#metric-shared-tfs').text(sharedCount);
        $('#metric-u1-tfs').text(u1Count);
        $('#metric-u2-tfs').text(u2Count);
        $('#metric-total-comb').text(allTfNames.length);

        $('#label-u1').text(`Unique to ${g1Param}`);
        $('#label-u2').text(`Unique to ${g2Param}`);

        // Draw table
        dt.clear().rows.add(comparativeList).draw();

        // Load Network mapping
        initCompareNetwork(elements);

        if (comparativeList.length > 0) {
            loadCompareInspectorTF(comparativeList[0].motif_alt_id, g1Param, g2Param);
        }
    }).fail(function() {
        renderErrorAlert('Failed to communicate with the comparison backend service.');
        $('#network').html('<div class="d-flex align-items-center justify-content-center h-100 text-danger">Connection Error</div>');
    });

    // --- INTERACTIVE EVENT LISTENER HOOKS ---

    // Autocomplete Setup Helper for Compare Inputs
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

    // Dismiss suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#compare-gene1, #compareGene1Suggestions').length) {
            $('#compareGene1Suggestions').hide();
        }
        if (!$(e.target).closest('#compare-gene2, #compareGene2Suggestions').length) {
            $('#compareGene2Suggestions').hide();
        }
    });

    // Comparison Action Button - Launches target query in a new browser tab
    $('#btnCompare').click(function() {
        const g1 = $('#compare-gene1').val().trim() || 'PER2';
        const g2 = $('#compare-gene2').val().trim() || 'CLOCK';
        const compareUrl = `tf_compare.php?g1=${encodeURIComponent(g1)}&g2=${encodeURIComponent(g2)}`;
        window.open(compareUrl, '_blank');
    });

    // Sync Table selection event to updates inspector details
    $('#tfbsTable tbody').on('click', 'tr', function () {
        const rowData = dt.row(this).data();
        if (rowData && rowData.motif_alt_id) {
            loadCompareInspectorTF(rowData.motif_alt_id, g1Param, g2Param);
        }
    });


    // --- RENDERING ROUTINES ---

    function buildCompareTableHeaders(g1, g2) {
        $('#tfbsTable').html(`
            <thead>
                <tr>
                    <th>TF</th>
                    <th>Motif ID</th>
                    <th>Sites on ${g1}</th>
                    <th>Sites on ${g2}</th>
                    <th>Relation Profile</th>
                </tr>
            </thead>
            <tbody></tbody>
        `);
    }

    // Comparative Inspector updating
    function loadCompareInspectorTF(tfName, g1Name, g2Name) {
        const tf = activeTFData[tfName];
        if (!tf) return;

        $('#ins-name').text(tf.name);
        $('#ins-motif').text(tf.motif_id);

        $('#ins-label-g1').text(`Sites on ${g1Name}`);
        $('#ins-val-g1').html(`<strong>${tf.sites_g1}</strong>`);

        $('#ins-label-g2').text(`Sites on ${g2Name}`);
        $('#ins-val-g2').html(`<strong>${tf.sites_g2}</strong>`);

        setInspectorLogo(tf.logo_url);

        const statusBadge = $('#ins-status');
        if (tf.relation === 'Shared') {
            statusBadge.html('<span class="badge bg-warning text-dark">Shared Target TF</span>');
        } else if (tf.relation === 'G1_Unique') {
            statusBadge.html(`<span class="badge bg-primary">Unique to ${g1Name}</span>`);
        } else {
            statusBadge.html(`<span class="badge bg-success">Unique to ${g2Name}</span>`);
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

    // Comparative network layout rendering engine
    function initCompareNetwork(elements) {
        cytoscape({
            container: document.getElementById('network'),
            elements: elements,
            style: [
                {
                    selector: 'node.gene1',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#0d6efd',
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
                    selector: 'node.gene2',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#198754',
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
                    selector: 'node.tf-shared',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#ffc107',
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'color': '#212529',
                        'font-size': '11px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-weight': 'bold',
                        'border-width': 2,
                        'border-color': '#d39e00'
                    }
                },
                {
                    selector: 'node.tf-unique1',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#cfe2ff',
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'color': '#084298',
                        'font-size': '11px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-weight': 'bold',
                        'border-width': 1,
                        'border-color': '#0d6efd'
                    }
                },
                {
                    selector: 'node.tf-unique2',
                    style: {
                        'label': 'data(label)',
                        'background-color': '#d1e7dd',
                        'width': 'data(size)',
                        'height': 'data(size)',
                        'color': '#0f5132',
                        'font-size': '11px',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-weight': 'bold',
                        'border-width': 1,
                        'border-color': '#198754'
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 'mapData(weight, 1, 20, 1.5, 6)',
                        'line-color': '#cbd5e1',
                        'curve-style': 'bezier'
                    }
                }
            ],
            layout: {
                name: 'concentric',
                fit: true,
                padding: 40,
                concentric: function(ele) { return ele.degree(); },
                levelWidth: function() { return 1; }
            }
        }).on('tap', 'node', function(evt){
            const node = evt.target;
            // Ignore standard gene node triggers
            if(node.id() !== 'G1_NODE' && node.id() !== 'G2_NODE') {
                loadCompareInspectorTF(node.id(), g1Param, g2Param);
            }
        });
    }
});
</script>

</body>
</html>
<?php
include 'footer.php';
?>