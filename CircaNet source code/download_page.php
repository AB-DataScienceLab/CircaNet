<?php 
// 1. Include header
include 'header.php'; 
?>

<!-- Custom Styles for Download Page (Matching Theme) -->
<style>
    body {
        background-color: #f4f7fa;
    }

    .download-header {
        padding: 30px 0 15px 0;
        background: transparent;
    }

    .download-title {
        color: #2A4B7C;
        font-weight: 700;
        font-size: 2.2rem;
        letter-spacing: -0.5px;
        margin-bottom: 5px;
    }

    .download-subtitle {
        color: #5A6B82;
        font-size: 1.05rem;
        font-weight: 300;
        margin-bottom: 15px;
    }

    .download-card {
        background: #ffffff;
        border: none;
        border-radius: 12px;
        border-top: 5px solid transparent; 
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }

    .download-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 25px rgba(91, 140, 190, 0.12); 
    }

    .card-blue { border-top-color: #5B8CBE; }
    .card-blue-light { border-top-color: #7AA2CD; }
    .card-orange { border-top-color: #E89D6C; }
    .card-orange-light { border-top-color: #F1B28C; }

    .card-title-download {
        color: #2A4B7C;
        font-weight: 700;
        font-size: 1.25rem;
        margin-bottom: 8px;
    }
    
    .card-desc-download {
        color: #667085;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 20px;
    }

    .btn-download-blue {
        border: 2px solid #5B8CBE;
        color: #5B8CBE;
        background-color: transparent;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 6px;
        padding: 6px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        transition: all 0.2s ease-in-out;
    }
    .btn-download-blue:hover {
        background-color: #5B8CBE;
        color: #ffffff;
    }

    .btn-download-orange {
        border: 2px solid #E89D6C;
        color: #E89D6C;
        background-color: transparent;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 6px;
        padding: 6px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        transition: all 0.2s ease-in-out;
    }
    .btn-download-orange:hover {
        background-color: #E89D6C;
        color: #ffffff;
    }

    .file-type-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .select-chr {
        font-size: 0.88rem;
        border: 1.5px solid #E89D6C;
        border-radius: 6px;
        color: #2A4B7C;
        font-weight: 500;
        padding: 6px 10px;
        outline: none;
        width: 100%;
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

<!-- Particle Background Canvas -->
<canvas id="bg-canvas"></canvas>

<!-- Sleek Header Block -->
<section class="download-header text-center">
    <div class="container">
        <h2 class="download-title">Downloads</h2>
        <p class="download-subtitle mx-auto" style="max-width: 600px;">
            Access and download data catalogs from the CircaNet database.
        </p>
    </div>
</section>

<!-- Content Grid -->
<section class="container mb-5 py-4">
    <div class="row g-4">
        
        <!-- Overall Genes data -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue">
                <div>
                    <h4 class="card-title-download">Genes Catalog</h4>
                    <p class="card-desc-download">
                        Description and annotation profiles of Circadian Associated genes.
                    </p>
                </div>
                <div class="mt-2">
                    <a href="download_handler.php?action=table&id=gene_annotation" class="btn-download-blue">
                        <span>Overall Genes</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Diseases & Comorbidity data -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-orange">
                <div>
                    <h4 class="card-title-download">Disease &amp; Comorbidity</h4>
                    <p class="card-desc-download">
                        Relational tables connecting pathologically linked traits, disease associations, and gene phenotypes.
                    </p>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <a href="download_handler.php?action=table&id=disease_edges_tb" class="btn-download-orange">
                        <span>Disease Association Data</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                    <a href="download_handler.php?action=table&id=Disease_tb" class="btn-download-orange">
                        <span>Gene-Disease Association</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Expression data -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue-light">
                <div>
                    <h4 class="card-title-download">Tissue Expression</h4>
                    <p class="card-desc-download">
                        Indices mapping baseline structural gene expressions across individual human tissue configurations.
                    </p>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <a href="download_handler.php?action=table&id=tissue_wise_tb_v2" class="btn-download-blue" style="border-color: #7AA2CD; color: #7AA2CD;">
                        <span>Tissue Specific Index</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                    <a href="download_handler.php?action=table&id=gene_wise_tb_v2" class="btn-download-blue" style="border-color: #7AA2CD; color: #7AA2CD;">
                        <span>Gene Wise Expression</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Alphamissense metric -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-orange-light">
                <div>
                    <h4 class="card-title-download">Alphamissense Metric</h4>
                    <p class="card-desc-download">
                        Calculated mutation pathogenicity metrics representing overall gene profiles and residue coordinates.
                    </p>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <a href="download_handler.php?action=table&id=alphamissense_metric" class="btn-download-orange" style="border-color: #F1B28C; color: #F1B28C;">
                        <span>Per Gene Core Metric</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                    <a href="download_handler.php?action=file&id=alphafold_pos" class="btn-download-orange" style="border-color: #F1B28C; color: #F1B28C;">
                        <span>Alphafold Detailed Positions</span>
                        <span class="file-type-badge">TSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Protein Feature -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue">
                <div>
                    <h4 class="card-title-download">Protein Features</h4>
                    <p class="card-desc-download">
                        Comprehensive residues cataloguing post-translational modifications, IDRs, and LLPS scores.
                    </p>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <a href="download_handler.php?action=file&id=residue_llps" class="btn-download-blue">
                        <span>Residue-wise LLPS</span>
                        <span class="file-type-badge">TSV</span>
                    </a>
                    <a href="download_handler.php?action=file&id=iupred_idrs" class="btn-download-blue">
                        <span>IDRs</span>
                        <span class="file-type-badge">ZIP</span>
                    </a>
                    <a href="download_handler.php?action=file&id=merged_ptm" class="btn-download-blue">
                        <span>Merged PTM</span>
                        <span class="file-type-badge">TSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Interaction & Module -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-orange">
                <div>
                    <h4 class="card-title-download">Interaction &amp; Modules</h4>
                    <p class="card-desc-download">
                        Protein-Protein Interactions and functional community groupings extracted using hierarchical clustering.
                    </p>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <a href="download_handler.php?action=table&id=Prot_string_with_HGNC" class="btn-download-orange">
                        <span>PPI Network</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                    <a href="download_handler.php?action=table&id=ppi_module_new" class="btn-download-orange">
                        <span>PPI Modules</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Inter-Tissue Communication -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue-light">
                <div>
                    <h4 class="card-title-download">Inter-Tissue Communication</h4>
                    <p class="card-desc-download">
                        Global centrality profiles representing signal dynamics across structural physiological checkpoints.
                    </p>
                </div>
                <div class="mt-2">
                    <a href="download_handler.php?action=file&id=global_centrality" class="btn-download-blue" style="border-color: #7AA2CD; color: #7AA2CD;">
                        <span>Global Centrality</span>
                        <span class="file-type-badge">ZIP</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Transcription Factor & Binding Sites -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-orange-light">
                <div>
                    <h4 class="card-title-download">Transcription Factors</h4>
                    <p class="card-desc-download">
                        Regulatory listings identifying transcription factor properties mapping genomic binding locus structures.
                    </p>
                </div>
                <div class="mt-2">
                    <a href="download_handler.php?action=table&id=tf_tb" class="btn-download-orange" style="border-color: #F1B28C; color: #F1B28C;">
                        <span>Transcription Factor Binding Sites</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Rhythmicity -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue">
                <div>
                    <h4 class="card-title-download">Rhythmicity</h4>
                    <p class="card-desc-download">
                        Parametric assessment outcomes mapping functional cyclic phases across baseline circadian profiles.
                    </p>
                </div>
                <div class="mt-2">
                    <a href="download_handler.php?action=table&id=circust_new" class="btn-download-blue">
                        <span>Rhythmicity Data</span>
                        <span class="file-type-badge">CSV</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Variant Data (Chromosome-wise Parquet only) -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-orange">
                <div>
                    <h4 class="card-title-download">Variants Data</h4>
                    <p class="card-desc-download">
                        Select and download optimized chromosome-level Parquet files (compatible with Python, R, Polars, DuckDB).
                    </p>
                </div>
                <div class="mt-2">
                    <!-- Dropdown for Chromosome-wise Download -->
                    <div class="d-flex gap-2">
                        <select id="chrSelect" class="form-select form-select-sm select-chr">
                            <option value="clinvar_chr1">Chr 1 (3.4 GB)</option>
                            <option value="clinvar_chr2">Chr 2 (3.0 GB)</option>
                            <option value="clinvar_chr3">Chr 3 (2.7 GB)</option>
                            <option value="clinvar_chr4">Chr 4 (1.9 GB)</option>
                            <option value="clinvar_chr5">Chr 5 (1.9 GB)</option>
                            <option value="clinvar_chr6">Chr 6 (2.1 GB)</option>
                            <option value="clinvar_chr7">Chr 7 (2.2 GB)</option>
                            <option value="clinvar_chr8">Chr 8 (1.8 GB)</option>
                            <option value="clinvar_chr9">Chr 9 (1.7 GB)</option>
                            <option value="clinvar_chr10">Chr 10 (1.9 GB)</option>
                            <option value="clinvar_chr11">Chr 11 (2.0 GB)</option>
                            <option value="clinvar_chr12">Chr 12 (1.9 GB)</option>
                            <option value="clinvar_chr13">Chr 13 (945 MB)</option>
                            <option value="clinvar_chr14">Chr 14 (1.2 GB)</option>
                            <option value="clinvar_chr15">Chr 15 (1.3 GB)</option>
                            <option value="clinvar_chr16">Chr 16 (1.6 GB)</option>
                            <option value="clinvar_chr17">Chr 17 (1.6 GB)</option>
                            <option value="clinvar_chr18">Chr 18 (766 MB)</option>
                            <option value="clinvar_chr19">Chr 19 (1.4 GB)</option>
                            <option value="clinvar_chr20">Chr 20 (853 MB)</option>
                            <option value="clinvar_chr21">Chr 21 (380 MB)</option>
                            <option value="clinvar_chr22">Chr 22 (694 MB)</option>
                            <option value="clinvar_chrX">Chr X (1.1 GB)</option>
                            <option value="clinvar_chrY">Chr Y (27 MB)</option>
                            <option value="clinvar_chrM">Chr M (904 KB)</option>
                        </select>
                        <button type="button" onclick="downloadSelectedChr()" class="btn-download-orange" style="cursor:pointer; white-space: nowrap;">
                            <span>Get</span>
                            <span class="file-type-badge ms-1">PARQUET</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- GFF Files -->
        <div class="col-md-6 col-lg-4">
            <div class="download-card card-blue-light">
                <div>
                    <h4 class="card-title-download">GFF Files</h4>
                    <p class="card-desc-download">
                        Annotation datasets mapping genomic coordinate features across targeted structural model files.
                    </p>
                </div>
                <div class="mt-2">
                    <a href="download_handler.php?action=file&id=gff_files" class="btn-download-blue" style="border-color: #7AA2CD; color: #7AA2CD;">
                        <span>GFF Files</span>
                        <span class="file-type-badge">ZIP</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Handler Script for Chromosome Dropdown -->
<script>
function downloadSelectedChr() {
    const selectedChr = document.getElementById('chrSelect').value;
    if (selectedChr) {
        window.location.href = 'download_handler.php?action=file&id=' + encodeURIComponent(selectedChr);
    }
}
</script>

<!-- Particle Canvas Animation -->
<script>
    (function() {
        const canvas = document.getElementById('bg-canvas');
        if (!canvas) return;
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

<?php 
// 2. Include footer
include 'footer.php'; 
?>