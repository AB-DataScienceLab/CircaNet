import os
import subprocess
import duckdb

# --- Paths ---
CLINVAR_GNOMAD_DIR = "/home/anshu/Shweta/Circanet/ClinVar/BIAS_clinvar/gnomad_annotated"
CLINVAR_BIAS_FILE = "/home/anshu/Shweta/Circanet/ClinVar/BIAS_clinvar/clinvar_bias_classification.tsv"
EVEE_FILE = "/home/anshu/Shweta/Circanet/ClinVar/Total_filtered_EVEE.parquet"

OUT_DIR = "/home/anshu/Shweta/Circanet/clinvar_full_parquet"
TMP_DIR = "/home/anshu/Shweta/Circanet/tmp_clinvar_tsvs"
os.makedirs(OUT_DIR, exist_ok=True)
os.makedirs(TMP_DIR, exist_ok=True)

CHRS = [f"chr{i}" for i in range(1, 23)] + ["chrX", "chrY", "chrM"]

AF_COLS = [
    'AF_joint_raw','AF_joint_XX','AF_joint_XY',
    'AF_joint_afr','AF_joint_afr_XX','AF_joint_afr_XY',
    'AF_joint_ami','AF_joint_ami_XX','AF_joint_ami_XY',
    'AF_joint_amr','AF_joint_amr_XX','AF_joint_amr_XY',
    'AF_joint_asj','AF_joint_asj_XX','AF_joint_asj_XY',
    'AF_joint_eas','AF_joint_eas_XX','AF_joint_eas_XY',
    'AF_joint_fin','AF_joint_fin_XX','AF_joint_fin_XY',
    'AF_joint_mid','AF_joint_mid_XX','AF_joint_mid_XY',
    'AF_joint_nfe','AF_joint_nfe_XX','AF_joint_nfe_XY',
    'AF_joint_remaining','AF_joint_remaining_XX','AF_joint_remaining_XY',
    'AF_joint_sas','AF_joint_sas_XX','AF_joint_sas_XY',
    'AF_joint','faf95_joint'
]

BASE_COLS = ['CHROM','POS','ID','REF','ALT','CLNSIG','CLNDN','CLNREVSTAT','CLNVC','GENEINFO','RS']

# --- Explicit Data Type Mappings ---
DTYPES_BASE = {
    'CHROM': 'VARCHAR', 'POS': 'BIGINT', 'ID': 'VARCHAR', 'REF': 'VARCHAR', 'ALT': 'VARCHAR',
    'CLNSIG': 'VARCHAR', 'CLNDN': 'VARCHAR', 'CLNREVSTAT': 'VARCHAR', 'CLNVC': 'VARCHAR',
    'GENEINFO': 'VARCHAR', 'RS': 'VARCHAR'
}
DTYPES_AF = {col: 'DOUBLE' for col in AF_COLS}
DTYPES_FULL = {**DTYPES_BASE, **DTYPES_AF}

af_fmt_tags = "".join([f"\\t%INFO/{c}" for c in AF_COLS])
vcf_format_full = (
    "%CHROM\t%POS\t%ID\t%REF\t%ALT\t%INFO/CLNSIG\t%INFO/CLNDN\t%INFO/CLNREVSTAT"
    "\t%INFO/CLNVC\t%INFO/GENEINFO\t%INFO/RS" + af_fmt_tags + "\n"
)
vcf_format_no_af = (
    "%CHROM\t%POS\t%ID\t%REF\t%ALT\t%INFO/CLNSIG\t%INFO/CLNDN\t%INFO/CLNREVSTAT"
    "\t%INFO/CLNVC\t%INFO/GENEINFO\t%INFO/RS\n"
)

con = duckdb.connect()

print("==================================================================")
print("  Stage A: Building clinvar_full parquet (per chromosome)")
print("==================================================================")

