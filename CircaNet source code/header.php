<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CircaNet -A Circadian Biology Atlas</title>

    <!-- Favicon Implementation -->
    <link rel="icon" type="image/png" href="/anshu/circanet/logo_images/ChatGPT_removebg.png">
    <link rel="shortcut icon" type="image/png" href="/anshu/circanet/logo_images/ChatGPT - Copy.png">

    <!-- Bootstrap CSS for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Select2 Searchable Dropdown CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <style>
        /* Shared variables / consistency settings */
        header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: #fcfdfe;
            border-bottom: 1px solid #e2e8f0;
            justify-content: space-between;
        }
        .logo img {
            height: 100px; 
            margin: 2px;
            padding: 5px;          
        }
        .header-text {
            text-align: center;
            flex-grow: 1;
        }
        .header-text h1 {
            margin: 0;
            font-size: 50px; 
            color: #1A365D; 
            font-weight: 700;
            line-height: 1.2;
        }
        .header-text h2 {
            margin: 8px 0 0 0;
            font-size: 1.5rem; 
            color: #2D4A6E; 
            font-style: italic; 
            line-height: 1.4;
        }
        
        nav {
            padding: 14px 10px;
            background-color: #eef4fc; 
            border-bottom: 1px solid #cbd5e1;
            text-align: center;
            position: relative;
            z-index: 1000;
        }
        nav a {
            margin: 0 18px; 
            text-decoration: none;
            color: #1A365D; 
            font-size: 25px; /* Balanced size to match footer links */
            font-weight: 500;
            position: relative;
            display: inline-block;
            transition: color 0.2s ease-in-out;
        }
        nav a:hover {
            color: #5B8CBE; 
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-trigger {
            cursor: pointer;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #ffffff;
            min-width: 230px; 
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1);
            z-index: 1050;
            left: 50%;
            transform: translateX(-50%);
            top: 100%;
            border-radius: 6px;
            border-top: 3px solid #E89D6C; 
            padding: 5px 0;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-content a {
            color: #2A4B7C; 
            padding: 10px 16px;
            text-decoration: none;
            display: block;
            font-size: 16px;
            text-align: left;
            margin: 0;
            transition: background-color 0.2s, color 0.2s;
        }
        .dropdown-content a:hover {
            background-color: #F0F4F8; 
            color: #2A4B7C;
        }
        
        .dropdown-submenu {
            position: relative;
        }
        .dropdown-submenu .submenu-content {
            display: none;
            position: absolute;
            left: 100%;
            top: 0;
            background-color: #ffffff;
            min-width: 240px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1);
            border-radius: 6px;
            border-left: 3px solid #E89D6C; 
            padding: 5px 0;
            z-index: 1100;
        }
        .dropdown-submenu:hover > .submenu-content {
            display: block;
        }
        .dropdown-submenu:hover > a {
            background-color: #F0F4F8;
            color: #2A4B7C;
        }

        .chart-container {
            position: relative;
            height: 450px;
            width: 100%;
        }
        .select2-container--open {
            z-index: 9999 !important;
        }

        /* Consistent Footer Styles */
        footer.secondary-nav {
            padding: 14px 10px; /* Aligned with top nav padding */
            background-color: #eef4fc; /* Identical to top nav background */
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #cbd5e1; /* Mirrors bottom border of nav */
            border-bottom: 3px solid #E89D6C; /* Mirror top line color accent */
        }
        footer.secondary-nav a {
            margin: 0 18px; /* Identical spacing as top nav links */
            text-decoration: none;
            color: #1A365D;
            font-size: 25px; /* Identical font size */
            font-weight: 500;
            display: inline-block;
            transition: color 0.2s ease-in-out;
        }
        footer.secondary-nav a:hover {
            color: #5B8CBE;
        }
        .copyright-bar {
            padding: 20px;
            background-color: #2A4B7C;
            text-align: center;
            color: #ffffff;
        }
        .copyright-bar p {
            margin: 0;
            font-size: 16px;
            line-height: 1.6;
        }
    </style>
