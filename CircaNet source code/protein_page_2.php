<?php 
include 'header.php';
$initial_id = isset($_GET['id']) ? htmlspecialchars(trim($_GET['id'])) : '';
?>

<!-- Load Page-Specific Libraries (Using basic bundle to optimize size) -->
<script src="https://cdn.plot.ly/plotly-basic-2.35.2.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/3dmol@2.1.0/build/3Dmol-min.js" defer></script>

<style>
/* ============================================================
   TOKENS & LOCAL SCOPE ADJUSTMENTS
   ============================================================ */
:root{
  --bg:           #f7f6f2;
  --panel:        #ffffff;
  --line:         #e7e4dc;
  --ink:          #21201c;
  --ink-soft:     #6f6c63;
  --ink-faint:    #a4a097;
  --accent:       #185fa5;
  --accent-soft:  #e7f0fa;
  --struct-bg:    #0d1024;
  --struct-panel: #11142b;
  --struct-line:  #262a4a;
  --struct-text:  #c9cbe0;
  --pathogenic:   #e24b4a;
  --ambiguous:    #ef9f27;
  --benign:       #1d9e75;
  --lock:         #ffd940;
  --radius:       10px;
  --mono: 'IBM Plex Mono', ui-monospace, monospace;
  --sans: 'Inter', system-ui, -apple-system, sans-serif;
}

/* ============================================================
   CONTAINER WIDESCREEN DISPLAY OVERRIDES
   ============================================================ */
@media (min-width: 1200px) {
  .container {
    max-width: 95% !important;
    width: 95% !important;
  }
}
@media (min-width: 1600px) {
  .container {
    max-width: 1650px !important;
  }
}

/* ============================================================
   HERO & SEARCH COMPONENT IMPLEMENTATION
   ============================================================ */
.gene-page-wrapper .hero-section {
    background: linear-gradient(135deg, #10428d 0%, #1e40af 100%);
    border-radius: 12px; 
    padding: 30px; color: white;
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); flex-wrap: wrap;
    gap: 20px;
}
.gene-page-wrapper .hero-text { flex: 1; min-width: 300px; padding-right: 20px; }
.gene-page-wrapper .hero-text h1 { 
    margin: 0; font-size: 2.2rem; font-weight: 700; color: white; line-height: 1.2; 
}
.gene-page-wrapper .hero-text .description { 
    display: block; margin-top: 5px; opacity: 0.9; font-weight: 300; 
    font-size: 1rem; color: #e0e7ff; 
}

