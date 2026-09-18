<?php
/**
 * Tissue Expression Explorer - Unified Portal Build
 * Focus: Single Gene Profile & Comparative Heatmap
 * Default Target: PER1
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'header.php';

// Support direct URL parameter (e.g. Cross_tissue.php?gene=PER2)
$target_gene = (isset($_GET['gene']) && trim($_GET['gene']) !== '') ? trim($_GET['gene']) : 'PER1';
?>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Reliable Plotly CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/plotly.js/2.24.2/plotly.min.js"></script>

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

    /* Custom Loading Overlay */
    .dashboard-view-wrapper {
        position: relative;
    }
    .dashboard-loader-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        display: none;
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

    /* Autocomplete dropdown styling */
    .search-wrapper { position: relative; width: 100%; max-width: 450px; }
    .search-results-overlay { 
        position: absolute; 
        top: 100%; 
        left: 0; 
        right: 0; 
        background: white; 
        border: 1px solid #ced4da; 
        border-radius: 6px; 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
        max-height: 250px; 
        overflow-y: auto; 
        z-index: 1000; 
        display: none; 
    }
    .search-item { 
        padding: 10px 16px; 
        cursor: pointer; 
        border-bottom: 1px solid #f1f5f9; 
        font-size: 13px; 
        display: flex; 
        justify-content: space-between; 
    }
    .search-item:hover { background-color: #f8f9fa; }
    .search-item .gene-name { font-weight: 700; color: #0d6efd; }
    .search-item .ensembl-id { font-family: monospace; font-size: 11px; color: #6c757d; }

    /* Heatmap tags */
    .heatmap-tags-container { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
    .gene-tag { 
        display: inline-flex; 
        align-items: center; 
        background-color: #e9ecef; 
        color: #212529; 
        border: 1px solid #ced4da; 
        padding: 5px 12px; 
        border-radius: 20px; 
        font-size: 12px; 
        font-weight: 600; 
        gap: 8px; 
    }
    .gene-tag button { 
        background: transparent; 
        border: none; 
        color: #dc3545; 
        font-weight: 700; 
        cursor: pointer; 
        outline: none; 
    }

    /* Toggle Buttons */
    .btn-toggle-group button {
        border-radius: 20px;
        padding: 5px 16px;
        font-size: 13px;
        font-weight: 600;
    }
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
            Tissue Expression Explorer
        </h1>

        <p style="
            font-family: 'Segoe UI', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #212529;
            line-height: 1.5;
            max-width: 900px;
            margin: 0 auto;">
            Explore tissue-specific gene expression profiles across 68 human tissues and compare multi-gene expression patterns.
        </p>
    </div>

    <!-- UTILITY CONTROL BAR WITH SEARCH & DATASET BADGE -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <span class="small fw-bold text-muted text-uppercase d-block">Active Target Gene</span>
                    <span id="currentGeneName" class="h4 fw-bold text-primary m-0"><?php echo htmlspecialchars($target_gene); ?></span>
                </div>
                <div class="col-md-6">
                    <div class="search-wrapper w-100">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="geneSearchInput" placeholder="Search Gene Symbol or Ensembl ID (e.g. PER1, CLOCK)..." class="form-control border-start-0">
                        </div>
                        <div id="geneSearchResults" class="search-results-overlay"></div>
                    </div>
                </div>
                <div class="col-md-3 text-md-end text-start">
                    <a href="gene_expression_catalog.php" class="btn btn-outline-primary btn-sm fw-semibold rounded-pill px-3">
                        <i class="bi bi-table me-1"></i> View Full Catalog
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT AREA -->
    <div class="dashboard-view-wrapper">
        
        <!-- Standardized Spinner Block -->
        <div id="dashboardLoader" class="dashboard-loader-overlay">
            <div class="custom-table-loader">
                <div class="custom-loader-spinner"></div>
                <span class="text-muted fw-bold">Loading Cross-Tissue Expression Profile...</span>
            </div>
        </div>

        <!-- ============== SECTION 1: SINGLE GENE WORKSPACE ============== -->
        <div class="card shadow-sm mb-4" id="genePanel">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Gene Expression Profile</h5>
                <div class="d-flex align-items-center gap-3">
                    <label class="form-check-label small fw-bold text-muted cursor-pointer mb-0">
                        <input type="checkbox" id="groupTissuesToggle" class="form-check-input me-1" checked onchange="toggleTissueAggregation()"> Group Similar Tissues (21 Organs)
                    </label>
                    <div class="btn-group btn-toggle-group">
                        <button id="btnRadarMode" class="btn btn-primary active btn-sm">Radar Plot</button>
                        <button id="btnBarMode" class="btn btn-outline-secondary btn-sm">Sorted Bars</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                
                <!-- TOP ROW: SUMMARY METRIC CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-primary mb-0" id="mTopTissue">-</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Top Tissue</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-danger mb-0" id="mMaxTpm">0.00</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Max Log2 Exp</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-warning mb-0" id="mTau">0.000</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Tau (&tau;)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-success mb-0" id="mGini">0.000</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Gini Index</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-info mb-0" id="mZmax">0.00</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Z Max</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card shadow-sm text-center">
                            <div class="card-body p-3">
                                <h4 class="fw-bold text-secondary mb-0" id="mSpecificity">-</h4>
                                <div class="text-muted small fw-bold text-uppercase mt-1">Specificity</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Single Gene Graph -->
                <div id="geneBarChart" style="height: 440px; min-height: 440px; width: 100%; display:none;"></div>
                <div id="geneRadarChart" style="height: 440px; min-height: 440px; width: 100%;"></div>
            </div>
        </div>

        <!-- ============== SECTION 2: COMPARATIVE MATRIX ============== -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Comparative Expression Heatmap</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-3">
                        <div class="p-3 bg-light border rounded">
                            <h6 class="fw-bold text-uppercase small text-muted mb-2">Compare Genes</h6>
                            <div class="search-wrapper mb-3">
                                <input type="text" id="heatmapSearchInput" placeholder="Add gene symbol..." class="form-control form-control-sm">
                                <div id="heatmapSearchResults" class="search-results-overlay"></div>
                            </div>
                            <div id="heatmapTagsContainer" class="heatmap-tags-container"></div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div id="heatmapChart" style="height: 450px; min-height: 450px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// BIOLOGICALLY REALIGNED ANATOMICAL MAPPING
const TISSUE_ORGAN_GROUPS = {
    "Adipose": ["Adipose_Subcutaneous", "Adipose_Visceral_Omentum"],
    "Endocrine Glands": ["Thyroid", "Pituitary", "Adrenal_Gland", "Minor_Salivary_Gland"],
    "Whole Blood": ["Whole_Blood"],
    "Lymphoid / Immune": ["Spleen"],
    "Vessels": ["Artery_Aorta", "Artery_Coronary", "Artery_Tibial"],
    "Nervous System": [
        "Brain_Amygdala", "Brain_Anterior_cingulate_cortex_BA24", "Brain_Caudate_basal_ganglia",
        "Brain_Cerebellar_Hemisphere", "Brain_Cerebellum", "Brain_Cortex",
        "Brain_Frontal_Cortex_BA9", "Brain_Hippocampus", "Brain_Hypothalamus",
        "Brain_Nucleus_accumbens_basal_ganglia", "Brain_Putamen_basal_ganglia",
        "Brain_Spinal_cord_cervical_c_1", "Brain_Substantia_nigra", "Nerve_Tibial"
    ],
    "Breast": ["Breast_Mammary_Tissue"],
    "Female Reproductive (Lower)": ["Cervix_Ectocervix", "Cervix_Endocervix", "Vagina"],
    "Female Reproductive (Upper)": ["Ovary", "Uterus", "Fallopian_Tube"],
    "Colon": ["Colon_Sigmoid", "Colon_Transverse", "Colon_Transverse_Mixed_Cell", "Colon_Transverse_Mucosa", "Colon_Transverse_Muscularis"],
    "Esophagus": ["Esophagus_Gastroesophageal_Junction", "Esophagus_Mucosa", "Esophagus_Muscularis"],
    "Heart": ["Heart_Atrial_Appendage", "Heart_Left_Ventricle"],
    "Kidney & Urinary": ["Kidney_Cortex", "Kidney_Medulla", "Bladder"],
    "Liver": ["Liver", "Liver_Hepatocyte", "Liver_Mixed_Cell", "Liver_Portal_Tract"],
    "Muscle": ["Muscle_Skeletal"],
    "Pancreas": ["Pancreas", "Pancreas_Acini", "Pancreas_Islets", "Pancreas_Mixed_Cell"],
    "Respiratory": ["Lung"],
    "Skin": ["Skin_Not_Sun_Exposed_Suprapubic", "Skin_Sun_Exposed_Lower_leg"],
    "Small Intestine": ["Small_Intestine_Terminal_Ileum", "Small_Intestine_Terminal_Ileum_Lymphoid_Aggregate", "Small_Intestine_Terminal_Ileum_Mixed_Cell"],
    "Stomach": ["Stomach", "Stomach_Mixed_Cell", "Stomach_Mucosa", "Stomach_Muscularis"],
    "Male Reproductive": ["Testis", "Prostate"]
};

const IN_VITRO_BLACKLIST = ["Cells_Cultured_fibroblasts", "Cells_EBV_transformed_lymphocytes", "Whole_Blood"];

let activeGeneId = "<?php echo addslashes($target_gene); ?>";
let activeGeneData = null; 
let currentHeatmapGenes = ['PER1', 'PER2', 'CRY1', 'CLOCK'];
let currentChartMode = 'radar';
const dashboardLoader = document.getElementById('dashboardLoader');

$(document).ready(function () {
    setupSearchAutocomplete();
    renderHeatmapTags();
    loadTargetGene(activeGeneId);
    renderHeatmap();
});

function setupSearchAutocomplete() {
    setupAutocompleteInput('geneSearchInput', 'geneSearchResults', 'single');
    setupAutocompleteInput('heatmapSearchInput', 'heatmapSearchResults', 'heatmap');
}

function setupAutocompleteInput(inputId, overlayId, type) {
    const input = document.getElementById(inputId);
    const overlay = document.getElementById(overlayId);
    let timeout = null;

    input.addEventListener('input', function(e) {
        const query = e.target.value.trim().toUpperCase();
        clearTimeout(timeout);
        if (query.length < 1) {
            overlay.style.display = 'none';
            return;
        }
        timeout = setTimeout(async () => {
            try {
                const req = await fetch(`get_data.php?action=suggest_genes&q=${encodeURIComponent(query)}`);
                const results = await req.json();
                renderSuggestions(results, overlay, type);
            } catch (e) {
                console.error("Autocomplete error: ", e);
            }
        }, 150);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest(`#${inputId}`)) {
            overlay.style.display = 'none';
        }
    });
}

function renderSuggestions(results, overlay, type) {
    overlay.innerHTML = "";
    if (!results || results.length === 0) {
        overlay.style.display = 'none';
        return;
    }
    results.forEach(g => {
        const div = document.createElement("div");
        div.className = "search-item";
        const symbol = g.Gene_Name || g.Approved_symbol || g.Gene_ID;
        div.innerHTML = `<span class="gene-name">${symbol}</span><span class="ensembl-id">${g.Gene_ID}</span>`;
        div.onclick = () => {
            if (type === 'single') {
                activeGeneId = g.Gene_ID || symbol;
                loadTargetGene(activeGeneId);
                document.getElementById('geneSearchInput').value = "";
            } else {
                addGeneToHeatmap(symbol);
                document.getElementById('heatmapSearchInput').value = "";
            }
            overlay.style.display = 'none';
        };
        overlay.appendChild(div);
    });
    overlay.style.display = 'block';
}

function addGeneToHeatmap(symbol) {
    if (!currentHeatmapGenes.includes(symbol)) {
        currentHeatmapGenes.push(symbol);
        renderHeatmapTags();
        renderHeatmap();
    }
}

function removeGeneFromHeatmap(symbol) {
    currentHeatmapGenes = currentHeatmapGenes.filter(g => g !== symbol);
    renderHeatmapTags();
    renderHeatmap();
}

function renderHeatmapTags() {
    const container = document.getElementById("heatmapTagsContainer");
    if (!container) return;
    container.innerHTML = "";
    currentHeatmapGenes.forEach(symbol => {
        const tag = document.createElement("span");
        tag.className = "gene-tag";
        tag.innerHTML = `<span>${symbol}</span><button onclick="removeGeneFromHeatmap('${symbol}')"><i class="bi bi-x-circle"></i></button>`;
        container.appendChild(tag);
    });
}

async function loadTargetGene(geneId) {
    try {
        dashboardLoader.style.display = 'flex';
        const req = await fetch(`get_data.php?action=get_gene_details&gene_id=${encodeURIComponent(geneId)}`);
        const res = await req.json();
        dashboardLoader.style.display = 'none';

        if (res.success) {
            activeGeneData = res;
            renderGenePanel(res);
        } else {
            console.error("Gene lookup notice: ", res.message);
        }
    } catch (e) {
        dashboardLoader.style.display = 'none';
        console.error("Target lookup failed: ", e);
    }
}

function renderGenePanel(g) {
    const symbol = g.gene_name || g.approved_symbol || g.gene_id || '';
    const top_tissue = g.top_tissue || '';
    const max_tpm = parseFloat(g.max_tpm || 0.0);
    const tau = parseFloat(g.tau || 0.0);
    const gini = parseFloat(g.gini || 0.0);
    const z_max = parseFloat(g.z_max || 0.0);
    const specificity = g.specificity || 'Intermediate';

    document.getElementById('currentGeneName').textContent = symbol;
    document.getElementById('mTopTissue').textContent = top_tissue ? top_tissue.replace(/_/g, " ") : '-';
    document.getElementById('mMaxTpm').textContent = max_tpm.toFixed(2);
    document.getElementById('mTau').textContent = tau.toFixed(3);
    document.getElementById('mGini').textContent = gini.toFixed(3);
    document.getElementById('mZmax').textContent = z_max.toFixed(2);
    document.getElementById('mSpecificity').textContent = specificity;

    drawGenePlots();
}

function getActiveTissueProfile() {
    if (!activeGeneData || !activeGeneData.tissues) return [];
    
    const inVivoBase = activeGeneData.tissues.filter(t => !IN_VITRO_BLACKLIST.includes(t.field));
    
    if (!document.getElementById("groupTissuesToggle").checked) return inVivoBase;

    const groupedResults = [];
    for (const [groupName, members] of Object.entries(TISSUE_ORGAN_GROUPS)) {
        const matches = inVivoBase.filter(t => members.includes(t.field));
        if (matches.length > 0) {
            const values = matches.map(t => parseFloat(t.tpm));
            groupedResults.push({
                label: groupName,
                field: groupName,
                tpm: values.reduce((sum, v) => sum + v, 0) / values.length
            });
        }
    }
    return groupedResults.sort((a, b) => b.tpm - a.tpm);
}

function toggleTissueAggregation() {
    drawGenePlots();
}

function drawGenePlots() {
    const tissues = getActiveTissueProfile();
    if (!tissues || tissues.length === 0) return;

    if (currentChartMode === 'bar') {
        document.getElementById('geneRadarChart').style.display = 'none';
        const el = document.getElementById('geneBarChart');
        el.style.display = 'block';

        const labels = tissues.map(t => t.label).slice().reverse();
        const values = tissues.map(t => t.tpm).slice().reverse();
        const maxVal = Math.max(...values);
        const colors = values.map(v => Math.abs(v - maxVal) < 0.001 ? '#dc3545' : '#0d6efd');
        
        Plotly.newPlot(el, [{
            type: 'bar', orientation: 'h', x: values, y: labels,
            marker: { color: colors, borderRadius: 4 },
            hovertemplate: '%{y}: %{x:.2f}<extra></extra>',
        }], {
            margin: { l: 200, r: 20, t: 10, b: 40 },
            xaxis: { title: 'Log2 Expression Level', gridcolor: '#f1f5f9' },
            yaxis: { gridcolor: '#f1f5f9' },
            font: { family: 'Segoe UI, sans-serif', size: 12 },
            height: 440, 
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)'
        }, { displayModeBar: false, responsive: true });
    } else {
        document.getElementById('geneBarChart').style.display = 'none';
        const el = document.getElementById('geneRadarChart');
        el.style.display = 'block';

        const labels = tissues.map(t => t.label);
        const values = tissues.map(t => t.tpm);
        
        Plotly.newPlot(el, [{
            type: 'scatterpolar', 
            r: [...values, values[0]], 
            theta: [...labels, labels[0]],
            fill: 'toself', 
            fillcolor: 'rgba(13, 110, 253, 0.15)', 
            line: { color: '#0d6efd', width: 2 },
            mode: 'lines+markers',
            marker: { size: 6, color: '#0d6efd' },
            hovertemplate: '%{theta}: %{r:.2f}<extra></extra>'
        }], {
            polar: { radialaxis: { visible: true, showline: false } }, showlegend: false,
            margin: { l: 80, r: 80, t: 30, b: 30 },
            font: { family: 'Segoe UI, sans-serif', size: 10 },
            height: 440, 
            paper_bgcolor: 'rgba(0,0,0,0)'
        }, { displayModeBar: false, responsive: true });
    }
}

async function renderHeatmap() {
    if (currentHeatmapGenes.length === 0) {
        Plotly.purge('heatmapChart');
        return;
    }

    try {
        const req = await fetch(`get_data.php?action=get_heatmap&genes=${encodeURIComponent(currentHeatmapGenes.join(','))}`);
        const res = await req.json();
        
        if (res.success && res.matrix.length > 0) {
            const cleanTissuesIndices = [];
            const labelsTissues = res.tissues.filter((t, idx) => {
                const keep = !IN_VITRO_BLACKLIST.includes(t);
                if (keep) cleanTissuesIndices.push(idx);
                return keep;
            }).map(t => t.replace(/_/g, " "));

            const labelsGenes = res.matrix.map(g => g.gene_name);
            const rawMatrix = res.matrix.map(g => {
                return g.raw.filter((_, idx) => cleanTissuesIndices.includes(idx));
            });

            Plotly.newPlot('heatmapChart', [{
                type: 'heatmap', 
                z: rawMatrix, 
                x: labelsTissues, 
                y: labelsGenes,
                colorscale: 'YlGnBu',
                colorbar: { title: 'Log2 Exp' },
                hovertemplate: 'Gene: %{y}<br>Tissue: %{x}<br>Expression: %{z:.2f}<extra></extra>'
            }], {
                margin: { l: 110, r: 20, t: 10, b: 100 },
                xaxis: { tickangle: -45, automargin: true },
                yaxis: { automargin: true },
                font: { family: 'Segoe UI, sans-serif', size: 12 },
                height: 450, 
                paper_bgcolor: 'rgba(0,0,0,0)',
                plot_bgcolor: 'rgba(0,0,0,0)'
            }, { displayModeBar: false, responsive: true });
        }
    } catch (e) {
        console.error("Heatmap loading error: ", e);
    }
}

document.getElementById('btnBarMode').addEventListener('click', () => {
    currentChartMode = 'bar';
    document.getElementById('btnBarMode').classList.add('active', 'btn-primary');
    document.getElementById('btnBarMode').classList.remove('btn-outline-secondary');
    document.getElementById('btnRadarMode').classList.remove('active', 'btn-primary');
    document.getElementById('btnRadarMode').classList.add('btn-outline-secondary');
    drawGenePlots();
});

document.getElementById('btnRadarMode').addEventListener('click', () => {
    currentChartMode = 'radar';
    document.getElementById('btnRadarMode').classList.add('active', 'btn-primary');
    document.getElementById('btnRadarMode').classList.remove('btn-outline-secondary');
    document.getElementById('btnBarMode').classList.remove('active', 'btn-primary');
    document.getElementById('btnBarMode').classList.add('btn-outline-secondary');
    drawGenePlots();
});
</script>

<?php
if (file_exists('footer.php')) {
    include 'footer.php';
}
?>