</head>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-NTG3R0XVR4"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-NTG3R0XVR4');
</script>
<body>
    <header>
        <div class="logo">
            <img src="https://datascience.imtech.res.in/anshu/circanet/logo_images/ChatGPT - Copy.png" alt="CircaNet Logo">
        </div>
        <div class="header-text">
            <h1>CircaNet: A Circadian Biology Atlas</h1>
            <h2>A Comprehensive Knowledge Base for Human Circadian Biology</h2>
        </div>
        <div class="logo">
            <img src="https://datascience.imtech.res.in/anshu/circanet/images/IMTECH_logo.jpg" alt="CSIR-IMTECH Logo">
        </div>
    </header>

    <nav>
        <!-- Home Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="https://datascience.imtech.res.in/anshu/circanet">Home <i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet">About Circanet</a>
                <div class="dropdown-submenu">
                    <a class="d-flex justify-content-between align-items-center" href="#">Base Module <i class="fas fa-caret-right ms-1" style="font-size: 12px;"></i></a>
                    <div class="submenu-content">
                        <a href="https://datascience.imtech.res.in/anshu/circanet/gene_search.php">Gene Catalog</a>
                        <a href="https://datascience.imtech.res.in/anshu/circanet/gene.php">Gene Profile</a>
                        <a href="https://datascience.imtech.res.in/anshu/circanet/ortho.php">Orthologs</a>
                    </div>
                </div>
                
                <div class="dropdown-submenu">
                    <a class="d-flex justify-content-between align-items-center" href="#">Integrative Module <i class="fas fa-caret-right ms-1" style="font-size: 12px;"></i></a>
                    <div class="submenu-content">
                        <a href="https://datascience.imtech.res.in/anshu/circanet/protein_page_2.php?id=Q16526">Structure</a>
                        <div class="dropdown-submenu">
                            <a class="d-flex justify-content-between align-items-center" href="#">Variants<i class="fas fa-caret-right ms-1" style="font-size: 11px;"></i></a>
                            <div class="submenu-content">
                                <a href="https://datascience.imtech.res.in/anshu/circanet/variant.php">Variant Browse</a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/gene_variant_distribution.php">Gene Variants </a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/population.php">Population Frequencies</a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/alphamissense.php">AlphaMissense</a>
                            </div>
                        </div>
                        <div class="dropdown-submenu">
                            <a class="d-flex justify-content-between align-items-center" href="#">Networks <i class="fas fa-caret-right ms-1" style="font-size: 11px;"></i></a>
                            <div class="submenu-content">
                                <a href="https://datascience.imtech.res.in/anshu/circanet/ppi_network.php">Interaction & Modules</a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/multicens_2.php">Inter-Tissue Communication</a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/tf_new.php">TF & TFBS</a>
                            </div>
                        </div>
                        <a href="https://datascience.imtech.res.in/anshu/circanet/circust.php">Rhythmicity</a>
                        <div class="dropdown-submenu">
                            <a class="d-flex justify-content-between align-items-center" href="https://datascience.imtech.res.in/anshu/circanet/disease.php">Disease <i class="fas fa-caret-right ms-1" style="font-size: 11px;"></i></a>
                            <div class="submenu-content">
                                <a href="https://datascience.imtech.res.in/anshu/circanet/disease.php">Gene Disease Associations </a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/disease_comorbidity.php">Comorbidity</a>
                            </div>
                        </div>
                        <div class="dropdown-submenu">
                            <a class="d-flex justify-content-between align-items-center" href="#">Expression <i class="fas fa-caret-right ms-1" style="font-size: 11px;"></i></a>
                            <div class="submenu-content">
                                <a href="https://datascience.imtech.res.in/anshu/circanet/Cross_tissue.php">Gene Expression </a>
                                <a href="https://datascience.imtech.res.in/anshu/circanet/gene_expression_catalog.php">Gene Expression Catalog</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dropdown-submenu">
                    <a class="d-flex justify-content-between align-items-center" href="#">Search <i class="fas fa-caret-right ms-1" style="font-size: 12px;"></i></a>
                    <div class="submenu-content">
                        <a href="https://datascience.imtech.res.in/anshu/circanet/search.php">Simple Search</a>
                        <a href="https://datascience.imtech.res.in/anshu/circanet/advanced_search.php">Advanced Search</a>
                        <a href="https://datascience.imtech.res.in/anshu/circanet/comparative_search.php">Comparative Search</a>
                    </div>
                </div>
                <a href="https://datascience.imtech.res.in/anshu/circanet/faq.php">FAQs & Help</a>
            </div>
        </div>

        <!-- Genes Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="https://datascience.imtech.res.in/anshu/circanet/gene_search.php">Genes <i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet/gene_search.php">Gene Catalog</a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/gene.php">Gene Profile</a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/ortho.php">Orthologs</a>
            </div>
        </div>

        <!-- Structure -->
        <a href="https://datascience.imtech.res.in/anshu/circanet/protein_page_2.php?id=Q16526">Structure</a>

        <!-- Variant Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="#">Variants <i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet/variant.php">Variant Browse </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/gene_variant_distribution.php">Gene Variants </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/population.php">Population Frequencies</a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/alphamissense.php">AlphaMissense</a>
            </div>
        </div>

        <!-- Network Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="#">Networks <i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet/ppi_network.php">Interaction & Modules </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/multicens_2.php">Inter-Tissue Communication </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/tf_new.php">TF & TFBS </a>
            </div>
        </div>
			   
        <!-- Rhythm Dropdown -->
        <a href="https://datascience.imtech.res.in/anshu/circanet/circust.php">Rhythmicity</a>
       
        <!-- Disease Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="https://datascience.imtech.res.in/anshu/circanet/disease.php">Disease<i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet/disease.php">Gene-Disease Associations </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/disease_comorbidity.php">Comorbidity</a>
            </div>
        </div>

        <!-- Expression Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="#">Expression<i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
               <a href="https://datascience.imtech.res.in/anshu/circanet/Cross_tissue.php">Gene Expression </a>
               <a href="https://datascience.imtech.res.in/anshu/circanet/gene_expression_catalog.php">Gene Expression Catalog</a>

            </div>
        </div>

        <!-- Search Dropdown -->
        <div class="dropdown">
            <a class="dropdown-trigger" href="#">Search <i class="fas fa-caret-down ms-1" style="font-size: 12px;"></i></a>
            <div class="dropdown-content">
                <a href="https://datascience.imtech.res.in/anshu/circanet/search.php">Simple Search </a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/advanced_search.php">Advanced Search</a>
                <a href="https://datascience.imtech.res.in/anshu/circanet/comparative_search.php">Comparative Search</a>
            </div>
        </div>
    </nav>
    
    <main class="container mt-4">