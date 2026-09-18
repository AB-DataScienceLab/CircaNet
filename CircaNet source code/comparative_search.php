<?php
// Include database connection credentials
include_once("conn.php");

// Include the page header
include_once("header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparative Search Portal</title>
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #333333;
            --border-color: #e2e8f0;
        }

        .comp-search-wrapper {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 40px 20px;
            min-height: 600px;
        }

        .comp-search-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .comp-search-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .comp-search-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .comp-search-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Search Filter Styling */
        .filter-container {
            max-width: 500px;
            margin: 0 auto 40px auto;
            position: relative;
        }

        .filter-input {
            width: 100%;
            padding: 12px 20px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 2px 10px rgba(52, 152, 219, 0.2);
        }

        /* Card Grid Layout */
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .comparison-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .comparison-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.08);
            border-color: var(--secondary-color);
        }

        .card-content h3 {
            color: var(--primary-color);
            font-size: 1.3rem;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .card-content p {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .card-footer {
            display: flex;
            align-items: center;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .card-footer .arrow {
            margin-left: 8px;
            transition: transform 0.2s ease;
        }

        .comparison-card:hover .arrow {
            transform: translateX(5px);
        }

        /* No Results Message */
        .no-results {
            display: none;
            text-align: center;
            font-size: 1.2rem;
            color: #777;
            padding: 40px;
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>

<div class="comp-search-wrapper">
    <div class="comp-search-container">
        
        <div class="comp-search-header">
            <h1>Comparative Analysis Portal</h1>
            <p>Select a module below to compare genomic, tissue, disease, or population-level data.</p>
        </div>

        <!-- Real-time Filter Input -->
<!--        <div class="filter-container">
-->
<!--            <input type="text" id="searchFilter" class="filter-input" placeholder="Search comparison options...">
-->
<!--        </div>
-->

        <!-- Comparative Options Grid -->
        <div class="comparison-grid" id="comparisonGrid">
            
            <!-- Card 1: Compare Diseases (Updated URL) -->
            <a href="https://datascience.imtech.res.in/anshu/circanet/disease_comorbidity.php#compare" class="comparison-card" data-title="compare diseases comorbidity">
                <div class="card-content">
                    <h3>Compare Diseases</h3>
                    <p>Analyze and explore relationships and comorbidities between different disease conditions.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 2: Compare Genes across Diseases (Updated URL) -->
            <a href="https://datascience.imtech.res.in/anshu/circanet/disease_comorbidity.php#compare" class="comparison-card" data-title="compare genes across diseases comorbidity">
                <div class="card-content">
                    <h3>Compare Genes across Diseases</h3>
                    <p>Investigate and compare the association and roles of specific genes across various disease profiles.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 3: Population-wise Comparison -->
            <a href="population.php" class="comparison-card" data-title="population-wise comparison genomic profiles">
                <div class="card-content">
                    <h3>Population-wise Comparison</h3>
                    <p>Compare genomic, transcriptomic, or clinical distributions across diverse populations.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 4: Compare Genes across Tissue -->
            <a href="Cross_tissue.php" class="comparison-card" data-title="compare genes across tissue expression">
                <div class="card-content">
                    <h3>Compare Genes across Tissue</h3>
                    <p>Analyze expression profiles and patterns of target genes across multiple distinct tissues.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 5: Compare Genes across Transcription Factor -->
            <a href="https://datascience.imtech.res.in/anshu/circanet/tf_compare.php?g1=PER2&g2=CLOCK" class="comparison-card" data-title="compare genes across transcription factor tf per2 clock">
                <div class="card-content">
                    <h3>Compare Genes across Transcription Factor</h3>
                    <p>Analyze transcription factor interactions and regulators, featuring a sample comparison of PER2 and CLOCK.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 6: Compare PPI Modules -->
            <a href="ppi_network.php" class="comparison-card" data-title="compare ppi modules protein network">
                <div class="card-content">
                    <h3>Compare PPI Modules</h3>
                    <p>Investigate, compare, and visualize Protein-Protein Interaction networks and sub-modules.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Card 7: Inter Tissue Comparison -->
            <a href="multicens.php" class="comparison-card" data-title="inter tissue comparison multicens network">
                <div class="card-content">
                    <h3>Inter Tissue Comparison</h3>
                    <p>Perform multi-tissue comparisons to identify co-expression and regulation patterns across systems.</p>
                </div>
                <div class="card-footer">
                    Explore Analysis <span class="arrow">&rarr;</span>
                </div>
            </a>

            <!-- Fallback text when no filter matches -->
            <div id="noResults" class="no-results">
                No matching comparison tools found.
            </div>

        </div>
    </div>
</div>

<script>
    // Live filter logic for interactive navigation
    const searchFilter = document.getElementById('searchFilter');
    const cards = document.querySelectorAll('.comparison-card');
    const noResults = document.getElementById('noResults');

    // Safe execution check in case searchFilter input is commented out in HTML
    if (searchFilter) {
        searchFilter.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let matches = 0;

            cards.forEach(card => {
                const dataTitle = card.getAttribute('data-title');
                if (dataTitle.includes(query)) {
                    card.style.display = 'flex';
                    matches++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (matches === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        });
    }
</script>

</body>
</html>

<?php
// Include the page footer
include_once("footer.php");
?>