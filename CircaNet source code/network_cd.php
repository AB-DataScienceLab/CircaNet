<?php
// 1. DATABASE CONNECTION & AJAX ENDPOINTS
require_once 'conn.php';

// Handle self-contained AJAX routing
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // Action: Search for proteins dynamically (for Select2)
    if ($action == 'search_proteins') {
        $term = isset($_GET['term']) ? trim($_GET['term']) : '';
        $results = [];
        
        if (!empty($term)) {
            $stmt = $conn->prepare("
                SELECT DISTINCT preferredName_A as protein FROM ppi_tb WHERE preferredName_A LIKE ? 
                UNION 
                SELECT DISTINCT preferredName_B as protein FROM ppi_tb WHERE preferredName_B LIKE ? 
                LIMIT 30
            ");
            $likeTerm = "%" . $term . "%";
            $stmt->bind_param("ss", $likeTerm, $likeTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $results[] = ['id' => $row['protein'], 'text' => $row['protein']];
            }
        }
        echo json_encode($results);
        exit;
    }

    // Action: Fetch full subnetwork for a selected Community Module
    if ($action == 'get_module_network') {
        $module_name = $_GET['module'] ?? '';
        if (empty($module_name)) {
            echo json_encode(['error' => 'No module selected']);
            exit;
        }

        // Fetch Community metadata from ppi_cd_tb
        $stmt = $conn->prepare("SELECT * FROM ppi_cd_tb WHERE name = ?");
        $stmt->bind_param("s", $module_name);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();

        if (!$meta) {
            echo json_encode(['error' => 'Community Module not found']);
            exit;
        }

        // Parse member lists (Space-separated)
        $members = array_filter(explode(' ', trim($meta['CD_MemberList'])));
        $annotated = array_filter(explode(' ', trim($meta['CD_AnnotatedMembers'])));
        $annotated_set = array_flip($annotated);

        if (empty($members)) {
            echo json_encode(['error' => 'Selected community has no members registered.']);
            exit;
        }

        // Limit display count on massive communities to ensure layout stability
        $max_nodes = 350;
        $display_members = count($members) > $max_nodes ? array_slice($members, 0, $max_nodes) : $members;

        // Fetch intra-module edges from ppi_tb
        $placeholders = implode(',', array_fill(0, count($display_members), '?'));
        $types = str_repeat('s', count($display_members) * 2);
        $params = array_merge($display_members, $display_members);

        $sql = "SELECT preferredName_A, preferredName_B, combined_score 
                FROM ppi_tb 
                WHERE preferredName_A IN ($placeholders) 
                  AND preferredName_B IN ($placeholders)
                ORDER BY combined_score DESC 
                LIMIT 1500";

        $stmt_edges = $conn->prepare($sql);
        $stmt_edges->bind_param($types, ...$params);
        $stmt_edges->execute();
        $edges_res = $stmt_edges->get_result();

        $nodes = [];
        $edges = [];
        $seen_nodes = [];

        while ($row = $edges_res->fetch_assoc()) {
            $source = $row['preferredName_A'];
            $target = $row['preferredName_B'];
            
            $edges[] = [
                'data' => [
                    'id' => $source . '-' . $target,
                    'source' => $source,
                    'target' => $target,
                    'weight' => (float)$row['combined_score']
                ]
            ];
            $seen_nodes[$source] = true;
            $seen_nodes[$target] = true;
        }

        // Include any singleton nodes that don't have edges to preserve complete coverage
        foreach ($display_members as $member) {
            if (!isset($seen_nodes[$member])) {
                $seen_nodes[$member] = true;
            }
        }

        // Build Cytoscape nodes
        foreach (array_keys($seen_nodes) as $node_id) {
            $is_annotated = isset($annotated_set[$node_id]);
            $nodes[] = [
                'data' => [
                    'id' => $node_id,
                    'label' => $node_id,
                    'module' => $module_name,
                    'annotated' => $is_annotated ? 'Yes' : 'No',
                    'status' => $is_annotated ? 'Annotated Member' : 'Non-Annotated Member'
                ]
            ];
        }

        echo json_encode([
            'success' => true,
            'metadata' => [
                'name' => $meta['name'],
                'community' => $meta['CD_CommunityName'],
                'source_db' => $meta['CD_AnnotatedMembers_SourceDB'],
                'source_term' => $meta['CD_AnnotatedMembers_SourceTerm'],
                'p_value' => $meta['CD_AnnotatedMembers_Pvalue'],
                'overlap' => $meta['CD_AnnotatedMembers_Overlap'],
                'size' => $meta['CD_MemberList_Size'],
                'algorithm' => $meta['CD_AnnotatedAlgorithm']
            ],
            'nodes' => $nodes,
            'edges' => $edges
        ]);
        exit;
    }

    // Action: Compare two communities side by side
    if ($action == 'compare_communities') {
        $mod1 = $_GET['mod1'] ?? '';
        $mod2 = $_GET['mod2'] ?? '';

        if (empty($mod1) || empty($mod2)) {
            echo json_encode(['error' => 'Select two communities to compare']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM ppi_cd_tb WHERE name IN (?, ?)");
        $stmt->bind_param("ss", $mod1, $mod2);
        $stmt->execute();
        $res = $stmt->get_result();

        $modules_data = [];
        while ($row = $res->fetch_assoc()) {
            $modules_data[$row['name']] = $row;
        }

        if (count($modules_data) < 2) {
            echo json_encode(['error' => 'Could not find database records for both communities']);
            exit;
        }

        $m1 = $modules_data[$mod1];
        $m2 = $modules_data[$mod2];

        $list1 = array_filter(explode(' ', trim($m1['CD_MemberList'])));
        $list2 = array_filter(explode(' ', trim($m2['CD_MemberList'])));

        $intersection = array_values(array_intersect($list1, $list2));

        echo json_encode([
            'success' => true,
            'mod1' => [
                'name' => $m1['name'],
                'community' => $m1['CD_CommunityName'],
                'size' => count($list1),
                'source_db' => $m1['CD_AnnotatedMembers_SourceDB'],
                'source_term' => $m1['CD_AnnotatedMembers_SourceTerm'],
                'p_value' => $m1['CD_AnnotatedMembers_Pvalue']
            ],
            'mod2' => [
                'name' => $m2['name'],
                'community' => $m2['CD_CommunityName'],
                'size' => count($list2),
                'source_db' => $m2['CD_AnnotatedMembers_SourceDB'],
                'source_term' => $m2['CD_AnnotatedMembers_SourceTerm'],
                'p_value' => $m2['CD_AnnotatedMembers_Pvalue']
            ],
            'shared_genes' => $intersection,
            'shared_count' => count($intersection)
        ]);
        exit;
    }
}

// 2. FETCH GENERAL PAGE INITIALIZATION DATA
$modules = [];
try {
    $mod_sql = "SELECT name, CD_CommunityName, CD_MemberList_Size, CD_AnnotatedMembers_Pvalue 
                FROM ppi_cd_tb 
                ORDER BY CD_MemberList_Size DESC";
    $mod_result = $conn->query($mod_sql);
    if ($mod_result && $mod_result->num_rows > 0) {
        while ($row = $mod_result->fetch_assoc()) {
            $modules[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error preparing initial modules dataset: " . $e->getMessage());
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PPI Systems Biology Module Dashboard</title>
    
    <!-- External UI Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <!-- Cytoscape Engine -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.26.0/cytoscape.min.js"></script>

    <style>
        :root {
            --primary: #2c3e50; --accent: #3498db; --success: #27ae60;
            --warning: #f39c12; --danger: #e74c3c; --bg: #f8f9fa;
        }
        body { background: var(--bg); color: var(--primary); font-family: 'Segoe UI', sans-serif; }
        
        /* Interactive Panel Elements */
        .workspace-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background-color: #ffffff; }
        #cy { height: 550px; width: 100%; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; }
        .info-card { border: 1px solid #edf2f7; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fafbfc; }
        .dot { width: 11px; height: 11px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .toolbar-box { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <!-- Header banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <h2 class="mb-1 text-primary fw-bold"><i class="fas fa-network-wired"></i> PPI Systems Biology Dashboard</h2>
            <p class="text-muted mb-0">Explore dynamic communities detected via HiDeF with active gProfiler enrichment annotations</p>
        </div>
        <div class="text-end">
            <span class="badge bg-primary px-3 py-2">Data Engine: ppi_cd_tb & ppi_tb</span>
        </div>
    </div>

    <!-- Toolbar controls -->
    <div class="toolbar-box">
        <div class="row g-3 align-items-end">
            <!-- Gene Search -->
            <div class="col-xl-3 col-md-6">
                <label class="form-label fw-bold text-secondary text-uppercase small">Gene / Protein Search</label>
                <select id="geneSearch" class="form-select" style="width: 100%;">
                    <option value="">Select or Type a Gene...</option>
                </select>
            </div>

            <!-- Pathway/Community Selector -->
            <div class="col-xl-4 col-md-6">
                <label class="form-label fw-bold text-secondary text-uppercase small">Filter By Community (Pathway / Enrichment)</label>
                <select id="moduleSelect" class="form-select">
                    <option value="">-- Choose Community Module --</option>
                    <?php foreach ($modules as $mod): ?>
                        <option value="<?php echo htmlspecialchars($mod['name']); ?>">
                            <?php echo htmlspecialchars($mod['name'] . " - " . ($mod['CD_CommunityName'] ?: 'Unannotated Module') . " (" . $mod['CD_MemberList_Size'] . " Genes)"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Comparative Analysis tool -->
            <div class="col-xl-5 col-md-12">
                <label class="form-label fw-bold text-secondary text-uppercase small">Compare Two Communities</label>
                <div class="input-group">
                    <select id="compMod1" class="form-select form-select-sm">
                        <option value="">Module A</option>
                        <?php foreach ($modules as $mod): ?>
                            <option value="<?php echo htmlspecialchars($mod['name']); ?>"><?php echo htmlspecialchars($mod['name'] . " (" . $mod['CD_MemberList_Size'] . ")"); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="compMod2" class="form-select form-select-sm">
                        <option value="">Module B</option>
                        <?php foreach ($modules as $mod): ?>
                            <option value="<?php echo htmlspecialchars($mod['name']); ?>"><?php echo htmlspecialchars($mod['name'] . " (" . $mod['CD_MemberList_Size'] . ")"); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-success" onclick="compareCommunities()"><i class="fas fa-balance-scale"></i> Compare</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main network layout panel -->
    <div class="row mb-5">
        <!-- Interactive Canvas Window -->
        <div class="col-lg-9 col-md-8 mb-4">
            <div class="card workspace-card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-project-diagram"></i> Interactive Network Visualizer</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="changeLayout('cose')">Cose Layout</button>
                        <button class="btn btn-outline-secondary" onclick="changeLayout('circle')">Circle</button>
                        <button class="btn btn-outline-secondary" onclick="changeLayout('grid')">Grid</button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="networkPlaceholder" class="alert alert-info text-center py-5 my-3">
                        <i class="fas fa-network-wired fa-3x mb-3 text-accent"></i>
                        <h5>Ready for Enrichment Rendering</h5>
                        <p class="mb-0 text-muted">Select a functional community pathway from the filter dropdown above to query interaction networks.</p>
                    </div>
                    <div id="cy" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Annotation and metadata Sidebar -->
        <div class="col-lg-3 col-md-4 mb-4">
            <!-- Selected Object properties -->
            <div class="card workspace-card mb-4 h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="info-card">
                            <h6 class="text-secondary fw-bold text-uppercase small mb-2"><i class="fas fa-info-circle text-primary"></i> Active Node Details</h6>
                            <div id="nodeDetails">Click a gene node in the network graph to inspect biological attributes.</div>
                        </div>

                        <!-- gProfiler enrichment details -->
                        <div class="info-card">
                            <h6 class="text-secondary fw-bold text-uppercase small mb-2"><i class="fas fa-award text-success"></i> gProfiler Enrichment</h6>
                            <div id="enrichmentDetails" class="text-muted small">No active community selection. Select a module to display enrichment properties.</div>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="info-card mb-0">
                        <h6 class="text-secondary fw-bold text-uppercase small mb-2"><i class="fas fa-map-signs"></i> Network Guide</h6>
                        <div style="font-size: 11px;">
                            <div class="mb-1"><span class="dot" style="background-color: #B10DC9;"></span> Metabolic Pathways</div>
                            <div class="mb-1"><span class="dot" style="background-color: #FF851B;"></span> RNA Metabolism</div>
                            <div class="mb-1"><span class="dot" style="background-color: #2ECC40;"></span> Translation Module</div>
                            <div class="mb-1"><span class="dot" style="background-color: #3498db;"></span> Other Functional Clusters</div>
                            <div class="mt-2 border-top pt-2 text-danger fw-bold"><i class="fas fa-circle-notch"></i> Red Border Node: Annotated Gene</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison panel (Initially hidden) -->
    <div id="comparisonBox" class="card workspace-card mb-5" style="display:none;">
        <div class="card-header bg-success text-white py-3 border-0">
            <h5 class="mb-0 fw-bold"><i class="fas fa-chart-bar"></i> Side-by-Side Community Comparative Analysis</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5">
                    <div class="p-3 border rounded bg-light">
                        <h5 class="text-primary" id="compName1">Module A</h5>
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><th>Functional Annotation:</th><td id="compComm1">-</td></tr>
                            <tr><th>Source Database:</th><td id="compDB1">-</td></tr>
                            <tr><th>Term Identifier:</th><td id="compTerm1">-</td></tr>
                            <tr><th>P-Value (Enriched):</th><td id="compPval1">-</td></tr>
                            <tr><th>Total Genes:</th><td id="compSize1">-</td></tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-2 text-center d-flex flex-column justify-content-center align-items-center my-3 my-md-0">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6">Shared Overlap</span>
                    <h3 class="fw-bold text-success mt-2" id="compSharedCount">0</h3>
                    <p class="text-muted small">Genes</p>
                </div>
                <div class="col-md-5">
                    <div class="p-3 border rounded bg-light">
                        <h5 class="text-primary" id="compName2">Module B</h5>
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><th>Functional Annotation:</th><td id="compComm2">-</td></tr>
                            <tr><th>Source Database:</th><td id="compDB2">-</td></tr>
                            <tr><th>Term Identifier:</th><td id="compTerm2">-</td></tr>
                            <tr><th>P-Value (Enriched):</th><td id="compPval2">-</td></tr>
                            <tr><th>Total Genes:</th><td id="compSize2">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <h6 class="fw-bold"><i class="fas fa-dna"></i> Overlapping Member Gene List:</h6>
                <div class="p-3 border rounded" id="compOverlappingGenes" style="max-height: 120px; overflow-y: auto; background-color: #fafbfc; font-family: monospace; font-size: 12px;"></div>
            </div>
        </div>
    </div>

    <!-- Bottom Data Grid Panel -->
    <div class="card workspace-card shadow-sm mb-5">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-table"></i> Active Module Gene Inventory</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="geneInventoryTable" class="table table-striped table-bordered w-100" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Gene Name</th>
                            <th>Parent Module</th>
                            <th>Annotated (gProfiler)</th>
                            <th>Biological Subgroup</th>
                            <th>Connection Degree</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted">No community module has been selected. Use the filter controls above to list gene members.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Core Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    var cyInstance = null;
    var dataGridTable = null;

    $(document).ready(function() {
        // Initialize Interactive DataTable
        dataGridTable = $('#geneInventoryTable').DataTable({
            pageLength: 10,
            destroy: true,
            order: [[0, 'asc']]
        });

        // Initialize Select2 Gene Search Box via AJAX
        $('#geneSearch').select2({
            theme: 'bootstrap-5',
            ajax: {
                url: 'network_cd.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { term: params.term, action: 'search_proteins' };
                },
                processResults: function(data) {
                    return { results: data };
                },
                cache: true
            },
            placeholder: 'Search network by gene symbol (e.g. UQCRHL)',
            allowClear: true
        });

        // Handle Gene Search Node highlighting
        $('#geneSearch').on('change', function() {
            var geneName = $(this).val();
            if (geneName && cyInstance) {
                focusOnNetworkNode(geneName);
            }
        });

        // Handle Module Selection
        $('#moduleSelect').on('change', function() {
            var selectedModule = $(this).val();
            if (selectedModule) {
                loadModuleNetwork(selectedModule);
            } else {
                resetWorkspace();
            }
        });
    });

    // Request module network details via AJAX
    function loadModuleNetwork(moduleName) {
        $('#networkPlaceholder').show().html('<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Fetching and building community subnetwork...</p>');
        $('#cy').hide();

        $.getJSON('network_cd.php', { action: 'get_module_network', module: moduleName }, function(response) {
            if (response.success) {
                $('#networkPlaceholder').hide();
                $('#cy').show();
                
                renderSubnetwork(response);
                populateEnrichmentCard(response.metadata);
                populateTableInventory(response.nodes);
            } else {
                $('#networkPlaceholder').show().html(`<div class="alert alert-danger">${response.error}</div>`);
            }
        });
    }

    // Render interactive network using Cytoscape.js
    function renderSubnetwork(networkData) {
        cyInstance = cytoscape({
            container: document.getElementById('cy'),
            elements: {
                nodes: networkData.nodes,
                edges: networkData.edges
            },
            style: [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(id)',
                        'width': 35,
                        'height': 35,
                        'font-size': '10px',
                        'text-valign': 'center',
                        'background-color': '#95a5a6',
                        'color': '#2c3e50',
                        'font-weight': 'bold',
                        'text-outline-width': 1.5,
                        'text-outline-color': '#ffffff'
                    }
                },
                // Red border styling for annotated members
                {
                    selector: 'node[annotated="Yes"]',
                    style: {
                        'border-width': 4,
                        'border-color': '#e74c3c'
                    }
                },
                // Community module specific color codes
                {
                    selector: 'node[module="C3521546"]',
                    style: {
                        'background-color': '#B10DC9',
                        'color': '#ffffff',
                        'text-outline-color': '#B10DC9'
                    }
                },
                {
                    selector: 'node[module="C3521547"]',
                    style: {
                        'background-color': '#FF851B',
                        'color': '#ffffff',
                        'text-outline-color': '#FF851B'
                    }
                },
                {
                    selector: 'node[module="C3521548"]',
                    style: {
                        'background-color': '#2ECC40',
                        'color': '#ffffff',
                        'text-outline-color': '#2ECC40'
                    }
                },
                // Other newly loaded modules
                {
                    selector: 'node[module][module != "C3521546"][module != "C3521547"][module != "C3521548"]',
                    style: {
                        'background-color': '#3498db',
                        'color': '#ffffff',
                        'text-outline-color': '#3498db'
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 2,
                        'line-color': '#cbd5e1',
                        'curve-style': 'bezier',
                        'opacity': 0.6
                    }
                },
                // Interactivity styles
                {
                    selector: '.faded',
                    style: {
                        'opacity': 0.15
                    }
                },
                {
                    selector: '.highlighted',
                    style: {
                        'width': 50,
                        'height': 50,
                        'font-size': '13px',
                        'z-index': 999
                    }
                }
            ],
            layout: {
                name: 'cose',
                animate: true
            }
        });

        // Trigger information display on node selection
        cyInstance.on('tap', 'node', function(evt) {
            var node = evt.target;
            focusOnNetworkNode(node.id());
        });
    }

    // Dynamic layout alteration
    function changeLayout(layoutName) {
        if (cyInstance) {
            cyInstance.layout({ name: layoutName, animate: true }).run();
        }
    }

    // Node Focus, highlighting, and Side panel populating
    function focusOnNetworkNode(nodeId) {
        if (!cyInstance) return;

        var targetNode = cyInstance.getElementById(nodeId);
        if (targetNode.length === 0) {
            alert(`Gene '${nodeId}' is not loaded in the current active pathway module.`);
            return;
        }

        cyInstance.elements().addClass('faded');
        targetNode.removeClass('faded').addClass('highlighted');
        targetNode.neighborhood().removeClass('faded');

        cyInstance.animate({
            center: { eles: targetNode },
            zoom: 1.8
        }, { duration: 500 });

        // Populate side details panel
        var data = targetNode.data();
        var deg = targetNode.degree();
        
        var detailsHtml = `
            <table class="table table-sm table-borderless mb-0 text-dark" style="font-size:12px;">
                <tr><th>Symbol Name:</th><td><b class="text-primary">${data.id}</b></td></tr>
                <tr><th>Parent Module:</th><td><span class="badge bg-dark">${data.module}</span></td></tr>
                <tr><th>Annotated:</th><td><span class="badge ${data.annotated === 'Yes' ? 'bg-danger' : 'bg-secondary'}">${data.annotated}</span></td></tr>
                <tr><th>Role:</th><td>${data.status}</td></tr>
                <tr><th>Local Degree:</th><td><strong>${deg} connections</strong></td></tr>
            </table>
        `;
        $('#nodeDetails').html(detailsHtml);
    }

    // Populate gProfiler Enrichment Sidebar
    function populateEnrichmentCard(meta) {
        var pValueFormatted = Number(meta.p_value).toExponential(3);
        var enrichmentHtml = `
            <div class="small">
                <div class="mb-1"><strong>Community:</strong> ${meta.community || 'Unannotated Pathways'}</div>
                <div class="mb-1"><strong>Identifier:</strong> <span class="text-primary fw-bold">${meta.name}</span></div>
                <div class="mb-1"><strong>Source Term:</strong> <span class="badge bg-secondary">${meta.source_db || 'N/A'}:${meta.source_term || 'N/A'}</span></div>
                <div class="mb-1"><strong>Overlap Ratio:</strong> ${meta.overlap || 'N/A'}</div>
                <div class="mb-1"><strong>P-Value (gProfiler):</strong> <span class="text-success fw-bold">${pValueFormatted}</span></div>
                <div class="mb-1"><strong>Module Size:</strong> ${meta.size} member genes</div>
                <div class="mt-2 text-muted" style="font-size: 10px;"><em>Pipeline: ${meta.algorithm}</em></div>
            </div>
        `;
        $('#enrichmentDetails').html(enrichmentHtml);
    }

    // Dynamic Bottom Table populating
    function populateTableInventory(nodes) {
        if ($.fn.DataTable.isDataTable('#geneInventoryTable')) {
            dataGridTable.clear().destroy();
        }

        var tbodyHtml = '';
        nodes.forEach(function(node) {
            var data = node.data;
            // Calculate connection degree from loaded elements
            var nodeElement = cyInstance.getElementById(data.id);
            var localDegree = nodeElement.length ? nodeElement.degree() : 0;

            tbodyHtml += `
                <tr>
                    <td class="fw-bold text-primary" style="cursor:pointer;" onclick="focusOnNetworkNode('${data.id}')"><i class="fas fa-search"></i> ${data.id}</td>
                    <td>${data.module}</td>
                    <td><span class="badge ${data.annotated === 'Yes' ? 'bg-danger' : 'bg-secondary'}">${data.annotated}</span></td>
                    <td>${data.status}</td>
                    <td class="fw-bold">${localDegree}</td>
                </tr>
            `;
        });

        $('#inventoryBody').html(tbodyHtml);
        dataGridTable = $('#geneInventoryTable').DataTable({
            pageLength: 10,
            order: [[0, 'asc']]
        });
    }

    // Action: Compare two communities side-by-side
    function compareCommunities() {
        var m1 = $('#compMod1').val();
        var m2 = $('#compMod2').val();

        if (!m1 || !m2) {
            alert("Please choose two communities from the dropdown selectors.");
            return;
        }

        $.getJSON('network_cd.php', { action: 'compare_communities', mod1: m1, mod2: m2 }, function(response) {
            if (response.success) {
                $('#comparisonBox').slideDown();
                
                // Set Module A Properties
                $('#compName1').text(response.mod1.name);
                $('#compComm1').text(response.mod1.community || 'Unannotated');
                $('#compDB1').text(response.mod1.source_db || 'N/A');
                $('#compTerm1').text(response.mod1.source_term || 'N/A');
                $('#compPval1').text(Number(response.mod1.p_value).toExponential(3));
                $('#compSize1').text(response.mod1.size + " Genes");

                // Set Module B Properties
                $('#compName2').text(response.mod2.name);
                $('#compComm2').text(response.mod2.community || 'Unannotated');
                $('#compDB2').text(response.mod2.source_db || 'N/A');
                $('#compTerm2').text(response.mod2.source_term || 'N/A');
                $('#compPval2').text(Number(response.mod2.p_value).toExponential(3));
                $('#compSize2').text(response.mod2.size + " Genes");

                // Set Overlap Info
                $('#compSharedCount').text(response.shared_count);
                if (response.shared_count > 0) {
                    $('#compOverlappingGenes').html(response.shared_genes.join(', '));
                } else {
                    $('#compOverlappingGenes').html('<span class="text-muted italic">No genes are shared between these two community groupings.</span>');
                }
            } else {
                alert(response.error);
            }
        });
    }

    // Reset workspace variables
    function resetWorkspace() {
        if (cyInstance) {
            cyInstance.destroy();
            cyInstance = null;
        }
        $('#cy').hide();
        $('#networkPlaceholder').show().html(`
            <i class="fas fa-network-wired fa-3x mb-3 text-accent"></i>
            <h5>Ready for Enrichment Rendering</h5>
            <p class="mb-0 text-muted">Select a functional community pathway from the filter dropdown above to query interaction networks.</p>
        `);
        $('#nodeDetails').html('Click a gene node in the network graph to inspect biological attributes.');
        $('#enrichmentDetails').html('No active community selection. Select a module to display enrichment properties.');
        $('#comparisonBox').slideUp();
        
        if ($.fn.DataTable.isDataTable('#geneInventoryTable')) {
            dataGridTable.clear().destroy();
        }
        $('#inventoryBody').html('<tr><td colspan="5" class="text-center text-muted">No community module has been selected. Use the filter controls above to list gene members.</td></tr>');
        dataGridTable = $('#geneInventoryTable').DataTable();
    }
</script>
</body>
</html>
<?php
include 'footer.php';
?>