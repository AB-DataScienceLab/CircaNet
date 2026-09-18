<?php
// require_once 'conn.php'; // Commented out for now as we are using dummy data
include 'header.php';

// 1. List of supported tissues
$tissues = ['brain', 'heart', 'kidney', 'liver', 'lung'];

// 2. Pairwise dummy dataset
$dummy_centrality_data = [
    'brain-liver' => [
        ['gene' => 'CLOCK', 'global_centrality_score' => 0.082100],
        ['gene' => 'BMAL1', 'global_centrality_score' => 0.075400],
        ['gene' => 'PER2', 'global_centrality_score' => 0.061200],
        ['gene' => 'A', 'global_centrality_score' => 0.040000],
        ['gene' => 'C', 'global_centrality_score' => 0.006000],
        ['gene' => 'B', 'global_centrality_score' => 0.005000],
    ],
    'brain-heart' => [
        ['gene' => 'NPAS2', 'global_centrality_score' => 0.032100],
        ['gene' => 'PER1', 'global_centrality_score' => 0.021100],
        ['gene' => 'CRY2', 'global_centrality_score' => 0.019800],
    ],
    'heart-liver' => [
        ['gene' => 'DBP', 'global_centrality_score' => 0.045600],
        ['gene' => 'TEF', 'global_centrality_score' => 0.031200],
        ['gene' => 'HLF', 'global_centrality_score' => 0.028900],
    ]
];
?>

<!-- Include modern styles & DataTables dependencies -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<!-- Bootstrap Icons for a modern look -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    /* Global layout enhancements */
    .dashboard-container {
        margin-top: 40px;
        margin-bottom: 60px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .panel-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    
    /* Interactive Human Anatomy Blueprint styling */
    .anatomy-svg-wrapper {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 1px dashed #cbd5e1;
    }
    .anatomy-svg {
        max-height: 480px;
        width: 100%;
    }
    .silhouette {
        fill: #f1f5f9;
        stroke: #cbd5e1;
        stroke-width: 1.5;
    }
    .organ-node {
        cursor: pointer;
    }
    .organ-shape {
        fill: #94a3b8;
        stroke: #64748b;
        stroke-width: 1.5;
        transition: all 0.2s ease-in-out;
    }
    .organ-node:hover .organ-shape {
        fill: #475569;
        stroke: #334155;
        filter: drop-shadow(0px 0px 4px rgba(71, 85, 105, 0.4));
    }
    
    /* Active selection styles for HPA aesthetic */
    .organ-node.selected-1 .organ-shape {
        fill: #f43f5e !important; /* Rose Red */
        stroke: #be123c !important;
        filter: drop-shadow(0 0 8px rgba(244, 63, 94, 0.6));
    }
    .organ-node.selected-2 .organ-shape {
        fill: #3b82f6 !important; /* Blue Accent */
        stroke: #1d4ed8 !important;
        filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.6));
    }

    /* Text pointer helper lines inside the blueprint */
    .blueprint-pointer {
        stroke: #94a3b8;
        stroke-dasharray: 2 2;
        stroke-width: 1;
    }
    .blueprint-text {
        font-size: 10px;
        font-weight: 600;
        fill: #64748b;
    }

    /* UI badges for selection slots */
    .selection-badge {
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .selection-empty {
        background-color: #f1f5f9;
        color: #94a3b8;
        border: 1px dashed #cbd5e1;
    }
    .selection-active-1 {
        background-color: #ffe4e6;
        color: #be123c;
        border: 1px solid #fecdd3;
    }
    .selection-active-2 {
        background-color: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    /* Responsive tables */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 4px 8px;
    }
    .table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
    }
</style>

