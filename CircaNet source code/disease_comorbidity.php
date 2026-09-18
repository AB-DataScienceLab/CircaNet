<?php
// disease_comorbidity.php
require_once 'conn.php';
include 'header.php';
?>

<!-- Vis.js Library for Network Visualization -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css" />

<style>
    /* Little padding at top above hero banner */
    main.container {
        margin-top: 15px !important;
        padding-top: 5px !important;
    }

    /* --- PAGE STYLES --- */
    .network-box { height: 500px; width: 100%; border: 1px solid #dee2e6; border-radius: 6px; background-color: #fafafa; }
    .tab-content { padding: 20px 0; }
    .compare-box { border-left: 3px solid #0d6efd; padding-left: 15px; margin-bottom: 20px; }
    .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }

    /* HERO & SEARCH COMPONENT (VIBRANT BLUE GRADIENT) */
    .hero-section {
        background: linear-gradient(135deg, #10428d 0%, #1e40af 100%);
        border-radius: 12px; 
        padding: 30px; 
        color: white;
        display: flex; 
        justify-content: space-between; 
        align-items: flex-start;
        margin-top: 0; 
        margin-bottom: 25px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        flex-wrap: wrap; 
        gap: 20px;
    }
    .hero-text { flex: 1; min-width: 300px; padding-right: 20px; }
    .hero-text h1 { 
        margin: 0; 
        font-size: 2.2rem; 
        font-weight: 700; 
        color: white; 
        line-height: 1.2; 
    }
    .hero-text .description { 
        display: block; 
        margin-top: 8px; 
        opacity: 0.9; 
        font-weight: 300; 
        font-size: 0.95rem; 
        color: #e0e7ff; 
    }

    /* Search Wrapper Aligned Right */
    .search-wrapper-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
    }
    .search-container { 
        position: relative; 
        margin-top: 0; 
        display: flex; 
    }
    .search-input {
        padding: 12px 20px 12px 45px; 
        border-radius: 50px; 
        border: none;
        width: 280px; 
        font-size: 14px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
        transition: background 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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

    /* Auto-suggest dropdown categories */
    .suggestion-box {
        position: absolute; top: 105%; left: 0; width: 330px; 
        background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px;
        z-index: 1050; max-height: 320px; overflow-y: auto; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none; margin-top: 5px;
    }
    .suggestion-category {
        background: #f8fafc; padding: 6px 15px; font-size: 11px; font-weight: 700;
        text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0;
    }
    .suggestion-item {
        padding: 10px 18px; cursor: pointer; color: #334155; 
        border-bottom: 1px solid #f1f5f9; text-align: left; 
        font-weight: 500; font-size: 13px; transition: background 0.2s;
        display: flex; align-items: center; gap: 10px;
    }
    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover { background-color: #f0f4ff; color: #1e40af; }
</style>

<!-- Unified Search Hero Banner Component -->
<div class="hero-section shadow-sm">
    <div class="hero-text">
        <h1>Disease &amp; Comorbidity Explorer</h1>
        <span class="description">Explore disease comorbidity networks, compare diseases and genes through shared associations, and browse curated gene-disease, disease-disease, and network centrality datasets.</span>
    </div>

    <div class="search-wrapper-right">
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="unifiedSearchInput" class="search-input" 
                   placeholder="Search Gene symbol or Disease..." autocomplete="off">
            <button type="button" class="search-btn" id="unifiedSearchBtn">Search</button>
            <div id="unifiedSuggestionBox" class="suggestion-box"></div>
        </div>
        
        <!-- Pill-shaped Watch Video Tutorial Button aligned on the right -->
        <div class="mt-2">
            <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Comorbidity.mp4" target="_blank" class="video-tutorial-hero-btn">
                <i class="fas fa-video me-1"></i> Watch Video Tutorial
            </a>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-pills nav-fill mb-4 bg-white p-2 rounded shadow-sm" id="mainTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab"><i class="fas fa-project-diagram"></i> Network Analysis</button>
    </li>
     <li class="nav-item">
        <button class="nav-link" id="tables-tab" data-bs-toggle="tab" data-bs-target="#tables" type="button" role="tab"><i class="fas fa-table"></i> Datasets</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="compare-tab" data-bs-toggle="tab" data-bs-target="#compare" type="button" role="tab"><i class="fas fa-balance-scale"></i> Comparison Tool</button>
    </li>
</ul>

<div class="tab-content" id="mainTabContent">
    
    <!-- Tab 1: Network Analysis Topology -->
    <div class="tab-pane fade show active" id="network" role="tabpanel">
        <div class="row">
            <div class="col-md-9">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-circle-nodes"></i> Disease-Disease Edges Network</h5>
                        <span class="badge bg-secondary">Top Composite Connections</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-8 d-flex align-items-center">
                                <label class="form-label me-2 mb-0 fw-bold">Composite Score Cutoff:</label>
                                <input type="range" class="form-range w-50" id="compositeRange" min="50" max="201" step="5" value="100">
                                <span id="thresholdVal" class="ms-2 badge bg-primary">100</span>
                            </div>
                            <div class="col-md-4 text-end">
                                <button onclick="loadDiseaseNetwork()" class="btn btn-sm btn-primary"><i class="fas fa-sync"></i> Re-render Network</button>
                            </div>
                        </div>
                        <div id="diseaseNetwork" class="network-box"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">Network Stats</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span>Unique Diseases:</span>
                            <strong id="nodeCount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Active Edges:</span>
                            <strong id="edgeCount">-</strong>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info shadow-sm mb-3">
                    <i class="fas fa-info-circle"></i> Zoom and drag to inspect clusters. Hovering over connectors exposes Composite Score metrics.
                </div>
                
                <!-- Direct Table Redirection Panel -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light fw-bold text-secondary py-2"><i class="fas fa-external-link-alt"></i> Browse Datasets</div>
                    <div class="card-body p-2">
                        <div class="d-grid gap-2">
                            <button onclick="goToDatasetTab('tab-gd-tab')" class="btn btn-outline-primary btn-sm text-start">
                                <i class="fas fa-dna me-1"></i> Gene-Disease Associations
                            </button>
                            <button onclick="goToDatasetTab('tab-de-tab')" class="btn btn-outline-primary btn-sm text-start">
                                <i class="fas fa-route me-1"></i> Disease-Disease Edges
                            </button>
                            <button onclick="goToDatasetTab('tab-cs-tab')" class="btn btn-outline-primary btn-sm text-start">
                                <i class="fas fa-chart-bar me-1"></i> Centrality Scores
                            </button>
                            <button onclick="goToDatasetTab('tab-gc-tab')" class="btn btn-outline-primary btn-sm text-start">
                                <i class="fas fa-calculator me-1"></i> Disease Gene Count
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Comparison Tool -->
    <div class="tab-pane fade" id="compare" role="tabpanel">
        
        <!-- Disease Comparison Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><i class="fas fa-disease text-danger"></i> Compare Two Diseases</div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-9">
                                <label class="form-label fw-bold">Select Diseases:</label>
                                <select id="compDiseaseA" class="form-select select2-disease mb-2" style="width:100%;"></select>
                                <select id="compDiseaseB" class="form-select select2-disease" style="width:100%;"></select>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-primary w-100 h-100 py-3" onclick="performDiseaseComparison()"><i class="fas fa-magnifying-glass"></i> Compare</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disease Comparison Result Container -->
        <div id="diseaseComparisonResult" style="display:none;" class="mb-5">
            <div class="card shadow-sm border-primary mb-4">
                <div class="card-header bg-primary text-white fw-bold">Disease Overlap Analysis Summary</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 compare-box">
                            <h4 id="resNameA" class="text-primary">-</h4>
                            <p class="text-muted" id="resMondoA"></p>
                            <ul class="list-group list-group-flush shadow-sm">
                                <li class="list-group-item d-flex justify-content-between"><span>Degree Centrality:</span><strong id="resDegA">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Betweenness:</span><strong id="resBetA">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Pagerank Score:</span><strong id="resPageA">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Associated Genes Count:</span><strong id="resGeneCountA">-</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-2 text-center d-flex flex-column justify-content-center">
                            <div class="display-6 text-primary"><i class="fas fa-code-merge"></i></div>
                            <div class="mt-2"><span class="badge bg-success py-2 px-3 fs-6" id="resSharedCount">-</span> Shared Genes</div>
                        </div>
                        <div class="col-md-5 compare-box" style="border-left-color: #dc3545;">
                            <h4 id="resNameB" class="text-danger">-</h4>
                            <p class="text-muted" id="resMondoB"></p>
                            <ul class="list-group list-group-flush shadow-sm">
                                <li class="list-group-item d-flex justify-content-between"><span>Degree Centrality:</span><strong id="resDegB">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Betweenness:</span><strong id="resBetB">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Pagerank Score:</span><strong id="resPageB">-</strong></li>
                                <li class="list-group-item d-flex justify-content-between"><span>Associated Genes Count:</span><strong id="resGeneCountB">-</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light fw-bold">Overlapping Genes</div>
                                <div class="card-body" id="resSharedList" style="max-height:150px; overflow-y:auto; font-family:monospace; background:#fafafa;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gene Comparison Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><i class="fas fa-dna text-success"></i> Compare Two Genes</div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-9">
                                <label class="form-label fw-bold">Select Genes:</label>
                                <select id="compGeneA" class="form-select select2-gene mb-2" style="width:100%;"></select>
                                <select id="compGeneB" class="form-select select2-gene" style="width:100%;"></select>
                            </div>
                            <div class="col-3">
                                <button class="btn btn-success w-100 h-100 py-3" onclick="performGeneComparison()"><i class="fas fa-magnifying-glass"></i> Compare</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gene Comparison Result Container -->
        <div id="geneComparisonResult" style="display:none;">
            <div class="card shadow-sm border-success mb-4">
                <div class="card-header bg-success text-white fw-bold">Gene Overlap Analysis Summary</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 compare-box" style="border-left-color: #198754;">
                            <h4 id="resGeneNameA" class="text-success">-</h4>
                            <ul class="list-group list-group-flush shadow-sm mt-3" id="resGeneListA" style="max-height:250px; overflow-y:auto;"></ul>
                        </div>
                        <div class="col-md-2 text-center d-flex flex-column justify-content-center">
                            <div class="display-6 text-success"><i class="fas fa-shuffle"></i></div>
                            <div class="mt-2"><span class="badge bg-primary py-2 px-3 fs-6" id="resGeneOverlapCount">-</span> Shared Diseases</div>
                        </div>
                        <div class="col-md-5 compare-box" style="border-left-color: #20c997;">
                            <h4 id="resGeneNameB" class="text-teal">-</h4>
                            <ul class="list-group list-group-flush shadow-sm mt-3" id="resGeneListB" style="max-height:250px; overflow-y:auto;"></ul>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light fw-bold">Overlapping Diseases</div>
                                <div class="card-body" id="resGeneOverlapList" style="max-height:150px; overflow-y:auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Tab 3: Detailed Data Tables -->
    <div class="tab-pane fade" id="tables" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="tab-gd-tab" data-bs-toggle="tab" data-bs-target="#tab-gd" type="button" role="tab">Gene-Disease Associations</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-de-tab" data-bs-toggle="tab" data-bs-target="#tab-de" type="button" role="tab">Disease-Disease Edges</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-cs-tab" data-bs-toggle="tab" data-bs-target="#tab-cs" type="button" role="tab">Centrality Scores</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-gc-tab" data-bs-toggle="tab" data-bs-target="#tab-gc" type="button" role="tab">Disease Gene Count</button></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Datatable 1: Gene-Disease Table -->
                    <div class="tab-pane fade show active" id="tab-gd" role="tabpanel">
                        
                        <div class="filter-section">
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-3 mb-md-0">
                                    <label for="symbolFilter" class="form-label fw-bold text-muted">Filter by Symbol:</label>
                                    <select id="symbolFilter" class="form-select">
                                        <option value="">All Symbols</option>
                                    </select>
                                </div>
                                <div class="col-md-5 mb-3 mb-md-0">
                                    <label for="diseaseFilter" class="form-label fw-bold text-muted">Filter by Disease:</label>
                                    <select id="diseaseFilter" class="form-select" style="width: 100%;">
                                        <option value="">All Diseases</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="sourceFilter" class="form-label fw-bold text-muted">Filter by Source:</label>
                                    <select id="sourceFilter" class="form-select">
                                        <option value="">All Sources</option>
                                        <option value="Open-Target">Open-Target</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="dtGD" class="table table-striped table-bordered w-100">
                                <thead><tr><th>Sr. No.</th><th>HGNC ID</th><th>Symbol</th><th>Disease Name</th><th>MONDO</th><th>Source</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Datatable 2: diseases_edges_tb -->
                    <div class="tab-pane fade" id="tab-de" role="tabpanel">
                        <div class="table-responsive">
                            <table id="dtDE" class="table table-striped table-bordered w-100">
                                <thead><tr><th>id</th><th>Disease A</th><th>Disease B</th><th>Overlap Count</th><th>Jaccard</th><th>P-val Adj</th><th>Composite Score</th></tr></thead>
                                <tbody>
                                    <?php
                                    $res = $conn->query("SELECT * FROM diseases_edges_tb LIMIT 100000");
                                    while ($r = $res->fetch_assoc()) {
                                        $disease_A_url = "https://monarchinitiative.org/" . urlencode(trim($r['disease_A']));
                                        $disease_B_url = "https://monarchinitiative.org/" . urlencode(trim($r['disease_B']));
                                        echo "<tr>
                                                <td>{$r['id']}</td>
                                                <td><a href='" . htmlspecialchars($disease_A_url) . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($r['disease_A']) . "</a></td>
                                                <td><a href='" . htmlspecialchars($disease_B_url) . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($r['disease_B']) . "</a></td>
                                                <td>{$r['n_shared']}</td>
                                                <td>" . round($r['jaccard'], 4) . "</td>
                                                <td>" . sprintf("%.4e", $r['pval_adj']) . "</td>
                                                <td>" . round($r['composite_score'], 2) . "</td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Datatable 3: centrality_scores_tb -->
                    <div class="tab-pane fade" id="tab-cs" role="tabpanel">
                        <div class="table-responsive">
                            <table id="dtCS" class="table table-striped table-bordered w-100">
                                <thead><tr><th>id</th><th>Disease</th><th>Degree</th><th>Weighted Deg.</th><th>Betweenness</th><th>Closeness</th><th>Pagerank</th></tr></thead>
                                <tbody>
                                    <?php
                                    $res = $conn->query("SELECT * FROM centrality_scores_tb");
                                    while ($r = $res->fetch_assoc()) {
                                        $disease_url = "https://monarchinitiative.org/" . urlencode(trim($r['disease']));
                                        echo "<tr>
                                                <td>{$r['id']}</td>
                                                <td><a href='" . htmlspecialchars($disease_url) . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($r['disease']) . "</a></td>
                                                <td>{$r['degree']}</td>
                                                <td>" . round($r['weighted_degree'], 3) . "</td>
                                                <td>" . sprintf("%.4e", $r['betweenness']) . "</td>
                                                <td>" . round($r['closeness'], 4) . "</td>
                                                <td>" . sprintf("%.4e", $r['pagerank']) . "</td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Datatable 4: disease_gene_count_tb -->
                    <div class="tab-pane fade" id="tab-gc" role="tabpanel">
                        <div class="table-responsive">
                            <table id="dtGC" class="table table-striped table-bordered w-100">
                                <thead><tr><th>id</th><th>Disease ID</th><th>n_genes</th></tr></thead>
                                <tbody>
                                    <?php
                                    $res = $conn->query("SELECT * FROM disease_gene_count_tb");
                                    while ($r = $res->fetch_assoc()) {
                                        $disease_id_url = "https://monarchinitiative.org/" . urlencode(trim($r['diseaseId']));
                                        echo "<tr>
                                                <td>{$r['id']}</td>
                                                <td><a href='" . htmlspecialchars($disease_id_url) . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($r['diseaseId']) . "</a></td>
                                                <td>{$r['n_genes']}</td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$conn->close();
include 'footer.php';
?>

<!-- Vis.js Library & Page Specific Javascript (Runs after footer loads jQuery/Bootstrap) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>

<script>
    let network = null;
    let diseaseTable = null;

    // Direct routing function to transition tabs smoothly
    function goToDatasetTab(subTabId) {
        // 1. Activate Main Datasets Tab
        const mainTabEl = document.querySelector('#tables-tab');
        const mainTab = new bootstrap.Tab(mainTabEl);
        mainTab.show();
        
        // 2. Activate target inner sub-tab
        const subTabEl = document.querySelector('#' + subTabId);
        const subTab = new bootstrap.Tab(subTabEl);
        subTab.show();
        
        // 3. Focus scroll down to Dataset window area
        document.getElementById('tables').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $(document).ready(function() {
        // --- UNIFIED SEARCH BAR JAVASCRIPT ---
        const $searchInput = $('#unifiedSearchInput');
        const $suggestionBox = $('#unifiedSuggestionBox');
        let debounceTimer;

        $searchInput.on('input', function () {
            clearTimeout(debounceTimer);
            const query = $(this).val().trim();
            
            if (query.length < 1) { 
                $suggestionBox.hide(); 
                return; 
            }

            debounceTimer = setTimeout(() => {
                $.getJSON('search_suggest.php', { term: query })
                    .done(function (data) {
                        if (data.length > 0) {
                            $suggestionBox.empty();

                            const genes = data.filter(item => item.type === 'Gene');
                            const diseases = data.filter(item => item.type === 'Disease');

                            if (genes.length > 0) {
                                $suggestionBox.append('<div class="suggestion-category"><i class="fas fa-dna"></i> Genes</div>');
                                genes.forEach(item => {
                                    $suggestionBox.append(createSuggestionElement(item, query));
                                });
                            }

                            if (diseases.length > 0) {
                                $suggestionBox.append('<div class="suggestion-category"><i class="fas fa-heart-pulse"></i> Diseases</div>');
                                diseases.forEach(item => {
                                    $suggestionBox.append(createSuggestionElement(item, query));
                                });
                            }

                            $suggestionBox.show();
                        } else {
                            $suggestionBox.hide();
                        }
                    })
                    .fail(function (err) {
                        console.error('Unified lookup error:', err);
                    });
            }, 250);
        });

        function createSuggestionElement(item, term) {
            const $el = $('<div class="suggestion-item"></div>');
            const regex = new RegExp(`(${escapeRegExp(term)})`, 'gi');
            const highlightedText = item.label.replace(regex, '<strong>$1</strong>');
            $el.html(highlightedText);
            
            $el.on('click', function () {
                $suggestionBox.hide();
                $searchInput.val('');

                if (item.type === 'Gene') {
                    if ($('#symbolFilter').find("option[value='" + item.symbol + "']").length) {
                        $('#symbolFilter').val(item.symbol).trigger('change');
                    } else {
                        const newOption = new Option(item.symbol, item.symbol, true, true);
                        $('#symbolFilter').append(newOption).trigger('change');
                    }
                } else if (item.type === 'Disease') {
                    if ($('#diseaseFilter').find("option[value='" + item.mondo + "']").length) {
                        $('#diseaseFilter').val(item.mondo).trigger('change');
                    } else {
                        const newOption = new Option(item.label, item.mondo, true, true);
                        $('#diseaseFilter').append(newOption).trigger('change');
                    }
                }

                goToDatasetTab('tab-gd-tab');
            });
            return $el;
        }

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        $(document).on('click', function (e) {
            if (!$searchInput.is(e.target) && !$suggestionBox.has(e.target).length) {
                $suggestionBox.hide();
            }
        });

        $('#unifiedSearchBtn').on('click', function() {
            const value = $searchInput.val().trim();
            if (value.length > 0) {
                if (diseaseTable) {
                    diseaseTable.search(value).draw();
                }
                goToDatasetTab('tab-gd-tab');
                $suggestionBox.hide();
            }
        });

        // --- DATATABLE INTEGRATION ---
        diseaseTable = $('#dtGD').DataTable({
            processing: true,
            serverSide: true,
            ajax: { 
                url: 'get_disease_data_serverside.php', 
                type: 'POST',
                data: function(d) {
                    d.symbol = $('#symbolFilter').val();
                    d.disease = $('#diseaseFilter').val();
                    d.source = $('#sourceFilter').val();
                },
                error: function (xhr, error, code) {
                    console.error("Gene-Disease Table Load Error: ", xhr.responseText);
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
                        return `<a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=${encodeURIComponent(cleanSymbol)}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
                { 
                    data: 3,
                    render: function(data, type, row) {
                        if (!data) return '';
                        let cleanMondo = row[4] ? row[4].toString().trim() : '';
                        if (cleanMondo === '') return data;
                        return `<a href="https://monarchinitiative.org/${encodeURIComponent(cleanMondo)}" target="_blank" rel="noopener noreferrer">${data}</a>`;
                    }
                },
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
            order: [[2, 'asc']]
        });
        
        $('#symbolFilter, #diseaseFilter, #sourceFilter').on('change', function() {
            if (diseaseTable) {
                diseaseTable.draw();
            }
        });

        $('#dtDE, #dtCS, #dtGC').DataTable({ pageLength: 10 });

        // Select2 filter search
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

        loadStandardFilterOptions();

        // Comparison tool options
        $('.select2-disease').select2({
            theme: 'bootstrap-5',
            ajax: {
                url: 'get_compare_data.php?type=search_diseases',
                dataType: 'json',
                delay: 250,
                processResults: data => ({ results: data })
            },
            placeholder: 'Search and Select Disease'
        });

        $('.select2-gene').select2({
            theme: 'bootstrap-5',
            ajax: {
                url: 'get_compare_data.php?type=search_genes',
                dataType: 'json',
                delay: 250,
                processResults: data => ({ results: data })
            },
            placeholder: 'Search and Select Gene'
        });

        // Set default values for Disease Comparison and perform initial comparison
        const defaultDisA = new Option('neurodevelopmental disorder (MONDO:0700092)', 'MONDO:0700092', true, true);
        $('#compDiseaseA').append(defaultDisA).trigger('change');

        const defaultDisB = new Option('metabolic disease (MONDO:0005066)', 'MONDO:0005066', true, true);
        $('#compDiseaseB').append(defaultDisB).trigger('change');

        performDiseaseComparison();

        // Set default values for Gene Comparison and perform initial comparison
        const defaultGeneA = new Option('TCF7L2', 'TCF7L2', true, true);
        $('#compGeneA').append(defaultGeneA).trigger('change');

        const defaultGeneB = new Option('STAG1', 'STAG1', true, true);
        $('#compGeneB').append(defaultGeneB).trigger('change');

        performGeneComparison();

        loadDiseaseNetwork();

        $('#compositeRange').on('input', function() {
            $('#thresholdVal').text($(this).val());
        });
        
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'network-tab' && network) {
                network.fit();
            }
        });
    });

    function loadStandardFilterOptions() {
        $.get('get_filter_options.php?field=Approved_symbol&table=Disease_tb')
        .done(data => {
            const $filter = $('#symbolFilter');
            if (data && data.length > 0) {
                data.forEach(item => $filter.append(`<option value="${item.value}">${item.text}</option>`));
            }
        });
    }

    function loadDiseaseNetwork() {
        const threshold = $('#compositeRange').val();
        
        $.post('get_disease_edges_network.php', { threshold: threshold }, 'json')
            .done(function(data) {
                if (data.error) {
                    console.error("Network SQL error: ", data.error);
                    return;
                }
                
                if (!data.edges || data.edges.length === 0) {
                    $('#diseaseNetwork').html('<div class="d-flex align-items-center justify-content-center h-100"><div class="text-center text-muted"><i class="fas fa-circle-info fa-2x mb-2"></i><p class="mb-0">No network links found at this Composite Score threshold. Try lowering the threshold.</p></div></div>');
                    $('#nodeCount').text('0');
                    $('#edgeCount').text('0');
                    return;
                }

                const uniqueNodes = new Set();
                const nodeNameMap = {}; 
                
                const processedEdges = data.edges.map(edge => {
                    const diseaseA = edge.disease_A;
                    const diseaseB = edge.disease_B;
                    const diseaseAName = edge.diseaseA_name || diseaseA;
                    const diseaseBName = edge.diseaseB_name || diseaseB;
                    const compScore = parseFloat(edge.composite_score) || 0;
                    
                    if (diseaseA) { uniqueNodes.add(diseaseA); nodeNameMap[diseaseA] = diseaseAName; }
                    if (diseaseB) { uniqueNodes.add(diseaseB); nodeNameMap[diseaseB] = diseaseBName; }

                    return {
                        from: diseaseA,
                        to: diseaseB,
                        value: compScore,
                        width: Math.min(compScore / 50, 6),
                        title: `<strong>Disease A:</strong> ${diseaseAName}<br><strong>Disease B:</strong> ${diseaseBName}<br><strong>Composite Score:</strong> ${compScore.toFixed(2)}`,
                        color: { 
                            color: '#64748b', 
                            highlight: '#0d6efd', 
                            hover: '#0d6efd',
                            opacity: 0.7
                        }
                    };
                });

                const nodesList = Array.from(uniqueNodes).map(nodeId => {
                    const nodeName = nodeNameMap[nodeId] || nodeId;
                    return { 
                        id: nodeId, 
                        label: nodeName,
                        title: nodeName,
                        color: {
                            background: '#3b82f6',
                            border: '#1e40af',
                            highlight: {
                                background: '#0d6efd',
                                border: '#0a58ca'
                            },
                            hover: {
                                background: '#0d6efd',
                                border: '#0a58ca'
                            }
                        }
                    };
                });

                $('#nodeCount').text(nodesList.length);
                $('#edgeCount').text(processedEdges.length);

                const container = document.getElementById('diseaseNetwork');
                container.innerHTML = '';

                const networkData = {
                    nodes: new vis.DataSet(nodesList),
                    edges: new vis.DataSet(processedEdges)
                };

                const options = {
                    autoResize: true,
                    height: '500px',
                    width: '100%',
                    physics: {
                        enabled: true,
                        stabilization: {
                            iterations: 150,
                            fit: true,
                            enabled: false
                        },
                        barnesHut: {
                            gravitationalConstant: -2000,
                            centralGravity: 0.3,
                            springLength: 200,
                            springConstant: 0.03
                        }
                    },
                    nodes: {
                        shape: 'dot',
                        size: 16,
                        font: {
                            size: 13,
                            color: '#334155', 
                            bold: {
                                color: '#1e293b' 
                            }
                        },
                        borderWidth: 2,
                        borderWidthSelected: 3
                    },
                    edges: {
                        smooth: { 
                            type: 'continuous',
                            forceDirection: 'none'
                        },
                        scaling: {
                            min: 0.5,
                            max: 6,
                            label: false
                        },
                        font: {
                            size: 11,
                            color: '#64748b',
                            background: {
                                enabled: true,
                                color: '#ffffff'
                            }
                        }
                    },
                    interaction: {
                        hover: true,
                        tooltipDelay: 150,
                        navigationButtons: true,
                        keyboard: true,
                        zoomView: true,
                        dragView: true
                    }
                };

                network = new vis.Network(container, networkData, options);
            })
            .fail(function(xhr, status, error) {
                console.error("Network AJAX Request failed: ", xhr.responseText);
                $('#diseaseNetwork').html('<div class="alert alert-danger">Failed to load network data. Please try again.</div>');
            });
    }

    function performDiseaseComparison() {
        const disA = $('#compDiseaseA').val();
        const disB = $('#compDiseaseB').val();
        if (!disA || !disB) {
            alert('Please select two valid diseases.');
            return;
        }

        $.get('get_compare_data.php', { type: 'diseases', disA: disA, disB: disB }, function(data) {
            if (data.diseaseA && data.diseaseB) {
                $('#diseaseComparisonResult').show();

                $('#resNameA').text(data.diseaseA.name);
                $('#resMondoA').text(data.diseaseA.mondo);
                $('#resNameB').text(data.diseaseB.name);
                $('#resMondoB').text(data.diseaseB.mondo);

                $('#resDegA').text(data.diseaseA.centrality.degree);
                $('#resBetA').text(Number(data.diseaseA.centrality.betweenness).toExponential(4));
                $('#resPageA').text(Number(data.diseaseA.centrality.pagerank).toExponential(4));
                $('#resGeneCountA').text(data.diseaseA.gene_count);

                $('#resDegB').text(data.diseaseB.centrality.degree);
                $('#resBetB').text(Number(data.diseaseB.centrality.betweenness).toExponential(4));
                $('#resPageB').text(Number(data.diseaseB.centrality.pagerank).toExponential(4));
                $('#resGeneCountB').text(data.diseaseB.gene_count);

                $('#resSharedCount').text(data.overlap.length);
                $('#resSharedList').text(data.overlap.join('; '));
            }
        });
    }

    function performGeneComparison() {
        const geneA = $('#compGeneA').val();
        const geneB = $('#compGeneB').val();
        if (!geneA || !geneB) {
            alert('Please select two valid genes.');
            return;
        }

        $.get('get_compare_data.php', { type: 'genes', geneA: geneA, geneB: geneB }, function(data) {
            if (data.geneA && data.geneB) {
                $('#geneComparisonResult').show();
                
                $('#resGeneNameA').text(data.geneA.symbol);
                $('#resGeneNameB').text(data.geneB.symbol);

                let listA = '';
                Object.values(data.geneA.diseases).forEach(name => {
                    listA += `<li class="list-group-item py-1">${name}</li>`;
                });
                $('#resGeneListA').html(listA || '<li class="list-group-item text-muted">None</li>');

                let listB = '';
                Object.values(data.geneB.diseases).forEach(name => {
                    listB += `<li class="list-group-item py-1">${name}</li>`;
                });
                $('#resGeneListB').html(listB || '<li class="list-group-item text-muted">None</li>');

                $('#resGeneOverlapCount').text(data.overlap.length);
                let overlapHtml = '';
                data.overlap.forEach(item => {
                    overlapHtml += `<span class="badge bg-secondary m-1 p-2">${item.name} (${item.mondo})</span>`;
                });
                $('#resGeneOverlapList').html(overlapHtml || '<span class="text-muted">No shared diseases found.</span>');
            }
        });
    }
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var hash = window.location.hash;
        if (hash) {
            var tabTriggerEl = document.querySelector('button[data-bs-target="' + hash + '"]') || 
                               document.querySelector('a[href="' + hash + '"]');
            if (tabTriggerEl) {
                var tab = new bootstrap.Tab(tabTriggerEl);
                tab.show();
            }
        }
    });
</script>