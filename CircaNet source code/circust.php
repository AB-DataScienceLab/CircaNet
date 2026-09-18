<?php 
// Instruct the server and browser to interpret this page using UTF-8
header('Content-Type: text/html; charset=utf-8');

include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Circadian Gene Rhythm Explorer</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card { background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

    <!-- Error Banner -->
    <div id="errorBanner" class="max-w-6xl mx-auto mt-2 px-4 hidden">
        <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded shadow-sm flex justify-between items-center">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-red-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span id="errorMessage" class="text-sm text-red-700"></span>
            </div>
            <button onclick="dismissError()" class="text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col items-center justify-center text-center mb-6 w-full">
            <h1 style="
                font-family: 'Segoe UI', sans-serif;
                font-size: 40px;
                font-weight: 700;
                color: #212529;
                margin-bottom: 5px;">
                Tissue Wise Rhythmicity
            </h1>

            <p style="
                font-family: 'Segoe UI', sans-serif;
                font-size: 20px;
                font-weight: 400;
                color: #212529;
                line-height: 1.5;
                margin-bottom: 0;">
                Explore tissue-specific rhythmic genes across more than 40 human tissues.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            
            <!-- Controls Column -->
            <div class="lg:col-span-1 space-y-3.5">
                
                <!-- Data Selector Card -->
                <div class="card p-3.5">
                    <h2 class="font-bold mb-2 pb-1.5 border-b text-indigo-700 text-sm">Select Data</h2>
                    
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Tissue</label>
                    <select id="tissueSelect" class="w-full p-2 border rounded mb-3 bg-white text-xs">
                        <option value="">Loading tissues...</option>
                    </select>

                    <label class="block text-xs font-semibold text-gray-600 mb-1">Search Gene</label>
                    <input type="text" id="geneSearch" placeholder="Type symbol or HGNC..." class="w-full p-2 border rounded mb-3 bg-white text-xs focus:ring-1 focus:ring-indigo-500 outline-none" />

                    <label class="block text-xs font-semibold text-gray-600 mb-1">Gene Symbol</label>
                    <select id="geneSelect" class="w-full p-2 border rounded bg-white text-xs">
                        <option value="">Select tissue first...</option>
                    </select>
                </div>

                <!-- Parameter Inspector Card -->
                <div class="card p-3.5">
                    <h3 class="font-bold text-indigo-700 text-xs border-b pb-1.5 mb-2.5">Model Parameters</h3>
                    <div id="inspectorContent" class="text-xs space-y-2 text-gray-700">
                        <p class="text-gray-400 italic">Select a gene to view parameters.</p>
                    </div>
                </div>

                <!-- Biological Relevance Explanation -->
                <div class="card p-3.5 bg-indigo-50 border border-indigo-100">
                    <h3 class="font-bold text-indigo-800 text-xs mb-1.5">Cosinor Model Interpretation</h3>
                    <div id="stats" class="text-[11px] leading-relaxed space-y-1.5 text-gray-700">
                        <p><strong>Mesor (M):</strong> Baseline gene expression level (midline baseline of the fitted cosine curve).</p>
                        <p><strong>Amplitude (A):</strong> Peak strength (half of the total variation) of the rhythmic expression.</p>
                        <p><strong>Relative Amplitude:</strong> Normalized amplitude representation against the midline baseline.</p>
                        <p><strong>Acrophase_Rad:</strong> Estimated timing of maximum activity in radians (Acrophase).</p>
                        <p><strong>R&sup2; Fit:</strong> Proportion of variance explained by the rhythm.</p>
                    </div>
                </div>

                <!-- Source Citation Card -->
                <div class="card p-3 bg-white border border-gray-100">
                    <h3 class="font-bold text-gray-800 text-[10px] uppercase tracking-wider mb-1">
                        Data Source Citation
                    </h3>
                    <p class="text-[11px] text-gray-600 leading-relaxed">
                        <strong>Declaration:</strong> Rhythmicity metrics generated using
                        <span class="font-medium">CYCLOPS</span>
                        (<a href="https://doi.org/10.1073/pnas.1619320114"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium">
                            Link
                        </a>).
                    </p>
                </div>
            </div>

            <!-- Visualization Column -->
            <div class="lg:col-span-3 space-y-4">
                
                <!-- Chart Card -->
                <div class="card p-4 relative">
                    <div class="h-72 w-full relative">
                        <canvas id="rhythmChart"></canvas>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="card overflow-hidden">
                    <div class="px-3.5 py-2.5 bg-gray-50 border-b flex justify-between items-center flex-wrap gap-2">
                        <span class="text-xs font-semibold text-gray-700">Gene List Overview</span>
                        <span id="tableNotice" class="text-[10px] text-gray-500 bg-gray-200 px-2 py-0.5 rounded hidden"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100 text-[11px] font-semibold uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left text-gray-600 w-12">S.No</th>
                                    <th class="px-3 py-2 text-left text-gray-600">Gene (HGNC)</th>
                                    <th class="px-3 py-2 text-left text-gray-600">Mesor</th>
                                    <th class="px-3 py-2 text-left text-gray-600">Amplitude</th>
                                    <th class="px-3 py-2 text-left text-gray-600">Relative_Amplitude</th>
                                    <th class="px-3 py-2 text-left text-gray-600">Acrophase_Rad</th>
                                    <th class="px-3 py-2 text-left text-gray-600">R&sup2; Fit</th>
                                </tr>
                            </thead>
                            <tbody id="dataTableBody" class="text-xs divide-y divide-gray-200">
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">Select a tissue to load gene dataset.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Table Pagination Footer -->
                    <div class="px-3.5 py-2.5 bg-gray-50 border-t flex justify-between items-center flex-wrap gap-2">
                        <span id="paginationInfo" class="text-xs text-gray-500">Showing 0 to 0 of 0 entries</span>
                        <div class="flex items-center space-x-2">
                            <button id="prevBtn" onclick="prevPage()" class="px-3 py-1 text-xs font-semibold text-gray-700 bg-white border rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <button id="nextBtn" onclick="nextPage()" class="px-3 py-1 text-xs font-semibold text-gray-700 bg-white border rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-40 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-4 rounded-lg shadow-xl flex items-center space-x-3">
            <svg class="animate-spin h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-semibold text-gray-700">Fetching dynamic data...</span>
        </div>
    </div>

    <script>
        let allTissueData = [];
        let filteredData = [];
        let chart;
        
        // Pagination state settings
        let currentPage = 1;
        const pageSize = 10;

        const tissueSelect = document.getElementById('tissueSelect');
        const geneSelect = document.getElementById('geneSelect');
        const geneSearch = document.getElementById('geneSearch');
        const tableBody = document.getElementById('dataTableBody');

        // Helper to format values in clean π fractions
        function formatToPi(value) {
            const tolerance = 0.08;
            const piFractions = [
                { val: 0, label: '0' },
                { val: Math.PI / 4, label: 'π/4' },
                { val: Math.PI / 2, label: 'π/2' },
                { val: (3 * Math.PI) / 4, label: '3π/4' },
                { val: Math.PI, label: 'π' },
                { val: (5 * Math.PI) / 4, label: '5π/4' },
                { val: (3 * Math.PI) / 2, label: '3π/2' },
                { val: (7 * Math.PI) / 4, label: '7π/4' },
                { val: 2 * Math.PI, label: '2π' }
            ];

            for (const item of piFractions) {
                if (Math.abs(value - item.val) < tolerance) {
                    return item.label;
                }
            }
            return (value / Math.PI).toFixed(2) + 'π';
        }

        async function init() {
            try {
                showLoading(true);
                const response = await fetch('get_circust_data.php?action=tissues');
                const result = await response.json();
                
                if (result.status === 'success' && result.data.length > 0) {
                    tissueSelect.innerHTML = result.data.map(t => 
                        `<option value="${t}">${t.replace(/_/g, ' ')}</option>`
                    ).join('');
                    
                    tissueSelect.addEventListener('change', handleTissueChange);
                    geneSelect.addEventListener('change', handleGeneChange);
                    geneSearch.addEventListener('input', filterGenes);
                    
                    await fetchTissueGenes(result.data[0]);
                } else {
                    const msg = result.message || "No tissues found. Please check if your 'circust_new' table contains data.";
                    showError(msg);
                }
            } catch (err) {
                console.error(err);
                showError("Unable to establish connection with database backend: " + err.message);
            } finally {
                showLoading(false);
            }
        }

        async function fetchTissueGenes(tissue) {
            try {
                showLoading(true);
                const response = await fetch(`get_circust_data.php?action=genes&tissue=${encodeURIComponent(tissue)}`);
                const result = await response.json();
                
                if (result.status === 'success') {
                    allTissueData = result.data.map(item => {
                        const mVal = parseFloat(item.Mesor || 0);
                        const aVal = parseFloat(item.Amplitude || 0);
                        const acroVal = parseFloat(item.Acrophase_Rad || 0);

                        return {
                            symbol: item.Gene_Symbol || '',
                            hgnc_id: item.hgnc_id || '',
                            m: mVal,
                            a: aVal,
                            rel_amp: parseFloat(item.Relative_Amplitude || 0),
                            t_u: acroVal, // Acrophase in radians
                            r2: parseFloat(item.R_Squared || 0),
                            p_value: parseFloat(item.P_Value || 0),
                            fdr_q: parseFloat(item.FDR_q || 0),
                            tissue: item.tissue || ''
                        };
                    });

                    geneSearch.value = '';
                    currentPage = 1;
                    filterGenes();
                } else {
                    showError(result.message || "Failed to retrieve gene details.");
                }
            } catch (err) {
                console.error(err);
                showError("Network error: " + err.message);
            } finally {
                showLoading(false);
            }
        }

        async function handleTissueChange() {
            const selectedTissue = tissueSelect.value;
            if (selectedTissue) {
                await fetchTissueGenes(selectedTissue);
            }
        }

        function handleGeneChange() {
            updateDashboard();
        }

        function filterGenes() {
            const query = geneSearch.value.toLowerCase().trim();
            
            filteredData = allTissueData.filter(d => 
                (d.symbol && d.symbol.toLowerCase().includes(query)) || 
                (d.hgnc_id && d.hgnc_id.toLowerCase().includes(query))
            );

            currentPage = 1;
            
            const dropdownLimit = 500;
            const dropdownGenes = filteredData.slice(0, dropdownLimit);
            geneSelect.innerHTML = dropdownGenes.map(g => 
                `<option value="${g.symbol}">${g.symbol} (${g.hgnc_id})</option>`
            ).join('');
            
            if (dropdownGenes.length > 0) {
                const currentSelected = geneSelect.value;
                if (!dropdownGenes.some(g => g.symbol === currentSelected)) {
                    geneSelect.value = dropdownGenes[0].symbol;
                }
            } else {
                geneSelect.innerHTML = '<option value="">No matches found</option>';
            }
            
            updateDashboard();
        }

        function updateDashboard() {
            const currentGene = geneSelect.value;
            const data = allTissueData.find(d => d.symbol === currentGene);
            
            if (data) {
                renderChart(data);
                updateInspector(data);
            }
            renderTable();
        }

        // Single Harmonic Cosinor in Radians: M + A * cos(theta - Acrophase_Rad)
        function calculateCosinorInRadians(theta_rad, m, a, acrophase_rad) {
            return m + a * Math.cos(theta_rad - acrophase_rad);
        }

        function renderChart(geneData) {
            const ctx = document.getElementById('rhythmChart').getContext('2d');
            
            // Generate fine points from 0 to 2π
            const dataPoints = [];
            const step = (2 * Math.PI) / 100;
            for (let theta = 0; theta <= (2 * Math.PI) + 0.001; theta += step) {
                const currentRad = Math.min(theta, 2 * Math.PI);
                const val = calculateCosinorInRadians(currentRad, geneData.m, geneData.a, geneData.t_u);
                dataPoints.push({ x: currentRad, y: val });
            }

            if (chart) chart.destroy();

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [{
                        label: `${geneData.symbol} Expression Profile`,
                        data: dataPoints,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.05)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        borderWidth: 2.5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { 
                            display: true, 
                            text: `Cosinor Fitted Oscillatory Rhythm: ${geneData.symbol} (${geneData.tissue.replace(/_/g, ' ')})`,
                            font: { size: 12, weight: 'bold' }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` Expression: ${context.raw.y.toFixed(4)}`;
                                },
                                title: function(context) {
                                    const rad = context[0].raw.x;
                                    return `Phase: ${formatToPi(rad)} (${rad.toFixed(2)} rad)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            type: 'linear',
                            min: 0,
                            max: 2 * Math.PI,
                            ticks: {
                                stepSize: Math.PI / 2, // 0, π/2, π, 3π/2, 2π
                                callback: function(value) {
                                    return formatToPi(value);
                                }
                            },
                            title: { 
                                display: true, 
                                text: 'Acrophase / Phase (Radians in π format)' 
                            } 
                        },
                        y: { 
                            title: { display: true, text: 'Relative Expression' } 
                        }
                    }
                }
            });
        }

        // Update inspector information card
        function updateInspector(geneData) {
            const inspector = document.getElementById('inspectorContent');
            const piFormatted = formatToPi(geneData.t_u);
            
            const pValFormatted = geneData.p_value < 0.001 ? geneData.p_value.toExponential(3) : geneData.p_value.toFixed(4);
            const fdrFormatted = geneData.fdr_q < 0.001 ? geneData.fdr_q.toExponential(3) : geneData.fdr_q.toFixed(4);

            inspector.innerHTML = `
                <div class="flex justify-between items-center bg-indigo-50 p-2 rounded mb-2.5 border border-indigo-100">
                    <span class="font-bold text-indigo-900 text-xs">${geneData.symbol}</span>
                    <span class="text-[9px] bg-indigo-200 text-indigo-800 px-1.5 py-0.5 rounded font-mono">${geneData.hgnc_id}</span>
                </div>
                <div class="grid grid-cols-2 gap-x-2 gap-y-2">
                    <div>
                        <p class="text-gray-400 text-[9px] uppercase font-semibold">Mesor (M)</p>
                        <p class="font-medium text-gray-800 text-xs">${geneData.m.toFixed(4)}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[9px] uppercase font-semibold">Amplitude (A)</p>
                        <p class="font-bold text-indigo-600 text-xs">${geneData.a.toFixed(4)}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[9px] uppercase font-semibold">Acrophase_Rad</p>
                        <p class="font-bold text-orange-600 text-xs">${geneData.t_u.toFixed(4)} <span class="text-[9px] text-gray-500 font-normal">(${piFormatted})</span></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-[9px] uppercase font-semibold">R&sup2; Fit Metric</p>
                        <p class="font-bold text-emerald-600 text-xs">${geneData.r2.toFixed(4)}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-gray-400 text-[9px] uppercase font-semibold">Relative Amplitude</p>
                        <p class="font-medium text-gray-800 text-xs">${geneData.rel_amp.toFixed(4)}</p>
                    </div>
                </div>
                <div class="border-t border-gray-100 pt-2.5 mt-2.5">
                    <p class="font-bold text-gray-600 text-[9px] uppercase mb-1.5 tracking-wider">Statistical Indicators</p>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="bg-gray-50 p-1.5 rounded border border-gray-100">
                            <p class="text-[8px] text-gray-400 font-mono">P-Value</p>
                            <p class="font-semibold text-gray-700 text-[10px] mt-0.5">${pValFormatted}</p>
                        </div>
                        <div class="bg-gray-50 p-1.5 rounded border border-gray-100">
                            <p class="text-[8px] text-gray-400 font-mono">FDR q-Value</p>
                            <p class="font-semibold text-gray-700 text-[10px] mt-0.5">${fdrFormatted}</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderTable() {
            const totalRecords = filteredData.length;
            const totalPages = Math.ceil(totalRecords / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalRecords);
            const tableRows = filteredData.slice(startIndex, endIndex);
            
            let html = '';
            if (totalRecords === 0) {
                html = `<tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">No matching gene records.</td></tr>`;
            } else {
                html = tableRows.map((d, index) => {
                    const serialNumber = startIndex + index + 1;
                    const isSelected = geneSelect.value === d.symbol;
                    const rowClass = isSelected ? 'bg-indigo-50/70 border-indigo-200' : 'hover:bg-gray-50';
                    
                    return `
                        <tr class="${rowClass} cursor-pointer transition-colors" onclick="selectGeneDirectly('${d.symbol}')">
                            <td class="px-3 py-2 font-medium text-gray-500">${serialNumber}</td>
                            <td class="px-3 py-2 font-medium">
                                <a href="https://datascience.imtech.res.in/anshu/circanet/gene.php?keyword=${encodeURIComponent(d.symbol)}" 
                                   target="_blank" 
                                   onclick="event.stopPropagation();" 
                                   class="text-indigo-600 hover:underline font-bold">
                                    ${d.symbol}
                                </a>
                                <span class="text-[10px] text-gray-400 font-mono block">${d.hgnc_id}</span>
                            </td>
                            <td class="px-3 py-2 text-gray-700">${d.m.toFixed(3)}</td>
                            <td class="px-3 py-2 text-indigo-600 font-bold">${d.a.toFixed(3)}</td>
                            <td class="px-3 py-2 text-gray-700 font-semibold">${d.rel_amp.toFixed(3)}</td>
                            <td class="px-3 py-2 text-orange-600 font-bold">${d.t_u.toFixed(3)}</td>
                            <td class="px-3 py-2 text-emerald-600 font-bold">${d.r2.toFixed(3)}</td>
                        </tr>
                    `;
                }).join('');
            }

            tableBody.innerHTML = html;

            const notice = document.getElementById('tableNotice');
            if (totalRecords > 0) {
                notice.textContent = `${totalRecords} records`;
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }

            const infoText = totalRecords > 0 
                ? `Showing ${startIndex + 1} to ${endIndex} of ${totalRecords} entries` 
                : 'Showing 0 to 0 of 0 entries';
            document.getElementById('paginationInfo').textContent = infoText;

            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            prevBtn.disabled = (currentPage === 1);
            nextBtn.disabled = (currentPage >= totalPages);
        }

        window.prevPage = function() {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        }

        window.nextPage = function() {
            const totalPages = Math.ceil(filteredData.length / pageSize) || 1;
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        }

        window.selectGeneDirectly = function(symbol) {
            let optionExists = false;
            for (let i = 0; i < geneSelect.options.length; i++) {
                if (geneSelect.options[i].value === symbol) {
                    geneSelect.selectedIndex = i;
                    optionExists = true;
                    break;
                }
            }
            
            if (!optionExists) {
                const targetGene = allTissueData.find(d => d.symbol === symbol);
                if (targetGene) {
                    const opt = document.createElement('option');
                    opt.value = targetGene.symbol;
                    opt.textContent = `${targetGene.symbol} (${targetGene.hgnc_id})`;
                    geneSelect.appendChild(opt);
                    geneSelect.value = targetGene.symbol;
                }
            }
            updateDashboard();
            
            document.getElementById('rhythmChart').scrollIntoView({ behavior: 'smooth', block: 'end' });
        }

        function showLoading(show) {
            const loader = document.getElementById('loadingOverlay');
            if (show) loader.classList.remove('hidden');
            else loader.classList.add('hidden');
        }

        function showError(msg) {
            const errBanner = document.getElementById('errorBanner');
            const errMsg = document.getElementById('errorMessage');
            errMsg.textContent = msg;
            errBanner.classList.remove('hidden');
            errBanner.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        window.dismissError = function() {
            document.getElementById('errorBanner').classList.add('hidden');
        }

        // Initialize on load
        init();
    </script>
</body>
</html>
<?php 
include 'footer.php';
?>