for chr_name in CHRS:
    print(f"\nProcessing {chr_name}...")

    vcf_path = os.path.join(CLINVAR_GNOMAD_DIR, f"clinvar_{chr_name}_gnomadAF.vcf.gz")
    if not os.path.exists(vcf_path):
        print(f"  SKIP: {vcf_path} not found")
        continue

    tmp_tsv = os.path.join(TMP_DIR, f"clinvar_{chr_name}.tsv")

    # --- 1. Extract ClinVar + gnomAD fields with Explicit Types ---
    if chr_name == "chrM":
        cmd = f"bcftools query -f '{vcf_format_no_af}' '{vcf_path}' > '{tmp_tsv}'"
        subprocess.run(cmd, shell=True, check=True)
        con.execute(f"""
            CREATE OR REPLACE TEMP TABLE base_df AS
            SELECT * FROM read_csv('{tmp_tsv}', delim='\t', header=False, nullstr='.',
                                    names={BASE_COLS}, types={DTYPES_BASE})
        """)
        pad_select = "SELECT " + ", ".join(BASE_COLS)
        for col in AF_COLS:
            pad_select += f", CAST(NULL AS DOUBLE) AS {col}"
        pad_select += " FROM base_df"
        con.execute(f"CREATE OR REPLACE TEMP TABLE clinvar_gnomad_df AS {pad_select}")
    else:
        cmd = f"bcftools query -f '{vcf_format_full}' '{vcf_path}' > '{tmp_tsv}'"
        subprocess.run(cmd, shell=True, check=True)
        con.execute(f"""
            CREATE OR REPLACE TEMP TABLE clinvar_gnomad_df AS
            SELECT * FROM read_csv('{tmp_tsv}', delim='\t', header=False, nullstr='.',
                                    names={BASE_COLS + AF_COLS},
                                    types={DTYPES_FULL})
        """)

    # --- 2. Load ClinVar-BIAS (Extract True ALT from hgvsg) ---
    con.execute(f"""
        CREATE OR REPLACE TEMP TABLE clinvar_bias_clean AS
        SELECT
            CASE 
                WHEN chromosome = 'chrMT' OR chromosome = 'MT' THEN 'chrM'
                WHEN chromosome NOT LIKE 'chr%' THEN 'chr' || chromosome 
                ELSE chromosome 
            END AS CHROM,
            CAST(position AS INT) AS POS,
            refAllele AS REF,
            CASE 
                WHEN hgvsg LIKE '%>%' THEN RIGHT(hgvsg, 1)
                ELSE altAllele 
            END AS ALT,
            variantType AS clinvar_variantType,
            consequence AS clinvar_consequence,
            acmgClassification AS clinvar_acmgClassification,
            hgvsg AS clinvar_hgvsg,
            hgvsc AS clinvar_hgvsc,
            hgvsp AS clinvar_hgvsp,
            aaChange AS clinvar_aaChange,
            pubmedIds AS clinvar_pubmedIds,
            associatedDiseases AS clinvar_associatedDiseases,
            transcript AS clinvar_transcript
        FROM read_csv('{CLINVAR_BIAS_FILE}', delim='\t', header=True,
                       nullstr=['.', 'n/a', ''], ignore_errors=True)
        WHERE (CASE WHEN chromosome = 'chrMT' OR chromosome = 'MT' THEN 'chrM' WHEN chromosome NOT LIKE 'chr%' THEN 'chr' || chromosome ELSE chromosome END) = '{chr_name}'
    """)

    # --- 3. Load EVEE, filtered to this chromosome ---
    con.execute(f"""
        CREATE OR REPLACE TEMP TABLE evee_clean AS
        SELECT
            CASE WHEN chrom = 'chrMT' OR chrom = 'MT' THEN 'chrM'
                 WHEN chrom NOT LIKE 'chr%' THEN 'chr' || chrom
                 ELSE chrom END AS CHROM,
            CAST(pos AS INT) + 1 AS POS,
            ref AS REF,
            alt AS ALT,
            significance AS evee_clinvar_significance,
            review_status AS evee_clinvar_review_status,
            CAST(pathogenicity AS DOUBLE) AS evo2_score,
            evee_classification AS EVO2_prediction
        FROM read_parquet('{EVEE_FILE}')
        WHERE (CASE WHEN chrom = 'chrMT' OR chrom = 'MT' THEN 'chrM'
                    WHEN chrom NOT LIKE 'chr%' THEN 'chr' || chrom
                    ELSE chrom END) = '{chr_name}'
    """)

    # --- 4. Join & Deduplicate into 1 Row per Variant ---
    parquet_path = os.path.join(OUT_DIR, f"clinvar_full_{chr_name}.parquet")
    con.execute(f"""
        COPY (
            SELECT
                c.CHROM, c.POS, c.REF, c.ALT,
                FIRST(c.ID) AS ID,
                MAX(c.CLNSIG) AS CLNSIG,
                MAX(c.CLNDN) AS CLNDN,
                MAX(c.CLNREVSTAT) AS CLNREVSTAT,
                MAX(c.CLNVC) AS CLNVC,
                FIRST(c.GENEINFO) AS GENEINFO,
                FIRST(c.RS) AS RS,

                FIRST(c.AF_joint_raw) AS AF_joint_raw, FIRST(c.AF_joint_XX) AS AF_joint_XX, FIRST(c.AF_joint_XY) AS AF_joint_XY,
                FIRST(c.AF_joint_afr) AS AF_joint_afr, FIRST(c.AF_joint_afr_XX) AS AF_joint_afr_XX, FIRST(c.AF_joint_afr_XY) AS AF_joint_afr_XY,
                FIRST(c.AF_joint_ami) AS AF_joint_ami, FIRST(c.AF_joint_ami_XX) AS AF_joint_ami_XX, FIRST(c.AF_joint_ami_XY) AS AF_joint_ami_XY,
                FIRST(c.AF_joint_amr) AS AF_joint_amr, FIRST(c.AF_joint_amr_XX) AS AF_joint_amr_XX, FIRST(c.AF_joint_amr_XY) AS AF_joint_amr_XY,
                FIRST(c.AF_joint_asj) AS AF_joint_asj, FIRST(c.AF_joint_asj_XX) AS AF_joint_asj_XX, FIRST(c.AF_joint_asj_XY) AS AF_joint_asj_XY,
                FIRST(c.AF_joint_eas) AS AF_joint_eas, FIRST(c.AF_joint_eas_XX) AS AF_joint_eas_XX, FIRST(c.AF_joint_eas_XY) AS AF_joint_eas_XY,
                FIRST(c.AF_joint_fin) AS AF_joint_fin, FIRST(c.AF_joint_fin_XX) AS AF_joint_fin_XX, FIRST(c.AF_joint_fin_XY) AS AF_joint_fin_XY,
                FIRST(c.AF_joint_mid) AS AF_joint_mid, FIRST(c.AF_joint_mid_XX) AS AF_joint_mid_XX, FIRST(c.AF_joint_mid_XY) AS AF_joint_mid_XY,
                FIRST(c.AF_joint_nfe) AS AF_joint_nfe, FIRST(c.AF_joint_nfe_XX) AS AF_joint_nfe_XX, FIRST(c.AF_joint_nfe_XY) AS AF_joint_nfe_XY,
                FIRST(c.AF_joint_remaining) AS AF_joint_remaining, FIRST(c.AF_joint_remaining_XX) AS AF_joint_remaining_XX, FIRST(c.AF_joint_remaining_XY) AS AF_joint_remaining_XY,
                FIRST(c.AF_joint_sas) AS AF_joint_sas, FIRST(c.AF_joint_sas_XX) AS AF_joint_sas_XX, FIRST(c.AF_joint_sas_XY) AS AF_joint_sas_XY,
                FIRST(c.AF_joint) AS AF_joint, FIRST(c.faf95_joint) AS faf95_joint,

                MAX(b.clinvar_variantType) AS clinvar_variantType,
                MAX(b.clinvar_consequence) AS clinvar_consequence,
                MAX(b.clinvar_acmgClassification) AS clinvar_acmgClassification,
                MAX(b.clinvar_hgvsg) AS clinvar_hgvsg,
                string_agg(DISTINCT b.clinvar_hgvsc, ', ') AS clinvar_hgvsc,
                string_agg(DISTINCT b.clinvar_hgvsp, ', ') AS clinvar_hgvsp,
                string_agg(DISTINCT b.clinvar_aaChange, ', ') AS clinvar_aaChange,
                MAX(b.clinvar_pubmedIds) AS clinvar_pubmedIds,
                MAX(b.clinvar_associatedDiseases) AS clinvar_associatedDiseases,
                string_agg(DISTINCT b.clinvar_transcript, ', ') AS clinvar_transcript,

                MAX(e.evee_clinvar_significance) AS evee_clinvar_significance,
                MAX(e.evee_clinvar_review_status) AS evee_clinvar_review_status,
                MAX(e.evo2_score) AS evo2_score,
                MAX(e.EVO2_prediction) AS EVO2_prediction

            FROM clinvar_gnomad_df c
            LEFT JOIN clinvar_bias_clean b
                ON c.POS = b.POS AND c.REF = b.REF AND c.ALT = b.ALT
            LEFT JOIN evee_clean e
                ON c.POS = e.POS AND c.REF = e.REF AND c.ALT = e.ALT
            GROUP BY c.CHROM, c.POS, c.REF, c.ALT
        ) TO '{parquet_path}' (FORMAT PARQUET, COMPRESSION 'ZSTD');
    """)

    if os.path.exists(tmp_tsv):
        os.remove(tmp_tsv)

    row_count = con.execute(f"SELECT COUNT(*) FROM read_parquet('{parquet_path}')").fetchone()[0]
    print(f"  DONE: {parquet_path} ({row_count:,} rows)")

print("\n==================================================================")
print("  Stage A complete: clinvar_full_<chr>.parquet written for all chromosomes")
print("==================================================================")