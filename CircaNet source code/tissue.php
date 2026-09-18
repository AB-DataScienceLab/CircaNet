<?php
require_once 'conn.php';
include 'header.php';
?>

<!-- Required External Libraries -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    body {
        background-color: #f4f7fa; 
    }

    :root { 
        --primary-accent: #5B8CBE;  
        --dark-blue: #2A4B7C;       
        --pastel-orange: #E89D6C;   
        --light-orange: #F1B28C;    
    }

    .hero-title {
        color: var(--dark-blue); 
        font-weight: 700;
        font-size: 2.5rem;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }

    .hero-subtitle {
        color: #5A6B82;
        font-size: 1.1rem;
        font-weight: 300;
        line-height: 1.6;
    }

    .feature-card-static {
        background: #ffffff;
        border: none;
        border-radius: 12px;
        border-top: 5px solid transparent; 
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        padding: 25px;
        height: 100%;
    }
    
    .card-primary { border-top-color: var(--primary-accent); }
    .card-secondary { border-top-color: var(--pastel-orange); }
    .card-info { border-top-color: #7AA2CD; }

    .chart-container { 
        position: relative; 
        height: 250px; 
        width: 100%; 
    }

    .table-responsive { 
        font-size: 0.88rem; 
    }
    
    table.dataTable {
        border-collapse: collapse !important;
    }
    
    table.dataTable thead th { 
        background: #f8fafc !important; 
        color: var(--dark-blue);
        font-weight: 700;
        text-transform: capitalize; 
        font-size: 0.82rem; 
        white-space: nowrap;
        border-bottom: 2px solid #e2e8f0 !important;
        padding: 12px 10px !important;
    }
    
    table.dataTable tbody td {
        padding: 12px 10px !important;
        border-bottom: 1px solid #f1f5f9;
    }

    /* Style for the clickable Gene Symbol, Ensembl, and HGNC links */
    table.dataTable tbody td a.gene-link {
        color: var(--dark-blue);
        text-decoration: none;
        transition: color 0.15s ease-in-out;
    }
    table.dataTable tbody td a.gene-link:hover {
        color: var(--primary-accent) !important;
        text-decoration: underline !important;
    }

    .dt-buttons .btn { 
        font-size: 0.85rem; 
        font-weight: 600; 
        border-radius: 6px; 
        padding: 6px 16px;
        transition: all 0.2s ease-in-out;
    }
    
    .dt-buttons .btn-outline-secondary {
        border: 2px solid #cbd5e1;
        color: #5A6B82;
        background: transparent;
    }
    .dt-buttons .btn-outline-secondary:hover {
        background-color: #f1f5f9;
        color: var(--dark-blue);
        border-color: #cbd5e1;
    }
    
    .dt-buttons .btn-outline-success {
        border: 2px solid var(--primary-accent);
        color: var(--primary-accent);
        background: transparent;
    }
    .dt-buttons .btn-outline-success:hover {
        background-color: var(--primary-accent);
        color: #ffffff;
        border-color: var(--primary-accent);
    }

    .badge-pastel-blue {
        background-color: #F0F4F8;
        color: var(--primary-accent);
        border: 1px solid #d0e1fd;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }
    
    .badge-pastel-orange {
        background-color: #FFF5EE;
        color: var(--pastel-orange);
        border: 1px solid #fde4d3;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }

    .loading-overlay { 
        display: none; 
        position: absolute; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(255, 255, 255, 0.8); 
        z-index: 10; 
        align-items: center; 
        justify-content: center; 
        flex-direction: column; 
        border-radius: 12px; 
    }
    
    .data-content-wrapper { 
        position: relative; 
        min-height: 400px; 
    }

    #bg-canvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.55;
        pointer-events: none;
    }
</style>

<canvas id="bg-canvas"></canvas>

<div class="container-fluid px-4 py-4">
    <div class="text-center my-4 pb-2">
        <h1 class="hero-title mb-2">Tissue Expression Atlas</h1>
        <p class="hero-subtitle mb-0">Explore gene expression landscapes across human tissues, including top tissue expression, maximum TPM, and tissue specificity indices.</p>
    </div>

    <!-- Data Content Wrapper with Loading Overlay -->
    <div class="data-content-wrapper">
        <div class="loading-overlay" id="loading-tissue">
            <div class="spinner-border text-primary"></div>
            <div class="mt-2 text-muted fw-bold">Loading Dataset...</div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4 mb-4">
            <div class="col-md-5">
                <div class="feature-card-static card-secondary">
                    <h6 class="text-muted fw-bold text-uppercase mb-3 small">
                        <i class="bi bi-pie-chart-fill me-2" style="color: var(--pastel-orange);"></i>Specificity Distribution
                    </h6>
                    <div class="chart-container">
                        <canvas id="tissuePieChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="feature-card-static card-info">
                    <h6 class="text-muted fw-bold text-uppercase mb-3 small">
                        <i class="bi bi-bar-chart-steps me-2" style="color: #7AA2CD;"></i>Top Max TPM Expressions
                    </h6>
                    <div class="chart-container">
                        <canvas id="tissueBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- DataTable Card -->
        <div class="feature-card-static card-primary p-4 mb-5">
            <div class="table-responsive">
                <table id="tableTissue" class="table table-hover w-100 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 60px;">S.No.</th>
                            <th>Gene Symbol</th>
                            <th>Gene Name</th>
                            <th>Ensembl Gene ID</th>
                            <th>HGNC ID</th>
                            <th>NCBI ID</th>
                            <th>Top Tissue</th>
                            <th>Max TPM</th>
                            <th>Specificity</th>
                            <th>Tau</th>
                            <th>TSI</th>
                            <th>Gini</th>
                            <th>Z-Max</th>
                            <th>HPA</th>
                            <th>SPM-Max</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Core Scripts for JS interactions -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Export Buttons Scripts -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    const dtButtons = [
        { 
            extend: 'copy', 
            className: 'btn btn-outline-secondary' 
        },
        { 
            text: '<i class="bi bi-download me-1"></i> Download Full CSV', 
            className: 'btn btn-outline-success', 
            action: function () {
                window.location.href = 'export_tissue.php';
            }
        }
    ];

    Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    Chart.defaults.color = '#5A6B82';

    // Fetch Aggregated Chart Data
    function loadChartData() {
        $.ajax({
            url: 'fetch_chart_data.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    console.error(response.error);
                    return;
                }
                renderCharts(response);
            },
            error: function() {
                console.error('Failed to load chart data.');
            }
        });
    }

    function renderCharts(chartData) {
        new Chart(document.getElementById('tissuePieChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.pie.map(d => d.label),
                datasets: [{ 
                    data: chartData.pie.map(d => d.value), 
                    backgroundColor: ['#5B8CBE','#7AA2CD','#E89D6C','#F1B28C','#8FAFD2','#A2C1E1','#F5C6A5','#F9DCC4','#cbd5e1'] 
                }]
            },
            options: { 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { 
                        position: 'right', 
                        labels: { boxWidth: 10, padding: 12 } 
                    } 
                } 
            }
        });

        new Chart(document.getElementById('tissueBarChart'), {
            type: 'bar',
            data: {
                labels: chartData.bar.map(d => d.label),
                datasets: [{ 
                    label: 'Max TPM', 
                    data: chartData.bar.map(d => d.value), 
                    backgroundColor: '#5B8CBE', 
                    borderRadius: 4 
                }]
            },
            options: { 
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Initialize DataTable in Server-Side Processing Mode
    $('#tableTissue').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'fetch_tissue_data.php',
            type: 'GET'
        },
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',
        buttons: dtButtons,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        scrollX: true,
        deferRender: true,
        order: [[7, 'desc']], // Max_TPM column
        columns: [
            { 
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row, meta) {
                    return `<strong>${meta.row + meta.settings._iDisplayStart + 1}</strong>`;
                }
            },
            { 
                data: 'Approved_symbol', 
                render: function(data, type, row) {
                    let symbol = row.Approved_symbol || row.Gene_Name || 'N/A';
                    if (symbol === 'N/A') {
                        return `<span class="fw-bold" style="color: var(--dark-blue);">${symbol}</span>`;
                    }
                    // Wraps the symbol in a redirect link that opens in a new tab
                    return `<a href="gene.php?keyword=${encodeURIComponent(symbol)}" class="gene-link fw-bold" target="_blank" rel="noopener noreferrer">${symbol}</a>`;
                }
            },
            { data: 'Approved_name', defaultContent: 'N/A' },
            { 
                data: 'Gene_ID', 
                render: function(data, type, row) {
                    let ensemblId = row.Gene_ID;
                    if (!ensemblId || ensemblId === 'N/A') {
                        return 'N/A';
                    }
                    // Redirects and opens the Ensembl ID page in a new tab
                    return `<a href="https://asia.ensembl.org/Multi/Search/Results?q=${encodeURIComponent(ensemblId)};site=ensembl_all" class="gene-link fw-bold" target="_blank" rel="noopener noreferrer">${ensemblId}</a>`;
                },
                defaultContent: 'N/A' 
            },
            { 
                data: 'HGNC_ID', 
                render: function(data, type, row) {
                    let hgncId = row.HGNC_ID;
                    if (!hgncId || hgncId === 'N/A') {
                        return 'N/A';
                    }
                    // Redirects and opens the HGNC Genenames page in a new tab
                    return `<a href="https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/${encodeURIComponent(hgncId)}" class="gene-link fw-bold" target="_blank" rel="noopener noreferrer">${hgncId}</a>`;
                },
                defaultContent: 'N/A' 
            },
            { data: 'NCBI_Gene_ID', defaultContent: 'N/A' },
            { data: 'Top_Tissue', defaultContent: 'N/A' },
            { 
                data: 'Max_TPM', 
                render: $.fn.dataTable.render.number(',', '.', 2), 
                defaultContent: '0.00' 
            },
            { 
                data: 'Specificity', 
                render: function(data) {
                    if (!data) return 'N/A';
                    let isEnriched = data.toLowerCase().includes('enriched');
                    let cssClass = isEnriched ? 'badge-pastel-blue' : 'badge-pastel-orange';
                    return `<span class="${cssClass}">${data}</span>`;
                }, 
                defaultContent: 'N/A'
            },
            { data: 'Tau', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' },
            { data: 'TSI', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' },
            { data: 'Gini', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' },
            { data: 'Z_max', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' },
            { data: 'HPA', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' },
            { data: 'SPM_max', render: $.fn.dataTable.render.number(',', '.', 3), defaultContent: '0.000' }
        ],
        initComplete: function() {
            $('#loading-tissue').hide(); 
        }
    });

    loadChartData();
});
</script>

<!-- Background Particles matching index.php -->
<script>
    (function() {
        const canvas = document.getElementById('bg-canvas');
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];

        function resize() {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width;
            canvas.height = height;
        }
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.size = Math.random() * 3 + 1;
                this.color = 'rgba(91, 140, 190, 0.15)'; 
            }
            update() {
                this.x += this.vx;
                this.y += this.vy;
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;
            }
            draw() {
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        for (let i = 0; i < 50; i++) {
            particles.push(new Particle());
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
                
                for (let j = i; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 120) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(91, 140, 190, ${0.15 - distance/800})`;
                        ctx.lineWidth = 1;
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(animate);
        }
        animate();
    })();
</script>

<?php include 'footer.php'; ?>