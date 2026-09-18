<?php
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inter-Tissue Communication</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

<style>
  :root {
    --heart-atrial: #ef5777;
    --heart-atrial-soft: #fde6ea;
    --heart-ventricle: #d63031;
    --heart-ventricle-soft: #fbebe8;
    --hypo: #17b89a;
    --hypo-soft: #dff5f0;
    --lung: #ff793f;
    --lung-soft: #fff1eb;
    --liver: #a55eea;
    --liver-soft: #f5effd;
    --kidney: #20bf6b;
    --kidney-soft: #e8f8f0;
    --sk-muscle: #e67e22;
    --sk-muscle-soft: #fdf2e9;
    --both: #8c6fe0;
    --both-soft: #ece6fb;
    --hub: #f0a93c;
    --ink: #1c2230;
    --muted: #626d7f;
    --line: #e2e5ec;
    --canvas: #f5f6fa;
    --card: #ffffff;
  }

  * { box-sizing: border-box; }

  body {
    background: var(--canvas);
    color: var(--ink);
    font-family: 'Inter', "Segoe UI", Roboto, system-ui, -apple-system, sans-serif;
  }

  .custom-wide-container {
    width: 100% !important;
    max-width: 98% !important;
    margin: 0 auto;
  }

  .dash-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 24px;
    margin-top: 22px;
    align-items: start;
  }
  @media (max-width: 992px) {
    .dash-grid { grid-template-columns: 1fr; }
  }

  .anatomy-panel { padding: 15px; }
  .anatomy-help { color: var(--muted); font-size: 13px; line-height: 1.5; }

  /* Badges */
  .selection-badge {
    display: inline-flex; align-items:center; gap:7px;
    padding: 7px 12px; border-radius: 999px;
    font-size: 0.8rem; font-weight: 600;
    border: 1.5px solid var(--line); background: var(--canvas); color: var(--muted);
  }
  .selection-badge .tag-label { font-size: 9.5px; font-weight: 500; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.04em; margin-right: 2px; }

  .badge-heart_atrial     { background: var(--heart-atrial-soft); border-color: var(--heart-atrial); color: #a8264a; }
  .badge-heart_ventricle  { background: var(--heart-ventricle-soft); border-color: var(--heart-ventricle); color: #801010; }
  .badge-hypothalamus     { background: var(--hypo-soft);  border-color: var(--hypo);  color: #0e7a64; }
  .badge-lung             { background: var(--lung-soft);  border-color: var(--lung);  color: #cd4d1d; }
  .badge-liver            { background: var(--liver-soft); border-color: var(--liver); color: #6f2dbd; }
  .badge-kidney           { background: var(--kidney-soft); border-color: var(--kidney); color: #0e7a46; }
  .badge-skeletal_muscle  { background: var(--sk-muscle-soft); border-color: var(--sk-muscle); color: #a04000; }

  .anatomy-svg-wrapper {
    width: 100%;
    display:flex;
    justify-content:center;
    background: #f8fafc;
    border-radius: 16px;
    padding: 14px 8px;
    border: 1px solid var(--line);
  }
  .anatomy-svg { width: 100%; max-width: 320px; height: auto; display: block; }

  .silhouette-bg { fill: #eaedf3; stroke: #cbd5e1; stroke-width: 1.5; }
  .silhouette-head-ring { fill: #ffffff; stroke: #d9e1ea; stroke-width: 2; }
  .silhouette-head-outer { fill: #f0f4f8; stroke: #cbd5e1; stroke-width: 1.5; }

  .organ-shape {
    fill: #b9bfc9;
    stroke: #ffffff;
    stroke-width: 1.5;
    transition: fill 0.2s ease, transform 0.2s ease, filter 0.2s ease;
  }
  .organ-node { cursor: pointer; }
  .organ-node:hover .organ-shape { filter: brightness(1.08) drop-shadow(0 2px 4px rgba(0,0,0,0.15)); }

  .organ-node[data-tissue="heart_atrial"] .organ-shape { fill: #ff758f; }
  .organ-node[data-tissue="heart_ventricle"] .organ-shape { fill: #e63946; }
  .organ-node[data-tissue="hypothalamus"] .organ-shape { fill: #17b89a; }
  .organ-node[data-tissue="lung"] .organ-shape { fill: #ffaa80; }
  .organ-node[data-tissue="liver"] .organ-shape { fill: #c7a4f9; }
  .organ-node[data-tissue="kidney"] .organ-shape { fill: #8ce3b4; }
  .organ-node[data-tissue="skeletal_muscle"] .organ-shape { fill: #fbc687; }

  .organ-node.organ-active .organ-shape { transform: scale(1.1); filter: drop-shadow(0 2px 6px rgba(0,0,0,0.25)); }
  .organ-node[data-tissue="heart_atrial"].organ-active .organ-shape { fill: var(--heart-atrial); stroke: #ffffff; stroke-width: 2; }
  .organ-node[data-tissue="heart_ventricle"].organ-active .organ-shape { fill: var(--heart-ventricle); stroke: #ffffff; stroke-width: 2; }
  .organ-node[data-tissue="hypothalamus"].organ-active .organ-shape { fill: var(--hypo); }
  .organ-node[data-tissue="lung"].organ-active .organ-shape { fill: var(--lung); }
  .organ-node[data-tissue="liver"].organ-active .organ-shape { fill: var(--liver); }
  .organ-node[data-tissue="kidney"].organ-active .organ-shape { fill: var(--kidney); }
  .organ-node[data-tissue="skeletal_muscle"].organ-active .organ-shape { fill: var(--sk-muscle); }

  .blueprint-pointer { stroke: #cbd5e1; stroke-width: 1.2; stroke-dasharray: 3, 3; }
  .blueprint-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    fill: #64748b;
    letter-spacing: 0.02em;
    transition: fill 0.3s ease, font-weight 0.3s ease;
  }
  .blueprint-text.active-heart_atrial { fill: var(--heart-atrial); }
  .blueprint-text.active-heart_ventricle { fill: var(--heart-ventricle); }
  .blueprint-text.active-hypothalamus { fill: var(--hypo); }
  .blueprint-text.active-lung { fill: var(--lung); }
  .blueprint-text.active-liver { fill: var(--liver); }
  .blueprint-text.active-kidney { fill: var(--kidney); }
  .blueprint-text.active-skeletal_muscle { fill: var(--sk-muscle); }

  .anatomy-footnote {
    margin-top: 14px; font-size: 11.5px; color: var(--muted);
    background: var(--canvas); border: 1px dashed var(--line);
    border-radius: 8px; padding: 10px 12px; line-height: 1.45;
  }
  .anatomy-footnote b { color: var(--ink); }

  .workspace-panel { padding: 18px; display:flex; flex-direction:column; min-height: 720px; }
  .workspace-head { display:flex; align-items:center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px; }
  .workspace-head h5 { font-family:'Sora', sans-serif; font-weight:600; font-size:1.02rem; margin:0; display:flex; align-items:center; gap:8px; }

  .slot-row { display:flex; gap: 10px; flex-wrap: wrap; }

  #perf-note {
    display: none;
    font-size: 11.5px; color: #8a5a00; background: #fff6e0;
    border: 1px dashed #e9c46a; border-radius: 8px;
    padding: 7px 11px; margin-bottom: 12px; line-height: 1.4;
  }
  #perf-note b { color: #6b4500; }

  #cy-wrap {
    position: relative;
    flex: 1 1 auto;
    min-height: 560px;
    border-radius: 14px;
    background-color: #fafbfc;
    background-image:
      linear-gradient(to right, rgba(0, 0, 0, 0.02) 1px, transparent 1px),
      linear-gradient(to bottom, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
    background-size: 20px 20px;
    border: 1px solid var(--line);
    overflow: hidden;
  }
  #cy { position: absolute; inset: 0; }

  #cy-empty {
    position: absolute; inset: 0; display:flex; align-items:center; justify-content:center;
    flex-direction: column; gap: 10px; color: var(--muted); text-align:center; padding: 20px;
  }
  #cy-empty i { font-size: 2.4rem; color: #cfd3da; }

  .cy-loading {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    color: var(--muted); font-size: 0.85rem; gap:8px;
  }
  .cy-error {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    flex-direction: column; gap: 8px; color: #b3261e; font-size: 0.85rem; text-align:center; padding: 20px;
  }

  .controls-row { display:grid; grid-template-columns: 1.3fr 1fr 1fr 1.2fr; gap: 12px; margin-top: 14px; }
  @media (max-width: 1180px) { .controls-row { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 620px)  { .controls-row { grid-template-columns: 1fr; } }

  .control-card {
    background: var(--canvas); border: 1px solid var(--line); border-radius: 12px;
    padding: 12px 13px;
  }
  .control-card h6 {
    font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--muted); font-weight: 700; margin-bottom: 9px;
  }

  .weight-readout { display:flex; align-items:center; justify-content:space-between; margin-bottom:2px; }
  .weight-readout .v { font-family:'JetBrains Mono', monospace; font-size:11.5px; background:#fff; border:1px solid var(--line); border-radius:6px; padding:1px 7px; color: var(--ink); }

  .switch-row { display:flex; align-items:center; gap:8px; margin-bottom: 6px; font-size: 0.83rem; }
  .swatch { width: 11px; height: 11px; border-radius: 3px; flex-shrink:0; }

  .legend-list { display:flex; flex-direction:column; gap: 7px; font-size: 0.8rem; }
  .legend-row { display:flex; align-items:center; gap: 9px; }

  .legend-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; box-sizing: border-box; }
  .legend-dot.fill-both  { background: var(--both); }

  .legend-dot.hub-single { background: #ffffff; border: 2.5px solid var(--hub); }
  .legend-dot.hub-double { background: #ffffff; border: 2px solid var(--hub); box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px var(--hub); }

  .partner-empty { font-size: 0.8rem; color: var(--muted); }
  .partner-meta { font-size: 11px; font-weight: 700; color: var(--heart-atrial); margin-bottom: 6px; }
  .partner-table { width: 100%; font-size: 12px; border-collapse: collapse; }
  .partner-table th { text-align:left; color: var(--muted); font-weight:600; font-size: 10.5px; text-transform:uppercase; letter-spacing:0.03em; padding-bottom: 4px; border-bottom: 1px solid var(--line); }
  .partner-table td { padding: 4px 0; border-bottom: 1px solid #f0f1f4; }
  .pill { padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 600; }

  #reset-btn { border-radius: 8px; font-size: 0.8rem; }
  #search-box { font-size: 0.85rem; }

  .layout-switch-wrap { display:flex; align-items:center; gap:8px; font-size: 0.8rem; color: var(--ink); }
  .layout-switch-wrap .form-check-input { width: 2.3em; height: 1.2em; cursor: pointer; }
  .layout-switch-wrap .form-check-input:checked { background-color: var(--heart-atrial); border-color: var(--heart-atrial); }
  .layout-switch-wrap label { cursor: pointer; margin-bottom: 0; font-weight: 600; }

  .go-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: #f8f9fa;
    color: var(--muted);
    font-weight: 600;
  }
  .go-gene-pill {
    background: #f1f3f7;
    border: 1px solid var(--line);
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    font-family: 'JetBrains Mono', monospace;
    margin-right: 3px;
    margin-bottom: 3px;
    display: inline-block;
    color: var(--muted);
    transition: all 0.25s ease;
  }
  .go-gene-pill.go-pill-active {
    background: #ffecb3 !important;
    border-color: #ffc107 !important;
    color: #5d4037 !important;
    font-weight: bold;
    transform: scale(1.05);
  }
</style>
</head>
<body>

<div class="custom-wide-container pb-4 pt-0">

  <!-- HEADER ROW -->
  <div class="row align-items-center mb-4">
    <div class="col-md-8 col-lg-9 text-start">
      <h1 style="font-family: 'Segoe UI', sans-serif; font-size: 40px; font-weight: 700; color: #212529; margin-top: 0; padding-top: 0; margin-bottom: 5px;">
        Inter-Tissue Communication
      </h1>
      <p id="dash-sub" style="font-family: 'Segoe UI', sans-serif; font-size: 20px; font-weight: 400; color: #212529; line-height: 1.5; margin-bottom: 0;">
        Click two tissues on the body map below to analyze co-expression networks, trace circadian coordination hubs, and isolate inter-tissue signaling vectors.
      </p>
    </div>
    <div class="col-md-4 col-lg-3 text-md-end text-start mt-3 mt-md-0">
      <a href="https://datascience.imtech.res.in/anshu/circanet/Videos/inter-tiisue_communication.mp4" target="_blank" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold" style="border-width: 2px;">
        <i class="fas fa-video me-2"></i> Watch Video Tutorial
      </a>
    </div>
  </div>

  <!-- GRAPHICAL ABSTRACT IMAGE CARD -->
  <div class="card shadow-sm mb-4">
      <div class="card-body text-center py-4 bg-white rounded">
          <img src="GA/GA6.png" alt="Graphical Abstract" style="max-width: 100%; height: auto; display: inline-block;">
      </div>
  </div>

  <div class="dash-grid">

    <!-- LEFT PANEL: TISSUE SELECTOR -->
    <div class="card shadow-sm h-100">
      <div class="card-header bg-light">
          <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-standing me-2"></i>Tissue Selector</h5>
      </div>
      <div class="card-body anatomy-panel">
          <p class="anatomy-help">Heart (Atrial), Heart (Ventricle), Hypothalamus, Lung, Liver, Kidney, and Skeletal Muscle datasets are loaded. Click any two to compare them.</p>

          <div class="anatomy-svg-wrapper mb-3">
            <svg class="anatomy-svg" viewBox="0 0 320 410" xmlns="http://www.w3.org/2000/svg">
              
              <!-- Head Silhouette & Concentric Ring -->
              <g transform="translate(145, 45)">
                <circle cx="0" cy="0" r="24" class="silhouette-head-outer" />
                <circle cx="0" cy="0" r="19" class="silhouette-head-ring" />
              </g>

              <!-- Hypothalamus Node -->
              <g class="organ-node" data-tissue="hypothalamus" transform="translate(145, 45)">
                <circle cx="0" cy="0" r="26" fill="transparent" pointer-events="all" />
                <circle cx="0" cy="0" r="15" class="organ-shape" />
                <line x1="0" y1="-14" x2="0" y2="14" stroke="#ffffff" stroke-width="1.5" opacity="0.85" />
                <circle cx="0" cy="0" r="7" fill="none" stroke="#ffffff" stroke-width="1.2" opacity="0.85" />
              </g>
              <line x1="172" y1="45" x2="208" y2="45" class="blueprint-pointer" />
              <text x="212" y="49" class="blueprint-text" id="label-hypothalamus">HYPOTHALAMUS</text>

              <!-- Smooth Rounded Body Silhouette -->
              <path class="silhouette-bg" d="
                M 125,95
                C 112,100 95,112 90,135
                C 85,160 88,210 93,260
                C 96,295 98,345 98,385
                L 192,385
                C 192,345 194,295 197,260
                C 202,210 205,160 200,135
                C 195,112 178,100 165,95
                Z" />

              <!-- Lungs (Positioned Upper Chest) -->
              <g class="organ-node" data-tissue="lung" transform="translate(145, 142)">
                <circle cx="0" cy="0" r="30" fill="transparent" pointer-events="all" />
                <!-- Left Lobe -->
                <path class="organ-shape" d="M -4,-26 C -18,-26 -28,-14 -26,10 C -24,25 -10,30 -4,30 C 0,30 0,10 0,-4 C 0,-18 -1,-26 -4,-26 Z" />
                <!-- Right Lobe -->
                <path class="organ-shape" d="M 4,-26 C 18,-26 28,-14 26,10 C 24,25 10,30 4,30 C 0,30 0,10 0,-4 C 0,-18 1,-26 4,-26 Z" />
              </g>
              <line x1="176" y1="140" x2="208" y2="140" class="blueprint-pointer" />
              <text x="212" y="144" class="blueprint-text" id="label-lung">LUNGS</text>

              <!-- Heart (Atrial - Upper Semicircle) -->
              <g class="organ-node" data-tissue="heart_atrial" transform="translate(145, 137)">
                <rect x="-16" y="-14" width="32" height="15" fill="transparent" pointer-events="all" />
                <path class="organ-shape" d="M -11,0 A 11,11 0 0,1 11,0 Z" />
              </g>
              <line x1="132" y1="132" x2="108" y2="132" class="blueprint-pointer" />
              <text x="5" y="136" class="blueprint-text" id="label-heart_atrial">HEART (ATRIAL)</text>

              <!-- Heart (Ventricle - Lower Semicircle) -->
              <g class="organ-node" data-tissue="heart_ventricle" transform="translate(145, 145)">
                <rect x="-16" y="-1" width="32" height="15" fill="transparent" pointer-events="all" />
                <path class="organ-shape" d="M -11,0 A 11,11 0 0,0 11,0 Z" />
              </g>
              <line x1="132" y1="152" x2="88" y2="152" class="blueprint-pointer" />
              <text x="5" y="156" class="blueprint-text" id="label-heart_ventricle">HEART (VENTRICLE)</text>

              <!-- Liver (Shifted slightly to the right) -->
              <g class="organ-node" data-tissue="liver" transform="translate(152, 192)">
                <rect x="-26" y="-12" width="52" height="26" fill="transparent" pointer-events="all" />
                <path class="organ-shape" d="M -16,-6 L 18,-6 C 20,-6 16,14 2,12 C -13,10 -16,-6 -16,-6 Z" />
              </g>
              <line x1="134" y1="192" x2="48" y2="192" class="blueprint-pointer" />
              <text x="5" y="196" class="blueprint-text" id="label-liver">LIVER</text>

              <!-- Kidneys -->
              <g class="organ-node" data-tissue="kidney" transform="translate(145, 235)">
                <rect x="-35" y="-16" width="70" height="32" fill="transparent" pointer-events="all" />
                <!-- Left Kidney -->
                <path class="organ-shape" d="M -15,-10 C -20,-10 -22,-4 -22,2 C -22,8 -18,12 -14,12 C -11,12 -10,8 -12,2 C -13,-4 -12,-10 -15,-10 Z" />
                <!-- Right Kidney -->
                <path class="organ-shape" d="M 15,-10 C 20,-10 22,-4 22,2 C 22,8 18,12 14,12 C 11,12 10,8 12,2 C 13,-4 12,-10 15,-10 Z" />
              </g>
              <line x1="172" y1="235" x2="208" y2="235" class="blueprint-pointer" />
              <text x="212" y="239" class="blueprint-text" id="label-kidney">KIDNEYS</text>

              <!-- Skeletal Muscle -->
              <g class="organ-node" data-tissue="skeletal_muscle" transform="translate(122, 310)">
                <rect x="-18" y="-28" width="36" height="56" rx="10" fill="transparent" pointer-events="all" />
                <path class="organ-shape" d="M -7,-20 C -2,-24 8,-24 10,-20 C 13,-10 9,18 6,22 C 3,25 -5,25 -7,22 C -11,18 -10,-10 -7,-20 Z" />
                <line x1="0" y1="-14" x2="0" y2="14" stroke="#ffffff" stroke-width="1.2" opacity="0.65" />
              </g>
              <line x1="108" y1="310" x2="100" y2="310" class="blueprint-pointer" />
              <text x="5" y="314" class="blueprint-text" id="label-skeletal_muscle">SKELETAL MUSCLE</text>
            </svg>
          </div>

          <div class="anatomy-footnote">
            <b>Tip.</b> Click any two tissues to compare them. Click a selected tissue again to remove it, or click a third tissue to swap out the first one you picked.
          </div>
      </div>
    </div>

    <!-- RIGHT PANEL: WORKSPACE NETWORK -->
    <div class="card shadow-sm h-100">
      <div class="card-body workspace-panel">

          <div class="workspace-head mb-3">
            <h5><i class="bi bi-intersect me-2 text-primary"></i><span id="workspace-title-text">Select two tissues</span></h5>
            <div class="slot-row">
              <span id="slot-1" class="selection-badge"><span class="tag-label">Source</span><span>None</span></span>
              <span id="slot-2" class="selection-badge"><span class="tag-label">Target</span><span>None</span></span>
            </div>
          </div>

          <div id="perf-note"></div>

          <div id="cy-wrap">
            <div id="cy"></div>
            <div id="cy-empty">
              <i class="bi bi-cpu"></i>
              <div><strong>Waiting for tissue selections</strong><br>Click two tissues on the map to load their network.</div>
            </div>
          </div>

          <div class="controls-row">
            <div class="control-card">
              <h6>Search gene &amp; threshold</h6>
              <input type="text" id="search-box" class="form-control form-control-sm mb-2" placeholder="e.g. PER2" list="gene-list">
              <datalist id="gene-list"></datalist>
              <div class="weight-readout">
                <span style="font-size:11px; color:var(--muted); font-weight:600;">MIN CORRELATION</span>
                <span id="weight-value" class="v">0.60</span>
              </div>
              <input type="range" class="form-range" id="weight-slider" min="0.6" max="1" step="0.01" value="0.6">
            </div>

            <div class="control-card">
              <h6>Tissue layers</h6>
              <div class="switch-row">
                <input class="form-check-input" type="checkbox" id="toggle-a" checked style="margin:0;">
                <span class="swatch" id="toggle-a-swatch" style="background:var(--heart-atrial);"></span>
                <label for="toggle-a" id="toggle-a-label">Tissue A edges</label>
              </div>
              <div class="switch-row">
                <input class="form-check-input" type="checkbox" id="toggle-b" checked style="margin:0;">
                <span class="swatch" id="toggle-b-swatch" style="background:var(--hypo);"></span>
                <label for="toggle-b" id="toggle-b-label">Tissue B edges</label>
              </div>
            </div>

            <div class="control-card">
              <h6>Legend</h6>
              <div class="legend-list">
                <div class="legend-row"><span class="legend-dot" id="legend-a-dot" style="background:var(--heart-atrial);"></span><span id="legend-a-label">Tissue A only</span></div>
                <div class="legend-row"><span class="legend-dot" id="legend-b-dot" style="background:var(--hypo);"></span><span id="legend-b-label">Tissue B only</span></div>
                <div class="legend-row"><span class="legend-dot fill-both"></span> Shared, both tissues</div>
                <div class="legend-row"><span class="legend-dot hub-single"></span> Hub, on 1 list</div>
                <div class="legend-row"><span class="legend-dot hub-double"></span> Hub, on both lists</div>
              </div>
            </div>

            <div class="control-card" id="partner-panel" style="max-height: 168px; overflow-y: auto;">
              <h6>Selection inspector</h6>
              <div id="partner-empty" class="partner-empty">Click a node to see its interaction partners.</div>
              <div id="partner-content" style="display:none">
                <div id="partner-meta" class="partner-meta"></div>
                <table class="partner-table" id="partner-table">
                  <thead><tr><th>Partner</th><th>Tissue</th><th>Weight</th></tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end align-items-center gap-3 mt-3 border-top pt-2">
            <div class="layout-switch-wrap form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" role="switch" id="layout-toggle">
              <label class="form-check-label" for="layout-toggle" id="layout-toggle-label">Force layout</label>
            </div>
            <button id="reset-btn" class="btn btn-sm btn-outline-secondary">Reset network view</button>
          </div>

      </div>
    </div>
  </div>

  <!-- GO ENRICHMENT CONTAINER -->
  <div class="card shadow-sm mt-4" id="go-enrichment-container" style="display: none;">
    <div class="card-header bg-light py-3">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>GO Biological Processes Enrichment</h5>
    </div>
    <div class="card-body py-4">
        <div class="row g-4">
          <div class="col-xl-6 col-12">
            <div class="card h-100 border-0 bg-light">
              <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background: none; padding: 12px 16px 8px;">
                <h6 class="m-0 text-dark fw-bold" id="go-table-a-title" style="font-family:'Sora', sans-serif;">Tissue A Biological Processes</h6>
                <span class="badge bg-secondary-subtle text-secondary-emphasis" id="go-table-a-count">0 Terms</span>
              </div>
              <div class="card-body p-2" style="max-height: 480px; overflow-y: auto;">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle bg-white rounded shadow-sm mb-0 go-table" id="go-table-a" style="font-size: 0.8rem; border-collapse: separate;">
                    <thead class="table-light">
                      <tr>
                        <th>Term</th>
                        <th style="width: 70px;">Overlap</th>
                        <th style="width: 100px;">Adj. P-value</th>
                        <th style="width: 90px;">Comb. Score</th>
                        <th>Genes</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-6 col-12">
            <div class="card h-100 border-0 bg-light">
              <div class="card-header border-0 d-flex justify-content-between align-items-center" style="background: none; padding: 12px 16px 8px;">
                <h6 class="m-0 text-dark fw-bold" id="go-table-b-title" style="font-family:'Sora', sans-serif;">Tissue B Biological Processes</h6>
                <span class="badge bg-secondary-subtle text-secondary-emphasis" id="go-table-b-count">0 Terms</span>
              </div>
              <div class="card-body p-2" style="max-height: 480px; overflow-y: auto;">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle bg-white rounded shadow-sm mb-0 go-table" id="go-table-b" style="font-size: 0.8rem; border-collapse: separate;">
                    <thead class="table-light">
                      <tr>
                        <th>Term</th>
                        <th style="width: 70px;">Overlap</th>
                        <th style="width: 100px;">Adj. P-value</th>
                        <th style="width: 90px;">Comb. Score</th>
                        <th>Genes</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.26.0/cytoscape.min.js"></script>

<script>
const TISSUE_META = {
  heart_atrial:     { label: 'Heart (Atrial)',    color: '#ef5777', soft: '#fde6ea', dark: '#a8264a' },
  heart_ventricle:  { label: 'Heart (Ventricle)', color: '#d63031', soft: '#fbebe8', dark: '#801010' },
  hypothalamus:     { label: 'Hypothalamus',      color: '#17b89a', soft: '#dff5f0', dark: '#0e7a64' },
  lung:             { label: 'Lung',              color: '#ff793f', soft: '#fff1eb', dark: '#cd4d1d' },
  liver:            { label: 'Liver',             color: '#a55eea', soft: '#f5effd', dark: '#6f2dbd' },
  kidney:           { label: 'Kidney',            color: '#20bf6b', soft: '#e8f8f0', dark: '#0e7a46' },
  skeletal_muscle:  { label: 'Skeletal Muscle',   color: '#e67e22', soft: '#fdf2e9', dark: '#a04000' }
};
const AVAILABLE_TISSUES = Object.keys(TISSUE_META);

// Mapping for GO Filenames PascalCase conventions
const PASCAL_MAP = {
  heart_atrial: 'HeartAtrial',
  heart_ventricle: 'HeartVentricle',
  hypothalamus: 'Hypothalamus',
  kidney: 'Kidney',
  liver: 'Liver',
  lung: 'Lung',
  skeletal_muscle: 'SkeletalMuscle'
};

// Anatomical ordering used in the GO files pair tags
const TISSUE_ORDER = [
  'heart_atrial',
  'heart_ventricle',
  'hypothalamus',
  'kidney',
  'liver',
  'lung',
  'skeletal_muscle'
];

const DATA_DIR = 'multicens_data_new';

const MAX_INITIAL_EDGES = 2500;
const COSE_NODE_WARN_LIMIT = 800;

let selectedTissues = ['heart_atrial', 'heart_ventricle'];
let cy = null;
let currentPairKey = null;
let currentHubFields = null;
let rawNodes = [];
let rawEdges = [];
let filterRAF = null;

function tissueLabel(t) {
  return (TISSUE_META[t] && TISSUE_META[t].label) || (t.charAt(0).toUpperCase() + t.slice(1));
}

function pairFileKey(t1, t2) {
  return [t1, t2].sort().join('_');
}

function getGOPairTag(t1, t2) {
  const i1 = TISSUE_ORDER.indexOf(t1);
  const i2 = TISSUE_ORDER.indexOf(t2);
  const first = i1 < i2 ? t1 : t2;
  const second = i1 < i2 ? t2 : t1;
  return PASCAL_MAP[first] + '2' + PASCAL_MAP[second];
}

$(document).ready(function () {
  $('.organ-node').on('click', function () {
    const tissue = $(this).attr('data-tissue');
    if (!AVAILABLE_TISSUES.includes(tissue)) return;

    const index = selectedTissues.indexOf(tissue);
    if (index > -1) {
      selectedTissues.splice(index, 1);
    } else {
      if (selectedTissues.length >= 2) selectedTissues.shift();
      selectedTissues.push(tissue);
    }
    updateVisualStates();
  });

  setupControls();
  updateVisualStates();
});

function updateVisualStates() {
  updateOrganHighlights();
  renderSlotBadges();
  renderHeaderLabels();
  renderControlsLabels();

  if (selectedTissues.length === 2) {
    const pairKey = pairFileKey(selectedTissues[0], selectedTissues[1]);
    $('#cy-empty').hide();

    if (pairKey !== currentPairKey) {
      if (cy) { cy.destroy(); cy = null; }
      currentPairKey = pairKey;
      initNetwork(selectedTissues[0], selectedTissues[1]);
      loadGOEnrichment(selectedTissues[0], selectedTissues[1]);
    } else if (cy) {
      $('#cy').show();
      cy.resize();
      cy.fit(undefined, 40);
    }
  } else {
    $('#cy-empty').show();
    if (cy) $('#cy').hide();
    currentPairKey = null;
    $('#go-enrichment-container').hide();
    $('#perf-note').hide();
  }
}

function updateOrganHighlights() {
  $('.organ-node').removeClass('organ-active');
  $('.blueprint-text').removeClass(AVAILABLE_TISSUES.map(t => 'active-' + t).join(' '));
  selectedTissues.forEach(t => {
    $('.organ-node[data-tissue="' + t + '"]').addClass('organ-active');
    $('#label-' + t).addClass('active-' + t);
  });
}

function renderSlotBadges() {
  function badge(tag, tissue) {
    const cls = tissue ? 'selection-badge badge-' + tissue : 'selection-badge';
    const text = tissue ? tissueLabel(tissue) : 'None';
    return { cls: cls, html: '<span class="tag-label">' + tag + '</span><span>' + text + '</span>' };
  }
  const b1 = badge('Source', selectedTissues[0]);
  $('#slot-1').attr('class', b1.cls).html(b1.html);
  const b2 = badge('Target', selectedTissues[1]);
  $('#slot-2').attr('class', b2.cls).html(b2.html);
}

function renderHeaderLabels() {
  if (selectedTissues.length === 2) {
    const a = selectedTissues[0], b = selectedTissues[1];
    const labelA = tissueLabel(a), labelB = tissueLabel(b);
    document.title = 'CircaNet - ' + labelA + ' x ' + labelB + ' Co-expression Network';
    $('#workspace-title-text').text(labelA + ' \u00d7 ' + labelB + ' network');
  } else {
    document.title = 'CircaNet - Pairwise Organ Network';
    $('#workspace-title-text').text('Select two tissues');
  }
}

function renderControlsLabels() {
  if (selectedTissues.length !== 2) {
    $('#legend-a-label').text('Tissue A only');
    $('#legend-b-label').text('Tissue B only');
    $('#toggle-a-label').text('Tissue A edges');
    $('#toggle-b-label').text('Tissue B edges');
    return;
  }
  const a = selectedTissues[0], b = selectedTissues[1];
  $('#legend-a-dot').css('background', TISSUE_META[a].color);
  $('#legend-b-dot').css('background', TISSUE_META[b].color);
  $('#legend-a-label').text(tissueLabel(a) + ' only');
  $('#legend-b-label').text(tissueLabel(b) + ' only');
  $('#toggle-a-swatch').css('background', TISSUE_META[a].color);
  $('#toggle-b-swatch').css('background', TISSUE_META[b].color);
  $('#toggle-a-label').text(tissueLabel(a) + ' edges');
  $('#toggle-b-label').text(tissueLabel(b) + ' edges');
}

async function loadNetworkData(pairKey) {
  const [rn, re] = await Promise.all([
    fetch(DATA_DIR + '/nodes_' + pairKey + '.json'),
    fetch(DATA_DIR + '/edges_' + pairKey + '.json')
  ]);
  if (!rn.ok) throw new Error('nodes_' + pairKey + '.json returned ' + rn.status);
  if (!re.ok) throw new Error('edges_' + pairKey + '.json returned ' + re.status);
  return { nodes: await rn.json(), edges: await re.json() };
}

const CONCENTRIC_LAYOUT_OPTS = {
  name: 'concentric',
  animate: false,
  padding: 36,
  concentric: ele => ele.degree(),
  levelWidth: () => 1,
  minNodeSpacing: 18
};

const COSE_LAYOUT_OPTS = {
  name: 'cose',
  animate: false,
  numIter: 400,
  padding: 36
};

async function initNetwork(tissueA, tissueB) {
  $('#cy').show().html('<div class="cy-loading"><i class="bi bi-arrow-repeat"></i> Loading network...</div>');
  $('#perf-note').hide();

  const layoutToggle = document.getElementById('layout-toggle');
  if (layoutToggle) layoutToggle.checked = false;

  const pairKey = pairFileKey(tissueA, tissueB);
  let nodes, edges;
  try {
    const data = await loadNetworkData(pairKey);
    nodes = data.nodes;
    edges = data.edges;
  } catch (err) {
    console.error('Failed to load network data for', pairKey, err);
    $('#cy').html(
      '<div class="cy-error"><i class="bi bi-exclamation-triangle" style="font-size:1.8rem;"></i>' +
      '<div><strong>Could not load this network.</strong><br>' + err.message + '</div></div>'
    );
    return;
  }

  rawNodes = nodes;
  rawEdges = edges;

  const hubFieldA = 'is_' + tissueA + '_hub';
  const hubFieldB = 'is_' + tissueB + '_hub';
  currentHubFields = { a: hubFieldA, b: hubFieldB, tissueA: tissueA, tissueB: tissueB };

  const slider = document.getElementById('weight-slider');
  slider.value = slider.min;
  const defaultThreshold = parseFloat(slider.min) || 0.6;

  let filteredEdges = edges.filter(e => e.weight >= defaultThreshold);

  const totalAboveThreshold = filteredEdges.length;
  if (totalAboveThreshold > MAX_INITIAL_EDGES) {
    filteredEdges = filteredEdges.slice().sort((a, b) => b.weight - a.weight).slice(0, MAX_INITIAL_EDGES);
    $('#perf-note').show().html(
      '<b>Showing top ' + MAX_INITIAL_EDGES.toLocaleString() + ' strongest edges</b> of ' +
      totalAboveThreshold.toLocaleString() + ' above current threshold, for performance.'
    );
  }

  const activeNodeIds = new Set();
  filteredEdges.forEach(e => { activeNodeIds.add(e.source); activeNodeIds.add(e.target); });
  const filteredNodes = nodes.filter(n => activeNodeIds.has(n.id));

  const datalist = document.getElementById('gene-list');
  datalist.innerHTML = '';
  nodes.map(n => n.id).sort().forEach(id => {
    const opt = document.createElement('option');
    opt.value = id;
    datalist.appendChild(opt);
  });

  const colorA = TISSUE_META[tissueA].color;
  const colorB = TISSUE_META[tissueB].color;

  const elements = buildElements(filteredNodes, filteredEdges, hubFieldA, hubFieldB);

  $('#cy').empty();
  cy = cytoscape({
    container: document.getElementById('cy'),
    elements: elements,
    pixelRatio: 1,
    textureOnViewport: true,
    hideEdgesOnViewport: true,
    hideLabelsOnViewport: true,
    motionBlur: false,

    style: [
      { selector: 'node', style: {
        'label': 'data(id)',
        'font-size': 11.5,
        'font-family': 'Inter, -apple-system, sans-serif',
        'font-weight': 600,
        'color': '#1c2230',
        'text-valign': 'bottom',
        'text-margin-y': 8,
        'min-zoomed-font-size': 8,
        'width': 32,
        'height': 32,
        'border-width': 2,
        'border-color': '#ffffff',
        'background-opacity': 0.95
      }},
      { selector: "node[tissue_class = '" + tissueA + "']", style: { 'background-color': colorA } },
      { selector: "node[tissue_class = '" + tissueB + "']", style: { 'background-color': colorB } },
      { selector: "node[tissue_class = 'both']", style: { 'background-color': '#8c6fe0' } },

      { selector: "node[?" + hubFieldA + "][!" + hubFieldB + "]", style: { 'border-width': 3, 'border-style': 'solid', 'border-color': '#f0a93c' } },
      { selector: "node[?" + hubFieldB + "][!" + hubFieldA + "]", style: { 'border-width': 3, 'border-style': 'solid', 'border-color': '#f0a93c' } },
      { selector: "node[?" + hubFieldA + "][?" + hubFieldB + "]", style: { 'border-width': 6, 'border-style': 'double', 'border-color': '#f0a93c' } },

      { selector: 'node.selected-focus', style: { 'border-color': '#1c2230', 'border-width': 4, 'scale': 1.15 } },

      { selector: 'edge', style: {
        'curve-style': 'haystack',
        'haystack-radius': 0.3,
        'width': 'mapData(weight, 0.6, 1, 1.4, 6)',
        'opacity': 'mapData(weight, 0.6, 1, 0.45, 0.9)',
        'line-color': '#b0b8c5'
      }},
      { selector: "edge[tissue = '" + tissueA + "']", style: { 'line-color': colorA } },
      { selector: "edge[tissue = '" + tissueB + "']", style: { 'line-color': colorB } },
      { selector: '.faded',       style: { 'opacity': 0.15 } },
      { selector: '.hidden-edge', style: { 'display': 'none' } }
    ],

    layout: CONCENTRIC_LAYOUT_OPTS
  });

  cy.on('tap', 'node', evt => selectNode(evt.target));
  cy.on('tap', evt => {
    if (evt.target === cy) {
      clearHighlight();
      document.getElementById('partner-empty').style.display = 'block';
      document.getElementById('partner-content').style.display = 'none';
    }
  });

  applyFilters();
}

function buildElements(nodeList, edgeList, hubFieldA, hubFieldB) {
  return [
    ...nodeList.map(n => ({
      data: {
        id: n.id,
        tissue_class: n.tissue_class,
        [hubFieldA]: n[hubFieldA],
        [hubFieldB]: n[hubFieldB]
      }
    })),
    ...edgeList.map((e, i) => ({
      data: { id: 'e' + i + '_' + e.source + '_' + e.target, source: e.source, target: e.target, weight: e.weight, tissue: e.tissue }
    }))
  ];
}

function setupControls() {
  const slider = document.getElementById('weight-slider');
  slider.addEventListener('input', applyFilters);
  document.getElementById('toggle-a').addEventListener('change', applyFilters);
  document.getElementById('toggle-b').addEventListener('change', applyFilters);

  document.getElementById('reset-btn').addEventListener('click', () => {
    clearHighlight();
    slider.value = slider.min;
    document.getElementById('toggle-a').checked = true;
    document.getElementById('toggle-b').checked = true;
    applyFilters();
    document.getElementById('search-box').value = '';
    document.getElementById('partner-empty').style.display = 'block';
    document.getElementById('partner-content').style.display = 'none';

    const layoutToggle = document.getElementById('layout-toggle');
    if (layoutToggle && layoutToggle.checked) {
      layoutToggle.checked = false;
      if (cy) cy.layout(CONCENTRIC_LAYOUT_OPTS).run();
    }
    if (cy) cy.fit(undefined, 40);
  });

  document.getElementById('layout-toggle').addEventListener('change', e => {
    if (!cy) { e.target.checked = false; return; }

    if (e.target.checked) {
      const n = cy.nodes(':visible').length;
      if (n > COSE_NODE_WARN_LIMIT) {
        const proceed = confirm(
          'This network currently has ' + n + ' visible nodes. A force layout at this size can take a while. Continue anyway?'
        );
        if (!proceed) { e.target.checked = false; return; }
      }
      cy.layout(COSE_LAYOUT_OPTS).run();
    } else {
      cy.layout(CONCENTRIC_LAYOUT_OPTS).run();
    }
  });

  document.getElementById('search-box').addEventListener('change', e => {
    const geneId = e.target.value.trim();
    if (!geneId || !cy) return;

    let node = cy.getElementById(geneId);
    if (node && node.length) { selectNode(node); return; }

    addGeneOnDemand(geneId);
  });
}

function addGeneOnDemand(geneId) {
  const nodeRecord = rawNodes.find(n => n.id === geneId);
  if (!nodeRecord) return;

  const minWeight = parseFloat(document.getElementById('weight-slider').value);
  const relatedEdges = rawEdges.filter(e => (e.source === geneId || e.target === geneId) && e.weight >= minWeight);
  const neighborIds = new Set([geneId]);
  relatedEdges.forEach(e => { neighborIds.add(e.source); neighborIds.add(e.target); });

  const hubFieldA = currentHubFields.a, hubFieldB = currentHubFields.b;
  const newNodeRecords = rawNodes.filter(n => neighborIds.has(n.id) && cy.getElementById(n.id).empty());
  const newEdgeRecords = relatedEdges.filter(e => cy.getElementById('e_' + e.source + '_' + e.target).empty());

  const elements = buildElements(newNodeRecords, newEdgeRecords, hubFieldA, hubFieldB);

  cy.batch(() => {
    cy.add(elements);
  });

  applyFilters();
  const node = cy.getElementById(geneId);
  if (node && node.length) selectNode(node);
}

function applyFilters() {
  if (!cy || selectedTissues.length !== 2) return;
  if (filterRAF) return;
  filterRAF = requestAnimationFrame(() => {
    filterRAF = null;
    const slider = document.getElementById('weight-slider');
    const minWeight = parseFloat(slider.value);
    document.getElementById('weight-value').textContent = minWeight.toFixed(2);
    const showA = document.getElementById('toggle-a').checked;
    const showB = document.getElementById('toggle-b').checked;
    const tissueA = selectedTissues[0], tissueB = selectedTissues[1];

    cy.batch(() => {
      cy.edges().forEach(edge => {
        const w = edge.data('weight');
        const tissue = edge.data('tissue');
        const tissueOn = (tissue === tissueA && showA) || (tissue === tissueB && showB);
        edge.toggleClass('hidden-edge', !(w >= minWeight && tissueOn));
      });
    });
  });
}

function clearHighlight() {
  if (cy) {
    cy.batch(() => {
      cy.elements().removeClass('faded');
      cy.elements().removeClass('selected-focus');
    });
  }
  $('.go-gene-pill').removeClass('go-pill-active');
}

function pillHtml(tissue) {
  const meta = TISSUE_META[tissue] || { soft: '#eee', dark: '#333' };
  return '<span class="pill" style="background:' + meta.soft + ';color:' + meta.dark + '">' + tissueLabel(tissue) + '</span>';
}

function showPartners(node) {
  const partners = [];
  node.connectedEdges().forEach(edge => {
    const otherId = edge.data('source') === node.id() ? edge.data('target') : edge.data('source');
    partners.push({ partner: otherId, tissue: edge.data('tissue'), weight: edge.data('weight') });
  });
  partners.sort((a, b) => b.weight - a.weight);

  document.getElementById('partner-empty').style.display = 'none';
  document.getElementById('partner-content').style.display = 'block';

  const hubBits = [];
  if (currentHubFields && node.data(currentHubFields.a)) {
    hubBits.push(tissueLabel(currentHubFields.tissueA) + ' \u2192 ' + tissueLabel(currentHubFields.tissueB));
  }
  if (currentHubFields && node.data(currentHubFields.b)) {
    hubBits.push(tissueLabel(currentHubFields.tissueB) + ' \u2192 ' + tissueLabel(currentHubFields.tissueA));
  }
  document.getElementById('partner-meta').innerHTML = hubBits.length
    ? node.id() + ' &middot; hub: <strong>' + hubBits.join(', ') + '</strong>'
    : node.id() + ' &middot; ' + (node.data('tissue_class') === 'both' ? 'shared' : tissueLabel(node.data('tissue_class')));

  const tbody = document.querySelector('#partner-table tbody');
  tbody.innerHTML = '';
  partners.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td>' + p.partner + '</td>' +
                   '<td>' + pillHtml(p.tissue) + '</td>' +
                   '<td>' + p.weight.toFixed(2) + '</td>';
    tbody.appendChild(tr);
  });
}

function selectNode(node) {
  clearHighlight();
  const neighborhood = node.closedNeighborhood();
  cy.batch(() => {
    cy.elements().not(neighborhood).addClass('faded');
    node.addClass('selected-focus');
  });
  cy.animate({ fit: { eles: neighborhood, padding: 60 } }, { duration: 250 });
  showPartners(node);

  const geneName = node.id().toUpperCase();
  $('.go-gene-pill[data-gene="' + geneName + '"]').addClass('go-pill-active');
}

/* ---------------------------------------------------------------------
   ROBUST GO ENRICHMENT FILE PARSER & RENDERER
--------------------------------------------------------------------- */
function cleanKey(k) {
  return (k || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function parseGOFile(text) {
  const lines = text.split(/\r?\n/);
  if (lines.length === 0) return [];

  const rawHeaders = lines[0].split('\t');
  const normalizedHeaders = rawHeaders.map(h => cleanKey(h));
  const data = [];

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;
    const cols = line.split('\t');
    const obj = {};
    normalizedHeaders.forEach((normH, index) => {
      obj[normH] = cols[index] ? cols[index].trim() : '';
    });
    data.push(obj);
  }
  return data;
}

function renderGOTable(tbody, countElement, data) {
  tbody.innerHTML = '';
  countElement.textContent = data.length + ' Terms';

  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No enrichment results found.</td></tr>';
    return;
  }

  data.forEach(row => {
    const term = row['term'] || '';
    const overlap = row['overlap'] || '';
    
    // Normalized key lookups for Adjusted P-value
    const adjP = row['adjustedpvalue'] || row['adjppval'] || row['adjpvalue'] || row['adjustedpval'] || row['pvalue'] || '';
    
    // Normalized key lookups for Combined Score
    const score = row['combinedscore'] || row['score'] || '';
    const genesStr = row['genes'] || '';

    let genePillsHtml = '';
    if (genesStr) {
      const genesList = genesStr.split(';');
      genePillsHtml = genesList.map(g => {
        const cleanG = g.trim().toUpperCase();
        return '<span class="go-gene-pill" data-gene="' + cleanG + '">' + cleanG + '</span>';
      }).join('');
    }

    // Format Adjusted P-Value
    let displayP = adjP;
    const numP = parseFloat(adjP);
    if (!isNaN(numP)) {
      displayP = (numP < 0.001 || numP > 9999) ? numP.toExponential(2) : numP.toFixed(4);
    }

    // Format Combined Score
    let displayScore = score;
    const numScore = parseFloat(score);
    if (!isNaN(numScore)) {
      displayScore = numScore.toFixed(2);
    }

    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="fw-medium text-dark" style="max-width: 240px; word-wrap: break-word;">' + term + '</td>' +
      '<td><span class="badge bg-secondary-subtle text-secondary-emphasis">' + overlap + '</span></td>' +
      '<td class="font-monospace fw-semibold">' + displayP + '</td>' +
      '<td class="font-monospace fw-semibold">' + displayScore + '</td>' +
      '<td><div style="max-height: 80px; overflow-y: auto; max-width: 260px; min-width: 140px;">' + genePillsHtml + '</div></td>';

    tbody.appendChild(tr);
  });
}

async function fetchFirstValidGOFile(candidates) {
  for (const url of candidates) {
    try {
      const res = await fetch(url);
      if (res.ok) {
        const text = await res.text();
        return text;
      }
    } catch (e) { }
  }
  return null;
}

async function loadGOEnrichment(tissueA, tissueB) {
  const container = document.getElementById('go-enrichment-container');
  const titleA = document.getElementById('go-table-a-title');
  const titleB = document.getElementById('go-table-b-title');
  const countA = document.getElementById('go-table-a-count');
  const countB = document.getElementById('go-table-b-count');
  const tbodyA = document.querySelector('#go-table-a tbody');
  const tbodyB = document.querySelector('#go-table-b tbody');

  container.style.display = 'block';
  titleA.textContent = tissueLabel(tissueA) + ' Biological Processes';
  titleB.textContent = tissueLabel(tissueB) + ' Biological Processes';

  tbodyA.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading...</td></tr>';
  tbodyB.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading...</td></tr>';
  countA.textContent = '...';
  countB.textContent = '...';

  const pairTag = getGOPairTag(tissueA, tissueB);

  const candidatesA = [
    DATA_DIR + '/GO_data/GO_Biological_Process_2026_' + PASCAL_MAP[tissueA] + '(' + pairTag + ').txt',
    DATA_DIR + '/GO_data/GO_Biological_Process_' + PASCAL_MAP[tissueA] + '(' + pairTag + ').txt'
  ];

  const candidatesB = [
    DATA_DIR + '/GO_data/GO_Biological_Process_2026_' + PASCAL_MAP[tissueB] + '(' + pairTag + ').txt',
    DATA_DIR + '/GO_data/GO_Biological_Process_' + PASCAL_MAP[tissueB] + '(' + pairTag + ').txt'
  ];

  const textA = await fetchFirstValidGOFile(candidatesA);
  if (textA) {
    renderGOTable(tbodyA, countA, parseGOFile(textA));
  } else {
    tbodyA.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No enrichment file found for ' + tissueLabel(tissueA) + '.</td></tr>';
    countA.textContent = '0 Terms';
  }

  const textB = await fetchFirstValidGOFile(candidatesB);
  if (textB) {
    renderGOTable(tbodyB, countB, parseGOFile(textB));
  } else {
    tbodyB.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No enrichment file found for ' + tissueLabel(tissueB) + '.</td></tr>';
    countB.textContent = '0 Terms';
  }
}
</script>

<?php
include_once 'footer.php';
?>