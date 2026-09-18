<?php include 'header.php'; ?>

<style>
  /* Page-specific styles for the FAQ content */
  .faq-container {
    max-width: 1700px;
    margin: 40px auto; /* Add vertical spacing */
    background: #fff;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 10px;
  }
  .faq-container h1 {
    text-align: center;
    color: var(--circanet-secondary); /* Adjusted theme color variable */
    margin-bottom: 40px;
  }
  .faq-item {
    margin-bottom: 25px;
    border-bottom: 1px solid #eee;
    padding-bottom: 25px;
  }
  .faq-item:last-child {
      border-bottom: none;
  }
  .faq-item h3 {
    margin-bottom: 10px;
    color: var(--circanet-primary); /* Adjusted theme color variable */
    font-size: 1.5rem;
  }
  .faq-item p {
    margin: 0;
    line-height: 1.6;
    color: #333; /* Darker text for readability */
  }
  .download-btn {
    display: block;
    margin: 30px auto 0;
    padding: 12px 24px;
    background-color: #007bff; /* Standard blue background color */
    color: white;
    border: none;
    border-radius: 6px;
    text-align: center;
    font-size: 16px;
    text-decoration: none;
    transition: background-color 0.3s;
    width: max-content;
  }
  .download-btn:hover {
    background-color: #0056b3; /* Darker blue on hover */
    color: white; /* Ensure text remains white on hover */
    text-decoration: none;
  }
  .faq-item a {
    color: var(--circanet-primary);
    text-decoration: underline;
    font-weight: 600;
  }
  .faq-item a:hover {
    color: var(--circanet-secondary);
  }
</style>

<div class="faq-container">
  <h1>Frequently Asked Questions (FAQs)</h1>

  <?php
  $faq = [
    [
      'question' => 'What is CircaNet?',
      'answer' => 'CircaNet is an integrated, curated, value-added knowledge base developed to provide a comprehensive resource for exploring human circadian rhythm-associated genes, genetic variants, diseases, regulatory networks, tissue-specific expression, and functional annotations. It integrates multi-omics datasets, structural annotations, and computational predictions to facilitate circadian biology and chronomedicine research from molecular to population scale.'
    ],
    [
      'question' => 'What are the different modules available in CircaNet?',
      'answer' => 'CircaNet is organised into three major categories: Base module, Integrative module and Search module. The Base module consists of circadian-associated genes and orthologs. The Integrative module is a composite module that consists of broadly 6 submodules: Variants (gene-level variants, AlphaMissense Prediction, Evo 2 functional impact scores, and ACMG classifications generated using the BIAS framework, population level, and gender level stratification), Structure (interactive 3D structure with Intrinsically Disordered Regions (IDRs), Liquid-Liquid Phase Separation (LLPS), and variant mapped on to domains), Diseases (Gene-Disease associations, Disease-Disease association), Networks (interactions & community detection, inter-tissue regulators, transcription factors (TFs) & transcription factor binding sites (TFBS)), Rhythmicity (rhythmic genes with circadian parameter across 48 tissues), and Expression Atlas (tissue-wise expression along with tissue-specific indices). The search modules have simple gene search, advanced search and comparative search options for hypothesis generation.<br><br>Each module provides interactive visualisation, filtering, and downloadable datasets for comprehensive exploration of circadian biology.'
    ],
    [
      'question' => 'How are data curated in CircaNet?',
      'answer' => 'CircaNet integrates information from multiple publicly available databases, computational resources, and peer-reviewed literature. All datasets undergo standardised preprocessing, identifier harmonisation, quality control, and comprehensive annotation before integration into the database. Genetic variants are enriched using computational annotation pipelines, including functional consequence prediction, ClinVar clinical interpretation, AlphaMissense pathogenicity prediction, EVO2 functional impact prediction, ACMG variant classification generated using the BIAS framework, population- and sex-specific allele frequency annotation from gnomAD, protein structural mapping, and disease association analyses. In addition, CircaNet incorporates tissue-specific gene expression profiles from GTEx and protein-protein interaction (PPI) networks with community detection analysis and identifies circadian-associated inter-tissue key regulators using computational network-based approaches. These integrated datasets provide a unified and comprehensive resource for studying human circadian biology across molecular, functional, structural, and systems levels.'
    ],
    [
      'question' => 'What is the need for CircaNet?',
      'answer' => 'To the best of our knowledge, there is no dedicated resource that enables understanding of human circadian biology from molecular to population scale. Given the emerging evidence of the role of circadian biology in all aspects of basic and applied research, it is imperative that dedicated and curated resources are made available to the wider scientific community. It is also important to highlight that most of the modules in CircaNet offer data from custom-built pipelines to provide deeper insights towards understanding human circadian biology.'
    ],
    [
      'question' => 'What are the unique features of CircaNet?',
      'answer' => 'CircaNet is a unique resource linking diverse biological datasets into an interactive unified platform. There are several unique features in CircaNet, namely, language model based variant annotation, ACMG variant classification in circadian context, variant interpretation based on LLPS and IDR mapping on protein structures, wide array of network perspectives including community detection, identification of inter-tissue communication regulators and comorbidities network. In addition, tissue-wise rhythmic patterns of genes across 30 tissues and population contexts of circadian gene variants capturing sex disaggregated allele frequencies along with geographical distribution.'
    ],
    [
      'question' => 'Can I download the data available in CircaNet?',
      'answer' => 'Yes. CircaNet provides downloadable datasets for all the modules, either in the download section or the corresponding module, enabling researchers to perform independent analyses, develop computational pipelines, and integrate the data into their own research workflows.'
    ],
    [
      'question' => 'Does CircaNet require registration?',
      'answer' => 'No. CircaNet is an open-access database. All modules can be accessed freely without user registration.'
    ]
  ];

  foreach ($faq as $item) {
    echo "<div class='faq-item'>";
    // Use htmlspecialchars for the question to prevent XSS attacks
    echo "<h3>" . htmlspecialchars($item['question']) . "</h3>";
    // The answer contains HTML tags (like <br>), so it is output directly
    echo "<p>" . $item['answer'] . "</p>";
    echo "</div>";
  }
  ?>

  <!-- Help File Download Button -->
  <a href="Download_File/Help_file_CircaNet.pdf" class="download-btn" download>
    Download Help File
  </a>
</div>

<?php include 'footer.php'; ?>