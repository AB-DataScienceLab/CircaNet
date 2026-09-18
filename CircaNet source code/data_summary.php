<?php
// Include the header
include_once 'header.php';
?>

<!-- Simple custom styling for the gallery layout -->
<style>
    .summary-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: Arial, sans-serif;
    }
    .summary-title {
        text-align: center;
        margin-bottom: 40px;
        color: #333;
    }
    .image-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 40px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    .image-card h2 {
        font-size: 1.5rem;
        color: #2d3748;
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #edf2f7;
        padding-bottom: 10px;
    }
    .image-card img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
        border: 1px solid #edf2f7;
    }
</style>

<div class="summary-container">
    <h1 class="summary-title">Data Summary</h1>


  <!-- 1) Distribution of circadian-associated genes across resources -->
    <div class="image-card">
        <h2>Distribution of circadian-associated genes across resources</h2>
        <img src="/anshu/circanet/Data%20Summary/Gene_Upset_Plot.png" alt="Gene Upset Plot">
    </div>


    <!-- 2) Tissue Specificity Landscape of Circadian-Associated Genes Across 68 Human Tissues -->
    <div class="image-card">
        <h2>Tissue Specificity Landscape of Circadian-Associated Genes Across 68 Human Tissues</h2>
        <img src="/anshu/circanet/Data%20Summary/Distribution%20of%20genes%20according%20to%20tissue%20specificity%20across%2068%20human%20tissues.png" alt="Distribution of genes according to tissue specificity across 68 human tissues">
    </div>

    <!-- 3) ClinVar Annotation -->
    <div class="image-card">
        <h2>ClinVar Clinical Significance Annotation of Genetic Variants</h2>
        <img src="/anshu/circanet/Data%20Summary/ClinVar%20Annotation.png" alt="ClinVar Annotation">
    </div>

  <!-- 4) AlphaMissense Annotation of Genetic Variants -->
    <div class="image-card">
        <h2>AlphaMissense Annotation of Genetic Variants</h2>
        <img src="/anshu/circanet/Data%20Summary/AlphaMissense%20Annotation.png" alt="AlphaMissense Annotation">
    </div>
 <!-- 5) VEP Consequence Type -->
<!--    <div class="image-card">-->
<!--        <h2>Distribution of VEP Variant Consequences</h2>-->
<!--        <img src="/anshu/circanet/Data%20Summary/VEP%20Consequence%20Type.png" alt="VEP Consequence Type">-->
<!--    </div>-->

  <!-- 6) Distribution of the Top 20 Diseases Associated with Circadian Rhythm-->
    <div class="image-card">
        <h2>Distribution of the Top 20 Diseases Associated with Circadian Rhythm</h2>
        <img src="/anshu/circanet/Data%20Summary/Figure4_Treemap.png" alt="Treemap">
    </div>

		
    <!-- 7) Top Tissue of Maximum Expression for Circadian-Associated Genes -->
<!--    <div class="image-card">-->
<!--        <h2>Top Tissue of Maximum Expression for Circadian-Associated Genes</h2>-->
<!--        <img src="/anshu/circanet/Data%20Summary/Top-Tissue.png" alt="Top-Tissue">-->
<!--    </div>-->
<!---->
   
  
</div>

<?php
// Include the footer
include_once 'footer.php';
?>