# CircaNet – A Circadian Biology Atlas

[![License: CC BY 4.0](https://img.shields.io/badge/License-CC%20BY%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by/4.0/)
[![CSIR-IMTech](https://img.shields.io/badge/Affiliation-CSIR--IMTech-orange.svg)](https://www.imtech.res.in/)
[![Web Portal](https://img.shields.io/badge/Portal-Live-brightgreen.svg)](https://datascience.imtech.res.in/anshu/circanet/)
---

[![Landing Page](IMAGE/graphical_abstract.png)](IMAGE/graphical_abstract.png)

**CircaNet** is currently hosted at [CircaNet - A Circadian Biology Atlas](https://datascience.imtech.res.in/anshu/circanet/)

---

CircaNet is an integrated, curated, and value-added knowledge-base for human circadian biology. CircaNet is a dedicated multi-omics circadian resource developed to provide deep insights into clock-controlled mechanisms and expand our understanding of circadian disruption in human health and disease.

CircaNet unifies circadian gene profiles, AlphaFold structural annotations, residue-level biophysical features, variant pathogenicity metrics,  cross-tissue co-expression, tissue-specific rhythmicity, and clinical comorbidity landscapes.

---

## Modules of CircaNet

1. **Base Module**:
   * Gene Catalog
   * Gene Profiles
   * Orthologs

2. **Integrative Module**
   * **Structure**:
   * **Variants**:
     * Variant Browse
     * Gene Variants
     * Population Frequencies
     * Alphamissense
   * **Networks**:
     * Interaction & Modules
     * Cross-tissue co-expression
   * **Rhythmicity**
   * **Disease & Comorbidity**:
     * Gene-Disease Associations
     *  Comorbidity
   * **Expression**:
     * Tissue Expression
     * Gene Expression Catalog

3. **Search Module**:
   * Simple Search
   * Advanced Search
   * Comparative Search

4. **Accessory Modules**:
   * Data Summary & Statistics
   * Data Downloads (Tabular datasets, GFF files, Network edge-lists)
   * FAQs, Tutorials & Help Documentation

---

## Database Architecture

The backend was built using the LAMP stack with PostgreSQL, which includes Linux (operating system), Apache (web server), MySQL (database), and PHP (server-side scripting). The web interface is created with HTML, CSS, JavaScript, and interactive visualization libraries. CircaNet runs on a secure Linux server where Apache handles incoming requests, PHP scripts manage data processing and API logic, and MySQL manages the structured biological data.

---

## Repository Structure

This repository is organized into two main directories: `CircaNet source code/` which contains the source for the Database, and `CircaNet Analysis/` which contains the data generation and analysis pipelines for each module.

Below is a visual representation of the repository's layout:

```text
.
├── CircaNet source code/
│   └── (All scripts and files for the CircaNet Database)
│
└── CircaNet Analysis/
    ├── Base_Module/
    │   ├── Scripts/
    │   └── Readme.md
    │
    ├── Structure_and_Features/
    │   ├── Scripts/
    │   └── Readme.md
    │
    ├── Variants_and_Pathogenicity/
    │   ├── Scripts/
    │   └── Readme.md
    │
    ├── Networks/
    │   ├── Scripts/
    │   └── Readme.md
    │
    ├── Expression_and_Rhythmicity/
    │   ├── Scripts/
    │   └── Readme.md
    │
    └── Disease_Comorbidity/
        ├── Scripts/
        └── Readme.md
