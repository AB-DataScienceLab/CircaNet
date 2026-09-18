<?php
// require_once 'conn.php'; // Commented out for now
include 'header.php';

// 1. Structural categorization mapping all 68 tissues into 11 biological systems
$categories = [
    'brain' => [
        'label' => 'Brain & Nervous System',
        'icon' => 'bi-headset', // Fallback icon representation
        'tissues' => [
            "Brain_Amygdala", "Brain_Anterior_cingulate_cortex_BA24", "Brain_Caudate_basal_ganglia",
            "Brain_Cerebellar_Hemisphere", "Brain_Cerebellum", "Brain_Cortex", "Brain_Frontal_Cortex_BA9",
            "Brain_Hippocampus", "Brain_Hypothalamus", "Brain_Nucleus_accumbens_basal_ganglia",
            "Brain_Putamen_basal_ganglia", "Brain_Spinal_cord_cervical_c-1", "Brain_Substantia_nigra",
            "Nerve_Tibial"
        ]
    ],
    'cardiovascular' => [
        'label' => 'Cardiovascular System',
        'icon' => 'bi-heart-pulse',
        'tissues' => [
            "Artery_Aorta", "Artery_Coronary", "Artery_Tibial", "Heart_Atrial_Appendage", "Heart_Left_Ventricle"
        ]
    ],
    'digestive' => [
        'label' => 'Digestive Tract',
        'icon' => 'bi-layers',
        'tissues' => [
            "Colon_Sigmoid", "Colon_Transverse", "Colon_Transverse_Mixed_Cell", "Colon_Transverse_Mucosa",
            "Colon_Transverse_Muscularis", "Esophagus_Gastroesophageal_Junction", "Esophagus_Mucosa",
            "Esophagus_Muscularis", "Small_Intestine_Terminal_Ileum", "Small_Intestine_Terminal_Ileum_Mixed_Cell",
            "Stomach", "Stomach_Mixed_Cell", "Stomach_Mucosa", "Stomach_Muscularis"
        ]
    ],
    'liver_pancreas' => [
        'label' => 'Liver & Pancreas',
        'icon' => 'bi-capsule',
        'tissues' => [
            "Liver", "Liver_Hepatocyte", "Liver_Mixed_Cell", "Liver_Portal_Tract",
            "Pancreas", "Pancreas_Acini", "Pancreas_Islets", "Pancreas_Mixed_Cell"
        ]
    ],
    'urinary' => [
        'label' => 'Urinary System',
        'icon' => 'bi-shield-shaded',
        'tissues' => [
            "Kidney_Cortex", "Kidney_Medulla", "Bladder"
        ]
    ],
    'endocrine' => [
        'label' => 'Endocrine Glands',
        'icon' => 'bi-activity',
        'tissues' => [
            "Adrenal_Gland", "Pituitary", "Thyroid"
        ]
    ],
    'reproductive' => [
        'label' => 'Reproductive Organs',
        'icon' => 'bi-gender-ambiguous',
        'tissues' => [
            "Cervix_Ectocervix", "Cervix_Endocervix", "Fallopian_Tube", "Ovary", "Uterus", "Vagina",
            "Prostate", "Testis"
        ]
    ],
    'respiratory' => [
        'label' => 'Respiratory System',
        'icon' => 'bi-wind',
        'tissues' => [
            "Lung"
        ]
    ],
    'blood_immune' => [
        'label' => 'Blood & Immune System',
        'icon' => 'bi-droplet-half',
        'tissues' => [
            "Spleen", "Whole_Blood", "Cells_EBV-transformed_lymphocytes", "Small_Intestine_Terminal_Ileum_Lymphoid_Aggregate"
        ]
    ],
    'musculoskeletal' => [
        'label' => 'Musculoskeletal System',
        'icon' => 'bi-person-arms-up',
        'tissues' => [
            "Muscle_Skeletal"
        ]
    ],
    'adipose_skin' => [
        'label' => 'Adipose & Skin',
        'icon' => 'bi-grid-3x3-gap',
        'tissues' => [
            "Adipose_Subcutaneous", "Adipose_Visceral_Omentum", "Skin_Not_Sun_Exposed_Suprapubic", "Skin_Sun_Exposed_Lower_leg"
        ]
    ],
    'other' => [
        'label' => 'Connective & Others',
        'icon' => 'bi-three-dots',
        'tissues' => [
            "Breast_Mammary_Tissue", "Cells_Cultured_fibroblasts", "Minor_Salivary_Gland"
        ]
    ]
];
?>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    .dashboard-container {
        margin-top: 40px;
        margin-bottom: 60px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .panel-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    
    /* Interactive vector anatomy styling */
    .anatomy-svg-wrapper {
        background-color: #f8fafc;
        border-radius: 8px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .anatomy-svg {
        max-height: 400px;
        width: 100%;
    }
    .silhouette {
        fill: #f1f5f9;
        stroke: #cbd5e1;
        stroke-width: 1.5;
    }
    
    /* Hotspot Target Nodes */
    .blueprint-node {
        cursor: pointer;
    }
    .node-outer-ring {
        fill: rgba(148, 163, 184, 0.2);
        stroke: #94a3b8;
        stroke-width: 1;
        transition: all 0.25s ease;
    }
    .node-inner-dot {
        fill: #64748b;
        transition: all 0.25s ease;
    }
    .blueprint-node:hover .node-outer-ring {
        fill: rgba(13, 148, 136, 0.2);
        stroke: #0d9488;
        r: 10;
    }
    .blueprint-node:hover .node-inner-dot {
        fill: #0d9488;
    }

    /* Active categorization markers */
    .blueprint-node.active .node-outer-ring {
        fill: rgba(13, 148, 136, 0.3) !important;
        stroke: #0d9488 !important;
        stroke-width: 1.5;
        r: 11;
    }
    .blueprint-node.active .node-inner-dot {
        fill: #0d9488 !important;
    }

    .blueprint-pointer {
        stroke: #cbd5e1;
        stroke-dasharray: 2 2;
        stroke-width: 1;
    }
    .blueprint-text {
        font-size: 8px;
        font-weight: 700;
        fill: #64748b;
        user-select: none;
    }

    /* Sidebar category list */
    .category-list-group .list-group-item {
        border: 1px solid #f1f5f9;
        border-radius: 6px;
        margin-bottom: 6px;
        transition: all 0.2s ease;
        font-size: 14px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
    }
    .category-list-group .list-group-item:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
    }
    .category-list-group .list-group-item.active {
        background-color: #0f766e !important; /* Deep Teal */
        color: #ffffff !important;
        border-color: #0f766e !important;
    }

    /* Heatmap cell styling */
    .heatmap-cell {
        text-align: center;
        font-weight: 600;
        font-size: 12px;
        transition: background-color 0.2s ease;
        cursor: default;
    }
    .expression-header-cell {
        font-size: 11px !important;
        white-space: nowrap;
        text-transform: capitalize;
    }
</style>

<div class="container dashboard-container">
    <div class="row mb-4">
        <div class="col-12">
            <span class="badge bg-teal mb-2" style="background-color: #0f766e;">Gene Expression Hub</span>
            <h2 class="fw-bold text-slate-800">Dynamic Multi-Tissue Expression Profiling</h2>
            <p class="text-muted">Explore structural circadian and housekeeping gene expression profiles across anatomical system divisions.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT PANEL: Multi-System Selector (Mannequin & Category List) -->
        <div class="col-lg-4 col-md-12">
            <div class="card panel-card p-3 h-100 d-flex flex-column">
                <h6 class="fw-bold text-slate-700 mb-3">
                    <i class="bi bi-body-text text-teal me-2" style="color: #0f766e;"></i> Anatomical Selection
                </h6>
                
                <div class="anatomy-svg-wrapper mb-3">
                    <svg class="anatomy-svg" viewBox="0 0 200 380" xmlns="http://www.w3.org/2000/svg">
                        <!-- Upper body mannequin outline -->
                        <path class="silhouette" d="M 100,20 C 112,20 120,28 120,40 C 120,52 112,60 100,60 C 88,60 80,52 80,40 C 80,28 88,20 100,20 Z M 60,105 C 70,95 85,90 100,90 C 115,90 130,95 140,105 C 145,115 145,130 145,145 C 145,165 140,185 135,230 C 132,260 130,290 130,350 L 70,350 C 70,290 68,260 65,230 C 60,185 55,165 55,145 C 55,130 55,115 60,105 Z" />

                        <!-- Interactive Nodes mapped to anatomical locations -->
                        <g class="blueprint-node" data-category="brain" id="node-brain">
                            <line x1="100" y1="40" x2="160" y2="40" class="blueprint-pointer" />
                            <text x="163" y="43" class="blueprint-text">BRAIN & NERVES</text>
                            <circle class="node-outer-ring" cx="100" cy="40" r="7" />
                            <circle class="node-inner-dot" cx="100" cy="40" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="endocrine" id="node-endocrine">
                            <line x1="100" y1="75" x2="35" y2="75" class="blueprint-pointer" />
                            <text x="5" y="78" class="blueprint-text">ENDOCRINE</text>
                            <circle class="node-outer-ring" cx="100" cy="75" r="7" />
                            <circle class="node-inner-dot" cx="100" cy="75" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="respiratory" id="node-respiratory">
                            <line x1="88" y1="125" x2="35" y2="125" class="blueprint-pointer" />
                            <text x="5" y="128" class="blueprint-text">RESPIRATORY</text>
                            <circle class="node-outer-ring" cx="88" cy="125" r="7" />
                            <circle class="node-inner-dot" cx="88" cy="125" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="cardiovascular" id="node-cardiovascular">
                            <line x1="105" y1="130" x2="160" y2="130" class="blueprint-pointer" />
                            <text x="163" y="133" class="blueprint-text">CARDIOVASCULAR</text>
                            <circle class="node-outer-ring" cx="105" cy="130" r="7" />
                            <circle class="node-inner-dot" cx="105" cy="130" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="liver_pancreas" id="node-liver_pancreas">
                            <line x1="85" y1="165" x2="35" y2="165" class="blueprint-pointer" />
                            <text x="5" y="168" class="blueprint-text">LIVER & PANCREAS</text>
                            <circle class="node-outer-ring" cx="85" cy="165" r="7" />
                            <circle class="node-inner-dot" cx="85" cy="165" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="digestive" id="node-digestive">
                            <line x1="100" y1="180" x2="160" y2="180" class="blueprint-pointer" />
                            <text x="163" y="183" class="blueprint-text">DIGESTIVE TRACT</text>
                            <circle class="node-outer-ring" cx="100" cy="180" r="7" />
                            <circle class="node-inner-dot" cx="100" cy="180" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="urinary" id="node-urinary">
                            <line x1="100" y1="220" x2="35" y2="220" class="blueprint-pointer" />
                            <text x="5" y="223" class="blueprint-text">URINARY SYSTEM</text>
                            <circle class="node-outer-ring" cx="100" cy="220" r="7" />
                            <circle class="node-inner-dot" cx="100" cy="220" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="reproductive" id="node-reproductive">
                            <line x1="100" y1="270" x2="160" y2="270" class="blueprint-pointer" />
                            <text x="163" y="273" class="blueprint-text">REPRODUCTIVE</text>
                            <circle class="node-outer-ring" cx="100" cy="270" r="7" />
                            <circle class="node-inner-dot" cx="100" cy="270" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="blood_immune" id="node-blood_immune">
                            <line x1="122" y1="110" x2="160" y2="110" class="blueprint-pointer" />
                            <text x="163" y="113" class="blueprint-text">BLOOD & IMMUNE</text>
                            <circle class="node-outer-ring" cx="122" cy="110" r="7" />
                            <circle class="node-inner-dot" cx="122" cy="110" r="3" />
                        </g>

                        <g class="blueprint-node" data-category="musculoskeletal" id="node-musculoskeletal">
                            <line x1="75" y1="310" x2="35" y2="310" class="blueprint-pointer" />
                            <text x="5" y="313" class="blueprint-text">MUSCLE & SKELETAL</text>
                            <circle class="node-outer-ring" cx="75" cy="310" r="7" />
                            <circle class="node-inner-dot" cx="75" cy="310" r="3" />
                        </g>
                    </svg>
                </div>

                <!-- Text-based list navigation fallback -->
                <div class="list-group category-list-group flex-grow-1 overflow-auto" style="max-height: 250px;">
                    <?php foreach ($categories as $key => $cat): ?>
                        <div class="list-group-item d-flex align-items-center gap-2" data-category="<?php echo $key; ?>">
                            <i class="bi <?php echo $cat['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($cat['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Gene Expression Heatmap Matrix -->
        <div class="col-lg-8 col-md-12">
            <div class="card panel-card p-4 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-slate-700 m-0">
                        <i class="bi bi-grid-3x3 text-teal me-2" style="color: #0f766e;"></i> Expression Heatmap
                    </h5>
                    <span id="active-category-badge" class="badge p-2" style="background-color: #0f766e; font-size: 12px;">No Selection</span>
                </div>

                <!-- Initial Placeholder -->
                <div id="heatmap-placeholder" class="my-auto text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-layout-three-columns" style="font-size: 3rem; color: #cbd5e1;"></i>
                    </div>
                    <h6 class="fw-bold text-slate-700">Select a tissue system to populate matrix</h6>
                    <p class="text-muted small px-5">Click on the blueprint targets or selection categories list to inspect expression scores (TPM) mapped as rows (genes) and columns (associated sub-tissues).</p>
                </div>

                <!-- Live Matrix Interface -->
                <div id="heatmap-matrix-container" class="d-none flex-grow-1">
                    <div class="table-responsive">
                        <table id="expressionMatrixTable" class="table table-bordered" style="width:100%">
                            <thead id="matrixHead">
                                <!-- Appended Dynamically -->
                            </thead>
                            <tbody id="matrixBody">
                                <!-- Appended Dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Deliver PHP configuration structure safely to Javascript
    const categoriesMapping = <?php echo json_encode($categories); ?>;
    
    // Circadian and housekeeping reference mock genes
    const referenceGenes = [
        { name: 'CLOCK', baseExp: 15 },
        { name: 'BMAL1', baseExp: 22 },
        { name: 'PER1', baseExp: 10 },
        { name: 'PER2', baseExp: 12 },
        { name: 'CRY1', baseExp: 18 },
        { name: 'CRY2', baseExp: 14 },
        { name: 'NPAS2', baseExp: 5 },
        { name: 'GAPDH', baseExp: 180 }, // High expression housekeeping control
        { name: 'ACTB', baseExp: 210 }    // High expression housekeeping control
    ];

    let dataTableInstance = null;
    let selectedCategory = null;

    $(document).ready(function() {
        // Universal Selection Router (blueprint or text list click)
        $('[data-category]').on('click', function() {
            const category = $(this).attr('data-category');
            selectAnatomicalCategory(category);
        });
    });

    /**
     * Updates navigation UI visual indicators and starts rendering matrix
     */
    function selectAnatomicalCategory(categoryKey) {
        selectedCategory = categoryKey;

        // Synchronize list visual selected states
        $('.category-list-group .list-group-item').removeClass('active');
        $(`.category-list-group .list-group-item[data-category="${categoryKey}"]`).addClass('active');

        // Synchronize vector nodes visual selected states
        $('.blueprint-node').removeClass('active');
        $(`#node-${categoryKey}`).addClass('active');

        // Update system labeling
        const systemLabel = categoriesMapping[categoryKey].label;
        $('#active-category-badge').text(systemLabel);

        // Render Matrix Table
        $('#heatmap-placeholder').addClass('d-none');
        $('#heatmap-matrix-container').removeClass('d-none');
        renderExpressionMatrix(categoryKey);
    }

    /**
     * Dynamically generates table columns and creates heatmap-shaded expressions
     */
    function renderExpressionMatrix(categoryKey) {
        const subTissues = categoriesMapping[categoryKey].tissues;

        if (dataTableInstance) {
            dataTableInstance.destroy();
        }

        // 1. Rebuild Headings Row
        const $matrixHead = $('#matrixHead');
        $matrixHead.empty();
        
        let headerRow = '<tr><th class="text-slate-700">Gene</th>';
        subTissues.forEach(function(tissue) {
            // Clean up name mapping strings for display
            const cleanName = tissue.replace(/_/g, ' ').replace('Brain ', '').replace('Liver ', '').replace('Pancreas ', '').replace('Kidney ', '');
            headerRow += `<th class="expression-header-cell text-muted text-center" title="${tissue}">${cleanName}</th>`;
        });
        headerRow += '</tr>';
        $matrixHead.append(headerRow);

        // 2. Rebuild Matrix Cell Values
        const $matrixBody = $('#matrixBody');
        $matrixBody.empty();

        referenceGenes.forEach(function(geneControl) {
            let rowHtml = `<tr><td class="fw-bold text-slate-800">${geneControl.name}</td>`;
            
            subTissues.forEach(function(tissue) {
                // Mock-up dynamic TPM values with biological system variation
                const variationFactor = 0.5 + (Math.random() * 0.9); 
                let expressionTPM = (geneControl.baseExp * variationFactor).toFixed(1);
                
                // Tissue-specific expression rules to mimic genuine biology
                if (geneControl.name === 'NPAS2' && !tissue.startsWith('Brain')) {
                    expressionTPM = (expressionTPM / 6).toFixed(1); // NPAS2 is primarily brain-enriched
                }

                // Shading parameters
                const cellBgColor = calculateHeatmapColor(expressionTPM);

                rowHtml += `
                    <td class="heatmap-cell" style="background-color: ${cellBgColor};" data-val="${expressionTPM}">
                        ${expressionTPM}
                    </td>
                `;
            });
            rowHtml += '</tr>';
            $matrixBody.append(rowHtml);
        });

        // 3. Initiate DataTables
        dataTableInstance = $('#expressionMatrixTable').DataTable({
            "paging": false,
            "info": false,
            "searching": true,
            "scrollX": true,
            "language": {
                "search": "<i class='bi bi-search text-muted'></i> Filter Genes:"
            }
        });
    }

    /**
     * Determines the CSS intensity value to construct GTEx/HPA styled heatmap grids
     */
    function calculateHeatmapColor(tpmValue) {
        const val = parseFloat(tpmValue);
        // Normalize against high housekeeping baseline bounds (~250 TPM)
        const scaleLimit = 250;
        let opacity = Math.min(val / scaleLimit, 0.85);
        if (opacity < 0.04) opacity = 0.04; // preserve a light minimal baseline grid tint

        // Premium Soft Teal Scale
        return `rgba(13, 148, 136, ${opacity})`; 
    }
</script>

<?php include 'footer.php'; ?>