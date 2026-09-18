<?php
require_once 'conn.php';

// =================================================================
// 1. AJAX HANDLERS: Fetch Charts & Dynamic Dropdown Data
// =================================================================

// --- A. Dynamic Select2 Symbol Search Handler ---
if (isset($_GET['action']) && $_GET['action'] == 'search_symbols') {
    header('Content-Type: application/json');
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $results = [];

    try {
        $stmt = $conn->prepare("SELECT DISTINCT symbol 
                                FROM gene_annotation 
                                WHERE symbol IS NOT NULL 
                                  AND symbol != '' 
                                  AND symbol != 'NA' 
                                  AND symbol LIKE ? 
                                ORDER BY symbol ASC 
                                LIMIT 40");
        $like_search = "%" . $search . "%";
        $stmt->bind_param("s", $like_search);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => $row['symbol'],
                'text' => $row['symbol']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }

    echo json_encode(['results' => $results]);
    exit;
}

// --- B. Initial Chart Stats Handler ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_stats') {
    header('Content-Type: application/json');
    $response = [
        'chr_labels' => [], 
        'chr_data' => [], 
        'type_labels' => [], 
        'type_data' => [],
        'all_gene_types' => [],
        'all_symbols' => [] 
    ];

    try {
        // Chromosome Data
        $chr_buckets = [];
        $chr_sql = "SELECT Chromosome, COUNT(*) as count 
                    FROM gene_annotation 
                    WHERE Chromosome IS NOT NULL 
                      AND Chromosome != '' 
                      AND Chromosome != 'NA' 
                    GROUP BY Chromosome";
        $chr_result = $conn->query($chr_sql);
        
        while($row = $chr_result->fetch_assoc()) {
            $raw_val = trim($row['Chromosome']);
            $count = (int)$row['count'];
            $chr_buckets[$raw_val] = ($chr_buckets[$raw_val] ?? 0) + $count;
        }
        
        uksort($chr_buckets, function($a, $b) {
            $map = [
                'X' => 100, 'Y' => 101, 
                'PAR' => 102, 'X AND Y' => 102, 'X,Y' => 102, 
                'M' => 103, 'MITOCHONDRIA' => 103, 'MT' => 103, 'CHRM' => 103, 'CHRMT' => 103
            ];
            $valA = is_numeric($a) ? (int)$a : ($map[strtoupper($a)] ?? 200);
            $valB = is_numeric($b) ? (int)$b : ($map[strtoupper($b)] ?? 200);
            return $valA <=> $valB;
        });
        
        foreach($chr_buckets as $raw_val => $count) {
            $raw_upper = strtoupper($raw_val);
            if ($raw_upper === 'PAR' || $raw_upper === 'X AND Y' || $raw_upper === 'X,Y') {
                $response['chr_labels'][] = 'PAR genes';
            } elseif (in_array($raw_upper, ['M', 'MITOCHONDRIA', 'MT', 'CHRM', 'CHRMT'])) {
                $response['chr_labels'][] = 'mitochondria';
            } else {
                $response['chr_labels'][] = "Chr $raw_val";
            }
            $response['chr_data'][] = $count;
        }

        // Locus Type Data
        $type_sql = "SELECT locus_type, COUNT(*) as count 
                     FROM gene_annotation 
                     WHERE locus_type IS NOT NULL 
                       AND locus_type != '' 
                       AND locus_type != 'NA' 
                     GROUP BY locus_type 
                     ORDER BY count DESC";
        
        $type_result = $conn->query($type_sql);
        $counter = 0;
        
        while($row = $type_result->fetch_assoc()) {
            $response['all_gene_types'][] = $row['locus_type'];
            if ($counter < 8) {
                $response['type_labels'][] = $row['locus_type'];
                $response['type_data'][] = (int)$row['count'];
                $counter++;
            }
        }
        
    } catch (Exception $e) { error_log($e->getMessage()); }

    echo json_encode($response);
    exit;
}