<div class="container dashboard-container">
    <div class="row mb-4">
        <div class="col-12">
            <span class="badge bg-secondary mb-2">Interactive Analysis Module</span>
            <h2 class="fw-bold text-slate-800">CircaNet Pairwise Organ Network Centrality</h2>
            <p class="text-muted">A dynamic anatomical interface modeled to inspect simulated global centrality scores. Click any two organs inside the human blueprint below to run a comparison.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN: Anatomical Diagram Selector -->
        <div class="col-lg-5 col-md-12">
            <div class="card panel-card p-4 h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-workspace text-primary"></i> Anatomical Blueprint
                </h5>
                <p class="small text-muted mb-4">Click two organs on the body blueprint to set your comparative coordinates.</p>
                
                <div class="anatomy-svg-wrapper">
                    <!-- Custom built inline interactive Vector Blueprint of human body -->
                    <svg class="anatomy-svg" viewBox="0 0 200 380" xmlns="http://www.w3.org/2000/svg">
                        <!-- Upper human body silhouette outline -->
                        <path class="silhouette" d="M 100,20 C 112,20 120,28 120,40 C 120,52 112,60 100,60 C 88,60 80,52 80,40 C 80,28 88,20 100,20 Z M 60,105 C 70,95 85,90 100,90 C 115,90 130,95 140,105 C 145,115 145,130 145,145 C 145,165 140,185 135,230 C 132,260 130,290 130,350 L 70,350 C 70,290 68,260 65,230 C 60,185 55,165 55,145 C 55,130 55,115 60,105 Z" />

                        <!-- Brain Organ Node -->
                        <g class="organ-node" data-tissue="brain" transform="translate(100, 40)">
                            <circle class="organ-shape" r="14" />
                            <!-- Brain internal abstract folds -->
                            <path d="M -7,0 C -7,-4 -4,-7 0,-7 C 4,-7 7,-4 7,0 C 7,4 4,7 0,7 C -4,7 -7,4 -7,0 Z" fill="none" stroke="#fff" stroke-width="0.8" opacity="0.6"/>
                            <line x1="0" y1="-7" x2="0" y2="7" stroke="#fff" stroke-width="0.8" opacity="0.6" />
                        </g>
                        <!-- Pointer line + text for Brain -->
                        <line x1="114" y1="40" x2="160" y2="40" class="blueprint-pointer" />
                        <text x="163" y="43" class="blueprint-text">BRAIN</text>

                        <!-- Lungs Organ Node -->
                        <g class="organ-node" data-tissue="lung" transform="translate(100, 135)">
                            <!-- Left lung -->
                            <path class="organ-shape" d="M -5,-22 C -16,-22 -20,-10 -18,8 C -16,20 -7,24 -4,24 C -1,24 0,8 0,-4 C 0,-16 -1,-22 -5,-22 Z" />
                            <!-- Right lung -->
                            <path class="organ-shape" d="M 5,-22 C 16,-22 20,-10 18,8 C 16,20 7,24 4,24 C 1,24 0,8 0,-4 C 0,-16 1,-22 5,-22 Z" />
                        </g>
                        <!-- Pointer line + text for Lungs -->
                        <line x1="120" y1="135" x2="160" y2="135" class="blueprint-pointer" />
                        <text x="163" y="138" class="blueprint-text">LUNGS</text>

                        <!-- Heart Organ Node -->
                        <g class="organ-node" data-tissue="heart" transform="translate(105, 142)">
                            <path class="organ-shape" d="M 0,7 C 0,7 -8,2 -8,-4 C -8,-9 -3,-9 0,-5 C 3,-9 8,-9 8,-4 C 8,2 0,7 0,7 Z" />
                        </g>
                        <!-- Pointer line + text for Heart -->
                        <line x1="97" y1="142" x2="35" y2="142" class="blueprint-pointer" />
                        <text x="5" y="145" class="blueprint-text">HEART</text>

                        <!-- Liver Organ Node -->
                        <g class="organ-node" data-tissue="liver" transform="translate(85, 195)">
                            <path class="organ-shape" d="M -15,-6 L 15,-10 L 8,10 C 3,12 -10,6 -15,-6 Z" />
                        </g>
                        <!-- Pointer line + text for Liver -->
                        <line x1="70" y1="195" x2="35" y2="195" class="blueprint-pointer" />
                        <text x="10" y="198" class="blueprint-text">LIVER</text>

                        <!-- Kidneys Organ Node -->
                        <g class="organ-node" data-tissue="kidney" transform="translate(100, 245)">
                            <!-- Left kidney -->
                            <path class="organ-shape" d="M -16,-8 C -20,-8 -22,-4 -22,2 C -22,8 -18,10 -16,10 C -14,10 -13,6 -13,2 C -13,-2 -14,-8 -16,-8 Z" />
                            <!-- Right kidney -->
                            <path class="organ-shape" d="M 16,-8 C 20,-8 22,-4 22,2 C 22,8 18,10 16,10 C 14,10 13,6 13,2 C 13,-2 14,-8 16,-8 Z" />
                        </g>
                        <!-- Pointer line + text for Kidneys -->
                        <line x1="124" y1="245" x2="160" y2="245" class="blueprint-pointer" />
                        <text x="163" y="248" class="blueprint-text">KIDNEYS</text>
                    </svg>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Analytical Workspace & Dynamic Tables -->
        <div class="col-lg-7 col-md-12">
            <div class="card panel-card p-4 h-100 d-flex flex-column">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart-line text-primary"></i> Analysis Workspace
                </h5>

                <!-- Selected Coordinates Display -->
                <div class="row mb-4 g-2">
                    <div class="col-sm-6">
                        <span class="d-block text-xs text-muted mb-1 uppercase fw-bold" style="font-size: 11px;">TISSUE COORDINATE 1</span>
                        <div id="slot-1" class="selection-badge selection-empty w-100">
                            <i class="bi bi-circle"></i> <span>None Selected</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="d-block text-xs text-muted mb-1 uppercase fw-bold" style="font-size: 11px;">TISSUE COORDINATE 2</span>
                        <div id="slot-2" class="selection-badge selection-empty w-100">
                            <i class="bi bi-circle"></i> <span>None Selected</span>
                        </div>
                    </div>
                </div>

                <!-- Conditional workspaces state holder -->
                <div id="workspace-placeholder" class="my-auto text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-intersect" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="fw-bold text-slate-700">Waiting for tissue comparison coordinate selections</h6>
                    <p class="text-muted small px-4">Please click two highlighted systems on the anatomical model on the left. The analysis metrics will automatically populate.</p>
                </div>

                <!-- Dynamic Data Table Workspace (Hidden on start) -->
                <div id="workspace-results" class="d-none flex-grow-1">
                    <div class="table-responsive">
                        <table id="centralityTable" class="table table-striped table-bordered align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Gene Identifier</th>
                                    <th>Global Centrality Score</th>
                                </tr>
                            </thead>
                            <tbody id="centralityTableBody">
                                <!-- Appended dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Scripts for dynamic dashboard transitions -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Bridges PHP backend structures safely with the interactive client
    const dummyCentralityData = <?php echo json_encode($dummy_centrality_data); ?>;
    
    // Tracks active coordinates
    let selectedTissues = [];
    let tableInstance = null;

    $(document).ready(function() {
        $('.organ-node').on('click', function() {
            const tissue = $(this).attr('data-tissue');
            const index = selectedTissues.indexOf(tissue);

            if (index > -1) {
                // If clicked a tissue that's already selected, remove it
                selectedTissues.splice(index, 1);
            } else {
                // Keep queue limit to 2 maximum selections
                if (selectedTissues.length >= 2) {
                    selectedTissues.shift(); // Remove oldest
                }
                selectedTissues.push(tissue);
            }

            updateVisualStates();
        });
    });

    /**
     * Re-renders active classes, updates textual slots, and refreshes the centrality table
     */
    function updateVisualStates() {
        // Reset classes on vector map
        $('.organ-node').removeClass('selected-1 selected-2');

        // Render first selection
        if (selectedTissues[0]) {
            $(`.organ-node[data-tissue="${selectedTissues[0]}"]`).addClass('selected-1');
            $('#slot-1').removeClass('selection-empty selection-active-2')
                       .addClass('selection-active-1')
                       .html(`<i class="bi bi-heart-pulse-fill"></i> <span>${capitalizeFirstLetter(selectedTissues[0])}</span>`);
        } else {
            $('#slot-1').removeClass('selection-active-1 selection-active-2')
                       .addClass('selection-empty')
                       .html(`<i class="bi bi-circle"></i> <span>None Selected</span>`);
        }

        // Render second selection
        if (selectedTissues[1]) {
            $(`.organ-node[data-tissue="${selectedTissues[1]}"]`).addClass('selected-2');
            $('#slot-2').removeClass('selection-empty selection-active-1')
                       .addClass('selection-active-2')
                       .html(`<i class="bi bi-heart-pulse-fill"></i> <span>${capitalizeFirstLetter(selectedTissues[1])}</span>`);
        } else {
            $('#slot-2').removeClass('selection-active-1 selection-active-2')
                       .addClass('selection-empty')
                       .html(`<i class="bi bi-circle"></i> <span>None Selected</span>`);
        }

        // Handle Table rendering logic when exactly two tissues are matched
        if (selectedTissues.length === 2) {
            $('#workspace-placeholder').addClass('d-none');
            $('#workspace-results').removeClass('d-none');
            renderCentralityData(selectedTissues[0], selectedTissues[1]);
        } else {
            $('#workspace-placeholder').removeClass('d-none');
            $('#workspace-results').addClass('d-none');
        }
    }

    /**
     * Determines key alignment, handles mock random generation fallbacks, and triggers DataTables
     */
    function renderCentralityData(t1, t2) {
        // Sort keys alphabetically to search in our data structure (e.g., 'brain-liver' rather than 'liver-brain')
        const sortedPair = [t1, t2].sort();
        const key = `${sortedPair[0]}-${sortedPair[1]}`;

        let results = [];

        if (dummyCentralityData[key]) {
            results = dummyCentralityData[key];
        } else {
            // Generates a mock, randomized dataset on the fly for unpopulated combinations
            const fallbackGenes = ['PER3', 'DEC1', 'DEC2', 'REV-ERBα', 'RORc', 'BHLHE40', 'TIMELESS', 'CRY1', 'BMAL2'];
            fallbackGenes.forEach(function(gene) {
                results.push({
                    gene: gene,
                    global_centrality_score: (Math.floor(Math.random() * 980) + 10) / 10000
                });
            });
            // Sort simulated scores high-to-low
            results.sort((a, b) => b.global_centrality_score - a.global_centrality_score);
        }

        // Destroy previous DataTable instance to prevent rendering conflicts
        if (tableInstance) {
            tableInstance.destroy();
        }

        // Rebuild table DOM body
        const $tableBody = $('#centralityTableBody');
        $tableBody.empty();

        results.forEach(function(row) {
            const tr = `
                <tr>
                    <td>
                        <a href="gene_details.php?gene=${encodeURIComponent(row.gene)}" class="fw-bold text-decoration-none text-primary">
                            <i class="bi bi-search small me-1"></i> ${escapeHtml(row.gene)}
                        </a>
                    </td>
                    <td class="font-monospace text-secondary fw-bold">${row.global_centrality_score.toFixed(6)}</td>
                </tr>
            `;
            $tableBody.append(tr);
        });

        // Initialize DataTables
        tableInstance = $('#centralityTable').DataTable({
            "order": [[1, "desc"]], // Default ordering high score to low
            "pageLength": 8,
            "lengthMenu": [5, 8, 15, 50],
            "language": {
                "search": "<i class='bi bi-funnel text-muted'></i> Filter Genes:"
            },
            "dom": "<'row mb-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });
    }

    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php include 'footer.php'; ?>