</main> <!-- Closes the main container from header.php -->

    <!-- Secondary Navigation (Harmonized to exactly match the top navigation style) -->
    <footer class="secondary-nav">
        <a href="http://datascience.imtech.res.in/anshu/circanet/download_page.php">Downloads</a>
        <a href="https://datascience.imtech.res.in/anshu/circanet/data_summary.php">Data Summary</a>
        <a href="https://datascience.imtech.res.in/anshu/circanet/faq.php">FAQs & Help</a>
        <a href="https://datascience.imtech.res.in/anshu/circanet/team.php">CircaNet Team</a>
    </footer>

    <!-- Copyright Footer (CSS aligned block with unified layout rules) -->
    <div class="copyright-bar">
        <p>
            Circanet &copy; 2026<br>
            All Data are freely accessible to all users, including commercial users.<br>
            This website doesn't use any cookies.
        </p>
    </div>

    <!-- REQUIRED JAVASCRIPT LIBRARIES -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/Chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.23.0/cytoscape.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Custom script to activate visualizations and table -->
    <script>
    $(document).ready(function() {

        // --- Show all loading spinners immediately ---
        $('#chartLoading').show();
        $('#networkLoading').show();
        $('#tableLoading').show();

        // --- 1. CHART INITIALIZATION (Theme matching) ---
        function initializeChart() {
            const ctx = document.getElementById('diseaseChart');
            if (ctx && typeof chart_data_json !== 'undefined' && chart_data_json.length > 0) {
                setTimeout(function() {
                    new Chart(ctx, { type: 'bar', data: { labels: chart_labels_json, datasets: [{ label: '# of Associated Genes', data: chart_data_json, backgroundColor: 'rgba(91, 140, 190, 0.75)' }] },
                        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return 'Genes: ' + context.parsed.x; } } } }, scales: { x: { beginAtZero: true, title: { display: true, text: 'Number of Genes' } } } }
                    });
                    $('#chartLoading').hide();
                    $('#chartContainer').show();
                }, 10);
            } else { $('#chartLoading').hide(); }
        }

        // --- 2. OPTIMIZED NETWORK INITIALIZATION ---
        function initializeNetwork() {
            const networkContainer = document.getElementById('networkContainer');
            if (networkContainer && typeof network_data_json !== 'undefined' && network_data_json.nodes.length > 0) {
                 setTimeout(function() {
                    const elements = [];
                    network_data_json.nodes.forEach(node => { elements.push({ group: 'nodes', data: { id: node.id, label: node.label }, classes: node.type }); });
                    network_data_json.edges.forEach(edge => { elements.push({ group: 'edges', data: { source: edge.source, target: edge.target } }); });

                    var cy = cytoscape({
                        container: networkContainer, elements: elements,
                        style: [
                            { selector: 'node', style: { 'label': 'data(label)', 'width': '60px', 'height': '60px', 'font-size': '10px', 'text-valign': 'center', 'color': '#fff', 'text-outline-width': 2, 'text-outline-color': '#555' }},
                            { selector: 'node.disease', style: { 'background-color': '#5B8CBE', 'shape': 'round-rectangle' }}, /* Pastel Blue */
                            { selector: 'node.gene', style: { 'background-color': '#E89D6C', 'shape': 'ellipse' }}, /* Pastel Orange */
                            { selector: 'edge', style: { 'width': 2, 'line-color': '#cbd5e1', 'curve-style': 'bezier' }},
                            { selector: 'node:selected, edge:selected', style: { 'border-width': 3, 'border-color': '#D07A44', 'line-color': '#D07A44', 'width': 4 }}
                        ],
                        layout: { name: 'grid', padding: 30 }
                    });

                    $('#networkLoading').hide();
                    $('#networkContainer').show();

                    let coseLayout = cy.layout({
                        name: 'cose', idealEdgeLength: 100, nodeOverlap: 20, refresh: 20, fit: true, padding: 30, randomize: false, componentSpacing: 100,
                        nodeRepulsion: 400000, edgeElasticity: 100, gravity: 80, numIter: 1000,
                        animate: true, animationDuration: 1000, animationEasing: 'ease-out'
                    });
                    coseLayout.run();

                    cy.on('tap', 'node', function(evt){ var node = evt.target; cy.elements().removeClass('selected'); node.addClass('selected').neighborhood().addClass('selected'); });
                    cy.on('tap', function(evt){ if(evt.target === cy){ cy.elements().removeClass('selected'); } });

                }, 50);
            } else { $('#networkLoading').hide(); }
        }

        // --- 3. DATATABLE INITIALIZATION AND FILTER SETUP ---
        $('#diseaseFilter').select2({
            theme: "bootstrap-5",
            placeholder: 'Type to search for a disease...',
            allowClear: true,
            ajax: {
                url: 'get_diseases.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            minimumInputLength: 2
        });

        const table = $('#diseaseTable').DataTable({
            "ajax": { "url": "get_disease_data.php", "dataSrc": "data" },
            "deferRender": true, "pageLength": 25,
            "language": { "processing": '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>' },
            "columns": [
                { "data": 0, "title": "Sr. No." },
                { "data": 1, "title": "HGNC ID" },
                { "data": 2, "title": "Approved Symbol" },
                { "data": 3, "title": "Disease Name" },
                { "data": 4, "title": "MONDO ID" },
                { "data": 5, "title": "Source" }
            ],
            "initComplete": function () {
                $('#tableLoading').hide();
                $('#tableContainer').show();
                populateDropdown(this.api().column(2), '#symbolFilter');
                populateDropdown(this.api().column(5), '#sourceFilter');
            }
        });

        function populateDropdown(column, selectId) {
            const select = $(selectId);
            const uniqueValues = new Set();
            select.find('option:gt(0)').remove();
            column.data().each(function(d) {
                if (typeof d === 'string' && d) {
                    const trimmedValue = d.trim();
                    if (trimmedValue) { uniqueValues.add(trimmedValue); }
                }
            });
            const sortedValues = Array.from(uniqueValues).sort();
            sortedValues.forEach(function(d) {
                select.append($('<option></option>').attr('value', d).text(d));
            });
        }

        $('#symbolFilter, #diseaseFilter, #sourceFilter').on('change', function() {
            table.column(2).search($('#symbolFilter').val()).draw();
            table.column(3).search($('#diseaseFilter').val() || '').draw();
            table.column(5).search($('#sourceFilter').val()).draw();
        });

        initializeChart();
        initializeNetwork();
    });
    </script>
</body>
</html>