// =================================================================
// 2. REGULAR PAGE LOAD (HTML UI)
// =================================================================
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gene Explorer | CircaNet</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" />
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <style>
        .chart-container { position: relative; height: 280px; width: 100%; display: flex; align-items: center; justify-content: center; }
        .filter-section { background-color: #ffffff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e9ecef; }
        .action-icon { font-size: 1.15rem; margin: 0 4px; cursor: pointer; transition: transform 0.15s ease-in-out; }
        .action-icon:hover { transform: scale(1.22); }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .spinner-border { width: 2rem; height: 2rem; color: #adb5bd; }
        .clickable-symbol, .clickable-chr { cursor: pointer; text-decoration: underline; text-decoration-style: dotted; }
        .clickable-symbol:hover, .clickable-chr:hover { text-decoration: underline; color: #0a58ca !important; }
        
        table.table tbody td a.gene-link-item {
            color: #0d6efd;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        table.table tbody td a.gene-link-item:hover {
            color: #0a58ca !important;
            text-decoration: underline !important;
        }

        .select2-container--open {
            z-index: 999999 !important;
        }

        /* --- Source Badges Styling --- */
        .source-badges-container {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        
        /* Active Highlighted Database Badge */
        .source-pill.active {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background-color: #0d6efd;
            color: #ffffff !important;
            border: 1px solid #0d6efd;
            box-shadow: 0 1px 3px rgba(13, 110, 253, 0.25);
            cursor: pointer;
            transition: all 0.18s ease-in-out;
        }
        .source-pill.active:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            box-shadow: 0 2px 6px rgba(13, 110, 253, 0.35);
            transform: translateY(-1px);
        }

        /* Inactive Disabled Database Badge */
        .source-pill.disabled {
            font-size: 0.72rem;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #adb5bd;
            border: 1px dashed #dee2e6;
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
            user-select: none;
        }
    </style>
</head>
<body>
<div class="container pt-0 pb-4">
    <div class="text-center">
        <h1 style="font-family: 'Segoe UI', sans-serif; font-size: 40px; font-weight: 700; color: #212529; padding: 0px; margin-bottom: 5px;">
            Gene Catalog
        </h1>
        <p style="font-family: 'Segoe UI', sans-serif; font-size: 20px; font-weight: 400; color: #212529; line-height: 1.5; margin-bottom: 0;">
            Explore the complete landscape of human genes associated with circadian rhythms.
        </p>
    </div>
</div>

    <!-- Charts Section -->
    <div class="row mb-5">
        <div class="col-lg-10 mb-8 mb-lg-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold text-muted small">CHROMOSOME DISTRIBUTION</h6></div>
                <div class="card-body">
                    <div class="chart-container">
                        <div class="spinner-border" id="chrSpinner" role="status"><span class="visually-hidden">Loading...</span></div>
                        <canvas id="chrChart" style="display:none;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            
            <div class="filter-section">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Chromosome</label>
                        <select id="chrFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php
                            $filter_chrs = ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','X','Y'];
                            foreach($filter_chrs as $c) echo "<option value='$c'>Chr $c</option>";
                            ?>
                            <option value="PAR">PAR genes</option>
                            <option value="M">mitochondria</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Gene Symbol</label>
                        <select id="symbolFilter" class="form-select form-select-sm">
                            <option value="">All Symbols</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-md-end pt-4">
                        <button id="downloadBtn" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-file-csv me-2"></i>Download
                        </button>
                        <button id="resetFilters" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i>Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="mainGeneTable" class="table table-hover w-100">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">S.No.</th>
                            <th>Symbol</th>
                            <th>HGNC ID</th>
                            <th>NCBI Gene ID</th>
                            <th>Structure</th>
                            <th>Chr</th>
                            <th>Source</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

<script>
    $(document).ready(function() {
        $.fn.dataTable.ext.errMode = 'none';

        let chrChartInstance;
        const currentPath = window.location.pathname;

        $('#chrFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'All Chromosomes',
            allowClear: true
        });

        $('#symbolFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search/Select Gene Symbol',
            allowClear: true,
            ajax: {
                url: currentPath,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { action: 'search_symbols', q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            },
            minimumInputLength: 1
        });

        $(document).on('select2:open', () => {
            setTimeout(() => {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) searchField.focus();
            }, 50);
        });

        // --- Fetch Charts ---
        $.ajax({
            url: currentPath + '?action=fetch_stats',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#chrSpinner').remove();
                $('#chrChart').show();

                const chrChartCtx = document.getElementById('chrChart').getContext('2d');
                chrChartInstance = new Chart(chrChartCtx, {
                    type: 'bar',
                    data: {
                        labels: response.chr_labels,
                        datasets: [{
                            data: response.chr_data,
                            backgroundColor: '#0d6efd',
                            borderRadius: 3
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } },
                        onClick: (e, elements) => {
                            if (elements.length > 0) {
                                const clickedElementIndex = elements[0].index;
                                const displayedLabel = chrChartInstance.data.labels[clickedElementIndex];
                                
                                let filterValue;
                                if (displayedLabel === 'mitochondria') {
                                    filterValue = 'M'; 
                                } else if (displayedLabel === 'PAR genes') {
                                    filterValue = 'PAR'; 
                                } else {
                                    filterValue = displayedLabel.replace('Chr ', ''); 
                                }
                                
                                $('#chrFilter').val(filterValue).trigger('change');
                            }
                        }
                    }
                });
            }
        });

        // --- DataTables Setup ---
        const checkNull = (data) => (data !== null && data !== undefined && String(data).trim() !== '') ? data : 'Null';

        const standardDatabases = [
            { id: 'cgdb', name: 'CGDB', url: 'https://doi.org/10.1093/nar/gkw1028' },
            { id: 'circadb', name: 'CircaDB', url: 'https://doi.org/10.1093/nar/gks1161' },
            { id: 'rhythmicdb', name: 'RhythmicDB', url: 'https://doi.org/10.3389/fgene.2022.882044' },
            { id: 'gtex', name: 'GTEx', url: 'https://doi.org/10.1371/journal.pbio.3001986' },
            { id: 'circakb', name: 'CircaKB', url: 'https://doi.org/10.1093/nar/gkae817' }
        ];

        const geneTable = $('#mainGeneTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "get_gene_data_serverside.php",
                type: "POST",
                data: function(d) {
                    d.chromosome = $('#chrFilter').val();
                    d.symbol = $('#symbolFilter').val();
                }
            },
            drawCallback: function() {
                $('[data-bs-toggle="tooltip"]').tooltip();
            },
            columns: [
                {
                    data: null, orderable: false, searchable: false,
                    render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
                },
                { 
                    data: 'symbol', 
                    render: d => checkNull(d) !== 'Null' ? `<a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=${encodeURIComponent(d)}" class="gene-link-item fw-bold text-primary text-decoration-none" target="_blank" rel="noopener noreferrer">${d}</a>` : `<span class="text-muted small">Null</span>` 
                },
                { 
                    data: 'hgnc_id', 
                    render: d => checkNull(d) !== 'Null' ? `<a href="https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/${encodeURIComponent(d)}" class="gene-link-item font-monospace small text-primary text-decoration-none" target="_blank" rel="noopener noreferrer">${d}</a>` : `<span class="text-muted small">Null</span>` 
                },
                { 
                    data: 'entrez_id', 
                    render: d => checkNull(d) !== 'Null' ? `<a href="https://www.ncbi.nlm.nih.gov/gene/?term=${encodeURIComponent(d)}" class="gene-link-item small text-primary text-decoration-none" target="_blank" rel="noopener noreferrer">${d}</a>` : `<span class="text-muted small">Null</span>` 
                },
                { 
                    data: 'symbol', 
                    render: d => checkNull(d) !== 'Null' ? `<a href="https://datascience.imtech.res.in/anshu/circanet/protein_page_2.php?id=${encodeURIComponent(d)}" class="gene-link-item small text-primary text-decoration-none" target="_blank" rel="noopener noreferrer"><i class="fas fa-cube me-1"></i>View Structure</a>` : `<span class="text-muted small">Null</span>` 
                },
                { 
                    data: 'Chromosome',
                    render: function(d) {
                        if (checkNull(d) !== 'Null') {
                            let displayVal = d;
                            let filterVal = d;
                            let upperD = String(d).toUpperCase();

                            if (upperD === 'PAR' || upperD === 'X AND Y' || upperD === 'X,Y') {
                                displayVal = 'PAR genes';
                                filterVal = 'PAR';
                            } else if (['M', 'MITOCHONDRIA', 'MT', 'CHRM', 'CHRMT'].includes(upperD)) {
                                displayVal = 'mitochondria';
                                filterVal = 'M';
                            }
                            return `<span class="text-primary clickable-chr" data-chr="${filterVal}">${displayVal}</span>`;
                        }
                        return `<span class="text-muted small">Null</span>`;
                    }
                },
                {
                    data: 'source',
                    defaultContent: '',
                    orderable: false,
                    render: function(d, type, row) {
                        const rawSource = String(d || row.source || row.Source || '').toLowerCase();

                        // Match databases with alias support (publication -> GTEx)
                        const isPresent = {
                            cgdb: rawSource.includes('cgdb'),
                            circadb: rawSource.includes('circadb') || rawSource.includes('circa_db'),
                            rhythmicdb: rawSource.includes('rhythmicdb') || rawSource.includes('rhythmic_db') || rawSource.includes('rhythmic'),
                            gtex: rawSource.includes('publication') || rawSource.includes('gtex'),
                            circakb: rawSource.includes('circakb') || rawSource.includes('circa_kb')
                        };

                        let html = '<div class="source-badges-container">';
                        standardDatabases.forEach(db => {
                            if (isPresent[db.id]) {
                                html += `<a href="${db.url}" target="_blank" rel="noopener noreferrer" class="source-pill active" data-bs-toggle="tooltip" title="Source: ${db.name} (Click to view paper)">${db.name} <i class="fas fa-external-link-alt" style="font-size: 0.6rem;"></i></a>`;
                            } else {
                                html += `<span class="source-pill disabled" data-bs-toggle="tooltip" title="${db.name} (Not documented for this gene)">${db.name}</span>`;
                            }
                        });
                        html += '</div>';

                        return html;
                    }
                },
                { 
                    data: 'symbol',
                    orderable: false,
                    render: d => checkNull(d) !== 'Null' ? `
                        <a href="gene.php?keyword=${encodeURIComponent(d)}" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" title="Gene Page (Click to visit)">
                            <i class="fas fa-dna action-icon text-primary"></i>
                        </a>
                        <a href="variant.php?symbol=${encodeURIComponent(d)}" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" title="Variant Page (Click to visit)">
                            <i class="fas fa-code-branch action-icon text-danger"></i>
                        </a>
                        <a href="disease.php?symbol=${encodeURIComponent(d)}" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" title="Disease Page (Click to visit)">
                            <i class="fas fa-heartbeat action-icon text-warning"></i>
                        </a>
                    ` : `<span class="text-muted small">Null</span>`
                }
            ],
            pageLength: 25,
            order: [[1, 'asc']]
        });

        $(document).on('change select2:select select2:clear select2:unselect', '#chrFilter, #symbolFilter', function() {
            geneTable.draw();
        });
        
        $('#resetFilters').on('click', () => { 
            $('#chrFilter, #symbolFilter').val(null).trigger('change'); 
            geneTable.draw(); 
        });

        $('#mainGeneTable').on('click', '.clickable-symbol', function() {
            const sym = $(this).data('symbol');
            if (sym) {
                if ($('#symbolFilter').find("option[value='" + sym + "']").length === 0) {
                    const newOption = new Option(sym, sym, true, true);
                    $('#symbolFilter').append(newOption).trigger('change');
                } else {
                    $('#symbolFilter').val(sym).trigger('change');
                }
            }
        });

        $('#mainGeneTable').on('click', '.clickable-chr', function() {
            const chr = $(this).data('chr');
            if (chr) {
                $('#chrFilter').val(chr).trigger('change');
            }
        });

        $('#downloadBtn').on('click', function() {
            var params = new URLSearchParams({
                chromosome: $('#chrFilter').val(),
                symbol: $('#symbolFilter').val(),
                search: geneTable.search()
            });
            window.location.href = 'export_gene_data.php?' + params.toString();
        });
    });
</script>

</body>
</html>
<?php $conn->close(); include 'footer.php'; ?>