/* Dynamic Tags */
.gene-page-wrapper .database-tags { margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
.gene-page-wrapper .db-tag-pill {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
    border-radius: 20px; font-size: 0.85rem !important; font-weight: 700 !important;
    background-color: #ffffff !important; color: #334155 !important;
    border: 1px solid #cbd5e1 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.06);
    transition: all 0.2s ease; text-decoration: none !important;
}
.gene-page-wrapper .db-tag-pill:hover {
    background-color: #f1f5f9 !important; transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1); color: var(--accent) !important;
}

/* Search Wrapper */
.gene-page-wrapper .search-wrapper-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}
.gene-page-wrapper .search-container { 
    position: relative; margin-top: 0; display: flex; 
}
.gene-page-wrapper .search-input {
    padding: 12px 20px 12px 45px; border-radius: 50px; border: none;
    width: 280px; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: width 0.3s ease;
    background-color: white !important;
    color: #21201c !important;
    height: 42px;
}
.gene-page-wrapper .search-input:focus { outline: none; width: 330px; }
.gene-page-wrapper .search-icon { 
    position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; 
}
.gene-page-wrapper .search-btn {
    background: #10b981; color: white; border: none; padding: 12px 25px;
    border-radius: 50px; margin-left: 10px; cursor: pointer; font-weight: 600;
    transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.gene-page-wrapper .search-btn:hover { background: #059669; }

/* Watch Video Tutorial Hero Pill Button Style */
.gene-page-wrapper .video-tutorial-hero-btn {
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
.gene-page-wrapper .video-tutorial-hero-btn:hover {
    background-color: #f0f7ff !important;
    border-color: #0d6efd !important;
    color: #0a58ca !important;
    transform: translateY(-1px);
}
.gene-page-wrapper .video-tutorial-hero-btn i {
    font-size: 1.05rem;
    color: #0d6efd !important;
}

.gene-page-wrapper.container {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

.gene-page-wrapper .hero-section {
    margin-top: 0 !important;
}

/* ============================================================
   AUTOSUGGEST DROPDOWN
   ============================================================ */
.autocomplete-suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  width: 280px;
  max-height: 220px;
  overflow-y: auto;
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 7px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  z-index: 1050;
  margin-top: 4px;
  display: none;
}
.autocomplete-suggestion {
  padding: 8px 10px;
  font-size: 12.5px;
  font-family: var(--mono);
  cursor: pointer;
  color: var(--ink);
  border-bottom: 1px solid var(--bg);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.autocomplete-suggestion:last-child {
  border-bottom: none;
}
.autocomplete-suggestion strong {
  color: var(--accent);
  font-weight: 700;
}
.autocomplete-suggestion:hover,
.autocomplete-suggestion.active {
  background: var(--accent-soft);
  color: var(--accent);
}

/* ============================================================
   LAYOUT & PANELS
   ============================================================ */
.layout{ 
  display:grid; 
  grid-template-columns:minmax(0,1.45fr) minmax(360px,1fr); 
  gap:16px; 
  padding:16px 0; 
  align-items:stretch;
}
@media (max-width: 1000px){ .layout{ grid-template-columns: 1fr; } }

.charts-col {
  position: relative;
}

.panel{ background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); overflow:hidden; margin-bottom:16px; position: relative; }
.charts-col .panel:last-child{ margin-bottom:0; }
.panel-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
.panel-head h2{ font-size:13.5px; font-weight:600; margin:0; }
.panel-head .hint{ font-size:11px; color:var(--ink-faint); }
.legend-row{ display:flex; gap:14px; flex-wrap:wrap; font-size:11.5px; color:var(--ink-soft); padding:0 16px 10px; }
.legend-row .chip{ display:inline-flex; align-items:center; gap:6px; }
.legend-row .dot{ width:9px; height:9px; border-radius:50%; display:inline-block; }
.legend-row .swatch{ width:13px; height:9px; border-radius:2px; display:inline-block; }
.chart{ width:100%; min-height: 260px; }

/* ============================================================
   STRUCTURE PANEL
   ============================================================ */
.structure-col {
  height: 100%;
}
.structure-panel{ 
  background:var(--struct-panel); 
  border-color:var(--struct-line); 
  color:var(--struct-text);
  height: 100%;
  display: flex;
  flex-direction: column;
  margin-bottom: 0;
}
.structure-panel .panel-head{ border-bottom:1px solid var(--struct-line); }
.structure-panel .panel-head h2{ color:#eef0fb; }
.structure-panel .panel-head .hint{ color:#7f84ad; }

#viewport{ 
  width:100%; 
  height: 650px;
  flex-grow: 1;
  position:relative; 
  background:var(--struct-bg); 
}
#viewport canvas{ display:block; }

.struct-footer{ padding:12px 16px 16px; border-top:1px solid var(--struct-line); display:flex; flex-direction:column; gap:12px; }
.domain-legend{ display:flex; gap:6px; flex-wrap:wrap; }
.domain-legend .tag{
  font-size:10.5px; padding:3px 8px; border-radius:5px; max-width:170px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500;
}

.colorby-row{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.colorby-row .label{ font-size:11.5px; color:#9498c2; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
.colorby-opts{ display:flex; gap:4px; background:#1a1d3a; border-radius:7px; padding:3px; }
.colorby-opts label{ font-size:12px; padding:5px 11px; border-radius:5px; cursor:pointer; color:#9498c2; }
.colorby-opts input{ display:none; }
.colorby-opts input:checked + span{ color:#fff; }
.colorby-opts label.active{ background:#2d3163; color:#fff; }

.gradient-legend{ display:flex; align-items:center; gap:8px; font-family:var(--mono); font-size:10.5px; color:#9498c2; }
.gradient-bar{ height:9px; width:130px; border-radius:4px; flex-shrink:0; }

.struct-note{ font-size:11px; color:#6b6f99; line-height:1.5; }
.empty-state{ display:flex; align-items:center; justify-content:center; height:100%; color:#6b6f99; font-size:13px; text-align:center; padding:20px; }

/* Lock badge */
.lock-badge{
  display:none; align-items:center; gap:8px; font-size:11.5px; font-weight:600;
  color:var(--lock); background:rgba(255,217,64,0.1); border:1px solid rgba(255,217,64,0.35);
  padding:4px 6px 4px 10px; border-radius:999px;
}
.lock-badge.visible{ display:flex; }
.lock-badge button{
  font-family:var(--sans); font-size:11px; font-weight:600; color:#11142b;
  background:var(--lock); border:none; border-radius:999px; padding:3px 10px; cursor:pointer;
}
.lock-badge button:hover{ background:#ffe580; }

/* ============================================================
   LOADING ANIMATIONS & OVERLAYS
   ============================================================ */
.loading-overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(255, 255, 255, 0.88);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 50;
  border-radius: var(--radius);
  backdrop-filter: blur(2px);
  transition: opacity 0.3s ease;
}

.spinner-circle {
  width: 44px;
  height: 44px;
  border: 4px solid #e2e8f0;
  border-top: 4px solid var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner-label {
  margin-top: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--accent);
}

.struct-loading-overlay {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(13, 16, 36, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 20;
  color: #c9cbe0;
}

.struct-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #262a4a;
  border-top: 4px solid #4a88e8;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>

<!-- ============== APP WRAPPER CONTAINER ============== -->
<div class="gene-page-wrapper container pb-4 pt-0">

    <!-- BLUE GRADIENT HERO SECTION -->
    <div class="hero-section">
        <div class="hero-text">
            <h1>
                <a id="geneName" target="_blank" style="color: white; text-decoration: none;">
                    <?php if(!empty($initial_id)): ?>
                        <i class="fas fa-circle-notch fa-spin me-2"></i>Loading <?php echo $initial_id; ?>...
                    <?php else: ?>
                        Search a Protein
                    <?php endif; ?>
                </a>
                <span class="uid text-white-50 ms-2" id="uidLabel" style="font-family: var(--mono); font-size: 1.15rem; font-weight: normal;">
                    <?php echo !empty($initial_id) ? '&middot; Fetching annotations...' : '&mdash;'; ?>
                </span>
            </h1>
            <span class="description">
                Explore protein domain architectures, alpha-missense variants, liquid-liquid phase separation (LLPS) propensities, and intrinsic disorders on 3D structures.
            </span>
            
            <!-- Breadcrumbs / Section Jumps -->
            <div class="sub-breadcrumb database-tags mt-3">
                <a data-jump="domainPanelWrap" class="db-tag-pill" style="cursor: pointer;"><i class="fas fa-fingerprint" style="color: #0d6efd;"></i> Variant</a>
                <a data-jump="llpsPanelWrap" class="db-tag-pill" style="cursor: pointer;"><i class="fas fa-droplet" style="color: #198754;"></i> LLPS</a>
                <a data-jump="iupredPanelWrap" class="db-tag-pill" style="cursor: pointer;"><i class="fas fa-dna" style="color: #6610f2;"></i> IDR</a>
                <a data-jump="structurePanelWrap" class="db-tag-pill" style="cursor: pointer;"><i class="fas fa-cube" style="color: #f59e0b;"></i> Structure</a>
                <a id="geneProfileLink" href="https://datascience.imtech.res.in/anshu/circanet/gene.php" target="_blank" class="db-tag-pill"><i class="fas fa-user-md" style="color: #0dcaf0;"></i> Gene Profile</a>
                <a id="orthologsLink" href="https://datascience.imtech.res.in/anshu/circanet/ortho.php" target="_blank" class="db-tag-pill"><i class="fas fa-sitemap" style="color: #6c757d;"></i> Orthologs</a>
            </div>
        </div>

        <div class="search-wrapper-right">
            <!-- Search container with autosuggest dropdown -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input id="proteinInput" type="text" class="search-input" 
                       placeholder="UniProt ID or gene, e.g. O15534" 
                       value="<?php echo $initial_id; ?>" autocomplete="off">
                <button id="loadBtn" class="search-btn">Search</button>
            </div>
            
            <!-- Video Tutorial Pill Button -->
            <div class="d-flex align-items-center gap-3 mt-3">
                <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/Structure.mp4" 
                   target="_blank" 
                   class="video-tutorial-hero-btn">
                    <i class="fas fa-video me-1"></i>
                    <span>Watch Video Tutorial</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ============== TWO COLUMN LAYOUT ============== -->
    <div class="layout">

      <!-- ============== LEFT: residue-level charts ============== -->
      <section class="charts-col">

        <!-- Overlay loader for left chart stack -->
        <div id="chartsLoader" class="loading-overlay" style="display: <?php echo !empty($initial_id) ? 'flex' : 'none'; ?>;">
          <div class="spinner-circle"></div>
          <div class="spinner-label"><i class="fas fa-dna fa-spin me-1"></i> Loading charts & annotations...</div>
        </div>

        <div class="panel" id="domainPanelWrap">
          <div class="panel-head">
            <h2>Domain Architecture &amp; Variants (AlphaMissense)</h2>
            <span class="hint">hover to highlight &middot; click to lock &middot; drag to zoom a region</span>
          </div>
          <div class="legend-row" id="amLegend">
            <span class="chip"><span class="dot" style="background:var(--pathogenic)"></span>Likely pathogenic</span>
            <span class="chip"><span class="dot" style="background:var(--ambiguous)"></span>Uncertain / ambiguous</span>
            <span class="chip"><span class="dot" style="background:var(--benign)"></span>Likely benign</span>
          </div>
          <div id="domainChart" class="chart"></div>
        </div>

        <div class="panel" id="llpsPanelWrap">
          <div class="panel-head">
            <h2>LLPS Propensity Score</h2>
            <span class="hint">positive = phase-separation prone &middot; dashed = threshold (0)</span>
          </div>
          <div id="llpsChart" class="chart"></div>
        </div>

        <div class="panel" id="iupredPanelWrap">
          <div class="panel-head">
            <h2>Intrinsic Disorder (IUPred3)</h2>
            <span class="hint">score &gt; 0.5 = disordered &middot; dashed = threshold (0.5)</span>
          </div>
          <div id="iupredChart" class="chart"></div>
        </div>

      </section>

      <!-- ============== RIGHT: 3D structure ============== -->
      <section class="structure-col">
        <div class="panel structure-panel" id="structurePanelWrap">
          <div class="panel-head">
            <h2 id="structureTitle">3D Structure</h2>
            <span class="hint" id="structureHint">drag a region on the left to zoom here</span>
            <span class="lock-badge" id="lockBadge">
              &#128274; <span id="lockBadgeText">locked</span>
              <button id="resetViewBtn" type="button">Reset</button>
            </span>
          </div>
          <div id="viewport">
            <!-- 3D Viewport Loader -->
            <div id="viewportLoader" class="struct-loading-overlay" style="display: <?php echo !empty($initial_id) ? 'flex' : 'none'; ?>;">
              <div class="struct-spinner"></div>
              <div class="mt-3 fs-6 font-monospace"><i class="fas fa-cube fa-spin me-1"></i> Rendering 3D model...</div>
            </div>
            <div class="empty-state" id="viewportEmpty" style="display: <?php echo !empty($initial_id) ? 'none' : 'flex'; ?>;">
              Enter a UniProt ID or Gene Symbol above to render structure.
            </div>
          </div>
          <div class="struct-footer">
            <div class="domain-legend" id="domainLegend"></div>
            <div class="colorby-row">
              <span class="label">Color by</span>
              <div class="colorby-opts" id="colorByOpts">
                <label data-mode="domain" class="active"><input type="radio" name="colorby" value="domain" checked><span>Domain</span></label>
                <label data-mode="iupred"><input type="radio" name="colorby" value="iupred"><span>IUPred score</span></label>
                <label data-mode="llps"><input type="radio" name="colorby" value="llps"><span>LLPS score</span></label>
              </div>
              <div class="gradient-legend" id="gradientLegend"></div>
            </div>
            <div class="struct-note">Hover over a point to preview that residue. Click a point to lock the view there, click it again (or hit Reset) to release it.</div>
          </div>
        </div>
      </section>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  /* =================================================================================
     BACKEND CONTRACT
  ================================================================================= */
  const API_BASE = "https://datascience.imtech.res.in/anshu/circanet/php-file-based/api";

  /* Style constants */
  const AM_COLORS  = { likely_pathogenic: "#e24b4a", ambiguous: "#ef9f27", likely_benign: "#1d9e75" };
  const AM_OFFSETS = { likely_pathogenic: -2, ambiguous: 0, likely_benign: 2 };
  const AM_LABELS  = { likely_pathogenic: "Likely pathogenic", ambiguous: "Uncertain / ambiguous", likely_benign: "Likely benign" };

  const DOMAIN_PALETTE = [
    { fill: "#d4e8c2", stroke: "#8ab870", text: "#3b6d11" },
    { fill: "#c5daf5", stroke: "#7aaee0", text: "#185fa5" },
    { fill: "#f5cece", stroke: "#e08080", text: "#a32d2d" },
    { fill: "#e8d5f5", stroke: "#b07fd4", text: "#534ab7" },
    { fill: "#fde8c8", stroke: "#e0a050", text: "#854f0b" },
  ];

  const IUPRED_STOPS = [[0,"#1b6ca8"],[0.35,"#74b3d8"],[0.5,"#f5f0e8"],[0.65,"#f0a868"],[1,"#c45000"]];
  const LLPS_STOPS   = [[0,"#0f6e56"],[0.3,"#5dcaa5"],[0.45,"#b4b2a9"],[0.55,"#b4b2a9"],[0.7,"#f0997b"],[1,"#993c1d"]];

  const PLOT_FONT = { family: "Inter, system-ui, sans-serif", color: "#6f6c63", size: 11 };

  const HOVER_WINDOW     = 25;
  const HOVER_ZOOM_DELAY = 150;
  const ZOOM_DURATION    = 500;

  /* App state & Client-side Caching */
  const state = {
    data: null,
    domainColors: [],
    viewer: null,
    hoverResidue: null,
    lockedResidue: null,
    hoverZoomTimer: null,
    colorMode: "domain",
    llpsRange: [0, 1],
    charts: ["domainChart", "llpsChart", "iupredChart"],
    syncing: false,
  };

  const cache = {
    proteins: new Map(),
    structures: new Map()
  };

  const el = (id) => document.getElementById(id);

  /* =================================================================================
     LOADER CONTROLS
  ================================================================================= */
  function showLoading(id) {
    el("loadBtn").disabled = true;
    el("chartsLoader").style.display = "flex";
    el("viewportLoader").style.display = "flex";
    if (el("viewportEmpty")) el("viewportEmpty").style.display = "none";

    const geneLink = el("geneName");
    geneLink.innerHTML = `<i class="fas fa-circle-notch fa-spin me-2"></i>Loading ${id}...`;
    geneLink.removeAttribute("href");
    el("uidLabel").textContent = "Fetching annotations\u2026";
  }

  function hideLoading() {
    el("loadBtn").disabled = false;
    $("#chartsLoader").fadeOut(250);
    $("#viewportLoader").fadeOut(250);
  }

  /* =================================================================================
     COLOR HELPERS
  ================================================================================= */
  function hexToRgb(hex){
    const h = hex.replace("#","");
    return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)];
  }
  function rgbToHex(rgb){
    return "#" + rgb.map(v => Math.max(0,Math.min(255,Math.round(v))).toString(16).padStart(2,"0")).join("");
  }
  function interpolateStops(stops, t){
    t = Math.max(0, Math.min(1, t));
    for(let i=0;i<stops.length-1;i++){
      const [t0,c0] = stops[i], [t1,c1] = stops[i+1];
      if(t >= t0 && t <= t1){
        const local = (t1===t0) ? 0 : (t - t0) / (t1 - t0);
        const a = hexToRgb(c0), b = hexToRgb(c1);
        return rgbToHex(a.map((v,i)=> v + (b[i]-v)*local));
      }
    }
    return stops[stops.length-1][1];
  }
  function scaleColor(stops, value, min, max){
    if(max === min) return interpolateStops(stops, 0.5);
    return interpolateStops(stops, (value - min) / (max - min));
  }

  /* =================================================================================
     FETCHING WITH PARALLEL REQUESTS & CACHING
  ================================================================================= */
  async function fetchProtein(id){
    if(cache.proteins.has(id)) return cache.proteins.get(id);
    const res = await fetch(`${API_BASE}/protein.php?id=${encodeURIComponent(id)}`);
    if(!res.ok) throw new Error(`Backend returned ${res.status} for "${id}"`);
    const val = await res.json();
    cache.proteins.set(id, val);
    return val;
  }

  async function fetchStructureText(data, id){
    if(cache.structures.has(id)) return cache.structures.get(id);
    const pdb = data && data.structure && data.structure.pdb_url;
    const url = (pdb && /^https?:\/\//i.test(pdb)) ? pdb : `${API_BASE}/structure.php?id=${encodeURIComponent(id)}`;
    const res = await fetch(url);
    if(!res.ok) throw new Error(`Could not load structure from ${url} (HTTP ${res.status})`);
    const val = await res.text();
    cache.structures.set(id, val);
    return val;
  }

  /* =================================================================================
     MAIN LOAD SEQUENCE
  ================================================================================= */
  async function loadProtein(rawId){
    const id = rawId.trim();
    if(!id) return;
    
    showLoading(id);
    resetLock();

    try {
      const proteinPromise = fetchProtein(id);
      const structurePromise = cache.structures.has(id) 
        ? Promise.resolve(cache.structures.get(id))
        : fetch(`${API_BASE}/structure.php?id=${encodeURIComponent(id)}`)
            .then(res => res.ok ? res.text() : null)
            .catch(() => null);

      const [data, standardPdbText] = await Promise.all([proteinPromise, structurePromise]);
      
      if(!data || (!data.uniprot_id && !data.gene_name)) {
        throw new Error(`No protein data found for "${id}"`);
      }

      state.data = data;
      assignDomainColors(data);

      const targetGeneName = data.gene_name || data.uniprot_id;
      const geneLabel = data.gene_name ? `${data.gene_name}` : data.uniprot_id;
      const geneLinkElement = el("geneName");
      
      geneLinkElement.textContent = geneLabel;
      geneLinkElement.href = `https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=${encodeURIComponent(targetGeneName)}`;
      
      el("geneProfileLink").href = `https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=${encodeURIComponent(targetGeneName)}`;
      el("orthologsLink").href = `https://datascience.imtech.res.in/anshu/circanet/ortho.php?keyword=${encodeURIComponent(targetGeneName)}`;

      el("uidLabel").textContent = `${data.uniprot_id} \u00b7 ${data.length} aa`;
      el("structureTitle").textContent = `3D Structure \u2013 ${data.gene_name || data.uniprot_id} (AlphaFold)`;

      renderDomainChart(data);
      renderLLPSChart(data);
      renderIUPredChart(data);
      renderDomainLegend(data);

      let pdbText = standardPdbText;
      const customPdb = data.structure && data.structure.pdb_url;
      if (!pdbText || (customPdb && /^https?:\/\//i.test(customPdb))) {
        pdbText = await fetchStructureText(data, data.uniprot_id || id);
      } else {
        cache.structures.set(id, pdbText);
      }

      initViewer(pdbText, data);
      hideLoading();

    } catch(err) {
      console.error(err);
      hideLoading();
      
      const geneLinkElement = el("geneName");
      geneLinkElement.innerHTML = `<i class="fas fa-exclamation-triangle text-warning me-2"></i>Protein not found (${id})`;
      geneLinkElement.removeAttribute("href");
      el("uidLabel").textContent = "Please verify the search query";
      
      if (el("viewportEmpty")) {
        el("viewportEmpty").textContent = `No structure available for "${id}"`;
        el("viewportEmpty").style.display = "flex";
      }
    }
  }

  function assignDomainColors(data){
    const doms = (data.domains || []).slice().sort((a,b)=>a.start-b.start);
    state.domainColors = doms.map((d,i)=> Object.assign({}, d, DOMAIN_PALETTE[i % DOMAIN_PALETTE.length]));
  }

  /* =================================================================================
     SHARED SHAPES & CHARTS
  ================================================================================= */
  function domainShapes(prot_len){
    const backbone = {
      type:"line", x0:1, x1:prot_len, y0:0, y1:0, xref:"x", yref:"y2",
      line:{ color:"rgba(80,80,80,0.35)", width:1.5 }
    };
    const rects = state.domainColors.map(d => ({
      type:"rect", x0:d.start, x1:d.end, y0:-0.6, y1:0.6, xref:"x", yref:"y2",
      fillcolor:d.fill, opacity:0.9, line:{ color:d.stroke, width:0.8 }
    }));
    return [backbone, ...rects];
  }

  function domainAnnotations(){
    return state.domainColors.map(d => ({
      x:(d.start+d.end)/2, y:1, xref:"x", yref:"y2",
      text: (d.name && d.name.length>32) ? d.name.slice(0,32)+"\u2026" : (d.name||d.accession),
      showarrow:false, font:{ size:10, color:d.text }, xanchor:"center", yanchor:"bottom"
    }));
  }

  function domainHoverTrace(){
    if(state.domainColors.length === 0) return null;
    return {
      type:"scatter", mode:"markers",
      x: state.domainColors.map(d=>(d.start+d.end)/2),
      y: state.domainColors.map(()=>0),
      marker:{ size:30, opacity:0 },
      text: state.domainColors.map(d => `<b>${d.name||d.accession}</b><br>${d.accession||""}<br>${d.start}\u2013${d.end} aa \u00b7 ${d.end-d.start+1} residues`),
      hoverinfo:"text", showlegend:false, yaxis:"y2"
    };
  }

  function baseLayout(prot_len, yaxisTitle, yaxisRange){
    return {
      xaxis:{ range:[0, prot_len+5], showgrid:false, zeroline:false, tickfont:{size:10,color:"#6f6c63"} },
      yaxis:{ title:yaxisTitle, range:yaxisRange, domain:[0.30,1],
              showgrid:true, gridcolor:"rgba(0,0,0,0.06)",
              zeroline:true, zerolinecolor:"rgba(0,0,0,0.15)", zerolinewidth:1,
              tickfont:{size:10,color:"#6f6c63"} },
      yaxis2:{ range:[-1.5,1.5], domain:[0,0.20], showticklabels:false, showgrid:false, zeroline:false, overlaying:"free", anchor:"x" },
      shapes: domainShapes(prot_len),
      annotations: domainAnnotations(),
      hovermode:"closest",
      margin:{ l:60, r:30, t:10, b:40 },
      font: PLOT_FONT,
      showlegend:false
    };
  }

  const PLOTLY_CONFIG = { displaylogo:false, responsive:true, modeBarButtonsToRemove:["lasso2d"] };

  function renderDomainChart(data){
    const prot_len = data.length;
    const variants = data.variants || [];
    const traces = [];

    ["likely_pathogenic","ambiguous","likely_benign"].forEach(cls=>{
      const sub = variants.filter(v=>v.am_class===cls);
      if(sub.length===0) return;
      traces.push({
        type:"scatter", mode:"markers", name:AM_LABELS[cls],
        x: sub.map(v=>v.position + AM_OFFSETS[cls]),
        y: sub.map(v=>v.mean_score),
        marker:{ color:AM_COLORS[cls], size:8, opacity:0.85, line:{color:"#fff",width:0.8} },
        error_y:{ type:"data", symmetric:false, array:sub.map(()=>0), arrayminus:sub.map(v=>v.mean_score),
                  color:AM_COLORS[cls], thickness:1.1, width:0 },
        customdata: sub.map(v=>v.position),
        text: sub.map(v=>`<b>${AM_LABELS[cls]}</b><br>Position: ${v.position}<br>Variants at site: ${v.n_vars}<br>Protein variants: ${v.variants}`),
        hovertemplate:"%{text}<extra></extra>",
        yaxis:"y"
      });
    });

    const hoverDom = domainHoverTrace();
    if(hoverDom) traces.push(hoverDom);

    const layout = Object.assign(baseLayout(prot_len, "AlphaMissense pathogenicity (mean)", [-0.02,1.05]));
    layout.height = 300;

    Plotly.newPlot("domainChart", traces, layout, PLOTLY_CONFIG);
    wireChartEvents("domainChart");
  }

  function renderLLPSChart(data){
    const prot_len = data.length;
    const res = (data.llps && data.llps.residues) || [];
    if(res.length===0){ Plotly.newPlot("llpsChart", [], {height:260, font:PLOT_FONT}, PLOTLY_CONFIG); return; }

    const pos = res.map(r=>r.position), score = res.map(r=>r.score);
    const cmin = Math.min(...score), cmax = Math.max(...score);
    state.llpsRange = [cmin, cmax];

    const fillPos = { x:[...pos, ...pos.slice().reverse()],
                       y:[...score.map(s=>Math.max(s,0)), ...pos.map(()=>0)],
                       type:"scatter", mode:"none", fill:"toself", fillcolor:"rgba(153,60,29,0.08)",
                       hoverinfo:"none", showlegend:false, yaxis:"y" };
    const fillNeg = { x:[...pos, ...pos.slice().reverse()],
                       y:[...score.map(s=>Math.min(s,0)), ...pos.map(()=>0)],
                       type:"scatter", mode:"none", fill:"toself", fillcolor:"rgba(15,110,86,0.08)",
                       hoverinfo:"none", showlegend:false, yaxis:"y" };

    const line = {
      x:pos, y:score, type:"scatter", mode:"lines+markers",
      line:{ color:"rgba(120,120,120,0.5)", width:1.2 },
      text: res.map(r=>`<b>${r.aa}</b> \u00b7 position ${r.position}<br>Score: <b>${(r.score>=0?"+":"")+r.score.toFixed(3)}</b>`),
      hoverinfo:"text", showlegend:false, yaxis:"y",
      marker:{ size:5, color:score, colorscale:LLPS_STOPS, cmin, cmax, showscale:true,
               line:{color:"#fff",width:0.5},
               colorbar:{ title:{text:"Residue<br>score", font:{size:10,color:"#6f6c63"}}, thickness:12, len:0.55,
                          tickformat:".1f", tickfont:{size:9,color:"#6f6c63"}, outlinewidth:0, y:0.65 } }
    };

    const traces = [fillPos, fillNeg, line];
    const hoverDom = domainHoverTrace();
    if(hoverDom) traces.push(hoverDom);

    const layout = baseLayout(prot_len, "Residue LLPS score", [cmin-0.05, cmax+0.05]);
    layout.height = 260;
    layout.margin.r = 80;

    Plotly.newPlot("llpsChart", traces, layout, PLOTLY_CONFIG);
    wireChartEvents("llpsChart");
  }

  function renderIUPredChart(data){
    const prot_len = data.length;
    const res = (data.iupred && data.iupred.residues) || [];
    if(res.length===0){ Plotly.newPlot("iupredChart", [], {height:260, font:PLOT_FONT}, PLOTLY_CONFIG); return; }

    const pos = res.map(r=>r.position), score = res.map(r=>r.score);
    const THRESH = 0.5;

    const threshLine = { x:[0, prot_len+1], y:[THRESH,THRESH], type:"scatter", mode:"lines",
                          line:{color:"#c45000", width:1.2, dash:"dash"}, hoverinfo:"none", showlegend:false, yaxis:"y" };
    const fill = { x:[...pos, ...pos.slice().reverse()],
                   y:[...score.map(s=>Math.max(s,THRESH)), ...pos.map(()=>THRESH)],
                   type:"scatter", mode:"none", fill:"toself", fillcolor:"rgba(196,80,0,0.08)",
                   hoverinfo:"none", showlegend:false, yaxis:"y" };
    const line = {
      x:pos, y:score, type:"scatter", mode:"lines+markers",
      line:{ color:"rgba(120,120,120,0.5)", width:1.2 },
      text: res.map(r=>`<b>${r.aa}</b> \u00b7 position ${r.position}<br>IUPred score: <b>${r.score.toFixed(4)}</b><br>${r.score>=THRESH?"Disordered":"Ordered"}`),
      hoverinfo:"text", showlegend:false, yaxis:"y",
      marker:{ size:5, color:score, colorscale:IUPRED_STOPS, cmin:0, cmax:1, showscale:true,
               line:{color:"#fff",width:0.5},
               colorbar:{ title:{text:"IUPred<br>score", font:{size:10,color:"#6f6c63"}}, thickness:12, len:0.55,
                          tickvals:[0,0.25,0.5,0.75,1], tickfont:{size:9,color:"#6f6c63"}, outlinewidth:0, y:0.65 } }
    };

    const traces = [threshLine, fill, line];
    const hoverDom = domainHoverTrace();
    if(hoverDom) traces.push(hoverDom);

    const layout = baseLayout(prot_len, "IUPred3 disorder score", [-0.02,1.05]);
    layout.height = 260;
    layout.margin.r = 80;

    Plotly.newPlot("iupredChart", traces, layout, PLOTLY_CONFIG);
    wireChartEvents("iupredChart");
  }

  function wireChartEvents(divId){
    const gd = el(divId);

    gd.on("plotly_hover", (ev)=>{
      const pt = ev.points && ev.points[0];
      if(!pt) return;
      const pos = (pt.customdata !== undefined) ? pt.customdata : Math.round(pt.x);
      highlightResidue(pos);
    });
    gd.on("plotly_unhover", ()=> highlightResidue(null));

    gd.on("plotly_click", (ev)=>{
      const pt = ev.points && ev.points[0];
      if(!pt) return;
      const pos = (pt.customdata !== undefined) ? pt.customdata : Math.round(pt.x);
      lockResidue(pos);
    });

    gd.on("plotly_relayout", (ev)=>{
      if(state.syncing) return;
      const x0 = ev["xaxis.range[0]"], x1 = ev["xaxis.range[1]"];
      if(x0 !== undefined && x1 !== undefined){
        syncRange(divId, [x0,x1]);
      }else if(ev["xaxis.autorange"]){
        syncRange(divId, null);
      }
    });
  }

  function syncRange(sourceId, range){
    resetLock();
    state.syncing = true;
    state.charts.filter(id=>id!==sourceId).forEach(id=>{
      const gd = el(id);
      if(!gd || !gd.data || gd.data.length===0) return;
      if(range){ Plotly.relayout(gd, {"xaxis.range":range}); }
      else{ Plotly.relayout(gd, {"xaxis.autorange":true}); }
    });
    state.syncing = false;

    if(state.viewer && state.data){
      if(range){
        const start = Math.max(1, Math.round(range[0]));
        const end   = Math.min(state.data.length, Math.round(range[1]));
        zoomStructureToRange(start, end);
      }else{
        state.viewer.zoomTo();
        state.viewer.render();
      }
    }
  }

  /* =================================================================================
     3D STRUCTURE VIEWER
  ================================================================================= */
  function initViewer(pdbText, data){
    el("viewportEmpty") && el("viewportEmpty").remove();
    if(state.viewer){ 
      state.viewer.clear(); 
    } else {
      state.viewer = $3Dmol.createViewer(el("viewport"), { backgroundColor: "#0d1024" });
    }
    state.viewer.addModel(pdbText, "pdb");
    applyStructureColor();
    state.viewer.zoomTo();
    state.viewer.render();
    renderGradientLegend();

    setTimeout(()=>{
      if(!state.viewer) return;
      state.viewer.resize();
      state.viewer.zoomTo();
      state.viewer.render();
    }, 60);
  }

  window.addEventListener("resize", ()=>{
    if(state.viewer){ state.viewer.resize(); state.viewer.render(); }
  });

  function colorForResidue(pos){
    const data = state.data;
    if(state.colorMode === "domain"){
      const d = state.domainColors.find(d => pos >= d.start && pos <= d.end);
      return d ? d.stroke : "#5b6072";
    }
    if(state.colorMode === "iupred"){
      const res = (data.iupred && data.iupred.residues) || [];
      const r = res[pos-1] && res[pos-1].position===pos ? res[pos-1] : res.find(r=>r.position===pos);
      if(!r) return "#5b6072";
      return scaleColor(IUPRED_STOPS, r.score, 0, 1);
    }
    if(state.colorMode === "llps"){
      const res = (data.llps && data.llps.residues) || [];
      const r = res[pos-1] && res[pos-1].position===pos ? res[pos-1] : res.find(r=>r.position===pos);
      if(!r) return "#5b6072";
      return scaleColor(LLPS_STOPS, r.score, state.llpsRange[0], state.llpsRange[1]);
    }
    return "#5b6072";
  }

  function applyStructureColor(){
    if(!state.viewer) return;
    state.viewer.setStyle({}, { cartoon: { colorfunc: (atom)=> colorForResidue(atom.resi) } });
    const active = state.lockedResidue !== null ? state.lockedResidue : state.hoverResidue;
    if(active !== null){
      state.viewer.addStyle({ resi: active }, { cartoon:{color:"#ffd940"}, sphere:{radius:1.4, color:"#ffd940"} });
    }
    state.viewer.render();
  }

  function highlightResidue(pos){
    if(state.lockedResidue !== null) return;
    state.hoverResidue = pos;
    applyStructureColor();

    clearTimeout(state.hoverZoomTimer);
    if(pos === null) return;
    state.hoverZoomTimer = setTimeout(()=> zoomToResidueWindow(pos), HOVER_ZOOM_DELAY);
  }

  function lockResidue(pos){
    if(state.lockedResidue === pos){ resetLock(); return; }
    clearTimeout(state.hoverZoomTimer);
    state.lockedResidue = pos;
    state.hoverResidue = pos;
    applyStructureColor();
    zoomToResidueWindow(pos, 0);
    showLockBadge(pos);
  }

  function resetLock(){
    clearTimeout(state.hoverZoomTimer);
    state.lockedResidue = null;
    state.hoverResidue = null;
    applyStructureColor();
    hideLockBadge();
  }

  function resetStructureView(){
    resetLock();
    if(state.viewer){ state.viewer.zoomTo(); state.viewer.render(); }
  }

  function zoomToResidueWindow(pos, delay = ZOOM_DURATION){
    if(!state.viewer || !state.data) return;
    const start = Math.max(1, pos - HOVER_WINDOW);
    const end   = Math.min(state.data.length, pos + HOVER_WINDOW);
    const sel = { resi: [] };
    for(let i=start;i<=end;i++) sel.resi.push(i);
    state.viewer.zoomTo(sel, delay);
    state.viewer.render();
  }

  function zoomStructureToRange(start, end){
    const sel = { resi: [] };
    for(let i=start;i<=end;i++) sel.resi.push(i);
    state.viewer.zoomTo(sel, ZOOM_DURATION);
    state.viewer.render();
  }

  function showLockBadge(pos){
    el("lockBadge").classList.add("visible");
    el("lockBadgeText").textContent = `locked on residue ${pos}`;
    el("structureHint").style.display = "none";
  }
  function hideLockBadge(){
    el("lockBadge").classList.remove("visible");
    el("structureHint").style.display = "";
  }

  function renderDomainLegend(data){
    const wrap = el("domainLegend");
    wrap.innerHTML = "";
    state.domainColors.forEach(d=>{
      const tag = document.createElement("span");
      tag.className = "tag";
      tag.style.background = d.stroke + "33";
      tag.style.color = "#eef0fb";
      tag.style.border = `1px solid ${d.stroke}`;
      tag.textContent = d.name || d.accession;
      tag.title = `${d.name || d.accession} (${d.start}-${d.end})`;
      wrap.appendChild(tag);
    });
  }

  function renderGradientLegend(){
    const wrap = el("gradientLegend");
    wrap.innerHTML = "";
    const bar = document.createElement("div");
    bar.className = "gradient-bar";
    let min, max, stops;
    if(state.colorMode === "domain"){
      wrap.appendChild(document.createTextNode("colored by domain region"));
      return;
    }else if(state.colorMode === "iupred"){
      stops = IUPRED_STOPS; min = 0; max = 1;
    }else{
      stops = LLPS_STOPS; min = state.llpsRange[0]; max = state.llpsRange[1];
    }
    const grad = stops.map(s=>`${s[1]} ${s[0]*100}%`).join(", ");
    bar.style.background = `linear-gradient(90deg, ${grad})`;
    const lo = document.createElement("span"); lo.textContent = min.toFixed(1);
    const hi = document.createElement("span"); hi.textContent = max.toFixed(1) + (state.colorMode==="llps" ? " (prone)" : "");
    wrap.appendChild(lo); wrap.appendChild(bar); wrap.appendChild(hi);
  }

  el("colorByOpts").addEventListener("click", (e)=>{
    const label = e.target.closest("label");
    if(!label) return;
    state.colorMode = label.dataset.mode;
    document.querySelectorAll("#colorByOpts label").forEach(l=>l.classList.toggle("active", l===label));
    applyStructureColor();
    renderGradientLegend();
  });

  el("resetViewBtn").addEventListener("click", resetStructureView);

  /* =================================================================================
     HEADER WIRING
  ================================================================================= */
  el("loadBtn").addEventListener("click", ()=> loadProtein(el("proteinInput").value));
  el("proteinInput").addEventListener("keydown", (e)=>{ if(e.key==="Enter") loadProtein(el("proteinInput").value); });
  document.querySelectorAll(".sub-breadcrumb a").forEach(a=>{
    if(a.dataset.jump) {
      a.addEventListener("click", ()=> el(a.dataset.jump).scrollIntoView({behavior:"smooth", block:"start"}));
    }
  });

  /* =================================================================================
     AUTOSUGGEST LOGIC
  ================================================================================= */
  (function setupAutocomplete() {
    const input = el("proteinInput");
    const searchContainer = document.querySelector(".search-container");
    
    const suggestionsBox = document.createElement("div");
    suggestionsBox.id = "proteinSuggestions";
    suggestionsBox.className = "autocomplete-suggestions";
    searchContainer.appendChild(suggestionsBox);

    let debounceTimer;
    let currentFocus = -1;

    function closeSuggestions() {
      suggestionsBox.innerHTML = "";
      suggestionsBox.style.display = "none";
      currentFocus = -1;
    }

    function addActive(items) {
      if (!items) return false;
      removeActive(items);
      if (currentFocus >= items.length) currentFocus = 0;
      if (currentFocus < 0) currentFocus = items.length - 1;
      items[currentFocus].classList.add("active");
      items[currentFocus].scrollIntoView({ block: "nearest" });
    }

    function removeActive(items) {
      for (let i = 0; i < items.length; i++) {
        items[i].classList.remove("active");
      }
    }

    input.addEventListener("input", function() {
      clearTimeout(debounceTimer);
      const query = this.value.trim();

      if (query.length < 2) {
        closeSuggestions();
        return;
      }

      debounceTimer = setTimeout(() => {
        const suggestUrl = API_BASE.replace("php-file-based/api", "index.php?ajax_suggest=") + encodeURIComponent(query);
        
        fetch(suggestUrl)
          .then(response => {
            if (!response.ok) throw new Error();
            return response.json();
          })
          .then(data => {
            if (data && data.length > 0) {
              suggestionsBox.innerHTML = "";
              data.forEach(item => {
                const div = document.createElement("div");
                div.className = "autocomplete-suggestion";
                
                const regex = new RegExp(`(${query})`, "gi");
                div.innerHTML = item.replace(regex, "<strong>$1</strong>");
                
                div.addEventListener("click", function() {
                  input.value = item;
                  closeSuggestions();
                  loadProtein(item);
                });
                
                suggestionsBox.appendChild(div);
              });
              suggestionsBox.style.display = "block";
            } else {
              closeSuggestions();
            }
          })
          .catch(() => {
            closeSuggestions();
          });
      }, 250);
    });

    input.addEventListener("keydown", function(e) {
      const items = suggestionsBox.getElementsByClassName("autocomplete-suggestion");
      if (suggestionsBox.style.display === "block" && items.length > 0) {
        if (e.key === "ArrowDown") {
          currentFocus++;
          addActive(items);
          e.preventDefault();
        } else if (e.key === "ArrowUp") {
          currentFocus--;
          addActive(items);
          e.preventDefault();
        } else if (e.key === "Enter") {
          if (currentFocus > -1) {
            if (items[currentFocus]) {
              items[currentFocus].click();
              e.preventDefault();
              e.stopImmediatePropagation();
            }
          } else {
            closeSuggestions();
          }
        } else if (e.key === "Escape") {
          closeSuggestions();
        }
      }
    });

    document.addEventListener("click", function(e) {
      if (e.target !== input && e.target !== suggestionsBox) {
        closeSuggestions();
      }
    });
  })();

  /* =================================================================================
     BOOTSTRAP - autoload from ?id=XXXX if present
  ================================================================================= */
  (function bootstrap(){
    const params = new URLSearchParams(window.location.search);
    const idParam = params.get("id");
    if(idParam){
      el("proteinInput").value = idParam;
      loadProtein(idParam);
    }
  })();
});
</script>

<?php 
include 'footer.php';
?>