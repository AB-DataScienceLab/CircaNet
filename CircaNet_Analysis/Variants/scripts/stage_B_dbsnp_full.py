import os
import subprocess
import duckdb

# --- Paths ---
DBSNP_ANNOTATED_DIR = "/home/anshu/Shweta/Circanet/annotated"
DBSNP_RENAMED_DIR = "/home/anshu/Shweta/Circanet/ClinVar/split/renamed"
CIRC_BIAS_DIR = "/home/anshu/Shweta/Circanet/ClinVar/BIAS_output"

OUT_DIR = "/home/anshu/Shweta/Circanet/circadian_full_parquet"
TMP_DIR = "/home/anshu/Shweta/Circanet/tmp_circadian_tsvs"
os.makedirs(OUT_DIR, exist_ok=True)
os.makedirs(TMP_DIR, exist_ok=True)

acc_to_chr = {
    "NC_000001.11": "chr1",  "NC_000002.12": "chr2",  "NC_000003.12": "chr3",
    "NC_000004.12": "chr4",  "NC_000005.10": "chr5",  "NC_000006.12": "chr6",
    "NC_000007.14": "chr7",  "NC_000008.11": "chr8",  "NC_000009.12": "chr9",
    "NC_000010.11": "chr10", "NC_000011.10": "chr11", "NC_000012.12": "chr12",
    "NC_000013.11": "chr13", "NC_000014.9":  "chr14", "NC_000015.10": "chr15",
    "NC_000016.10": "chr16", "NC_000017.11": "chr17", "NC_000018.10": "chr18",
    "NC_000019.10": "chr19", "NC_000020.11": "chr20", "NC_000021.9":  "chr21",
    "NC_000022.11": "chr22", "NC_000023.11": "chrX",  "NC_000024.10": "chrY",
    "NC_012920.1":  "chrM"
}

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
BASE_COLS = ['CHROM','POS','ID','REF','ALT','RS','GENEINFO','VC']

DTYPES_BASE = {
    'CHROM': 'VARCHAR', 'POS': 'BIGINT', 'ID': 'VARCHAR', 'REF': 'VARCHAR', 'ALT': 'VARCHAR',
    'RS': 'VARCHAR', 'GENEINFO': 'VARCHAR', 'VC': 'VARCHAR'
}
DTYPES_AF = {col: 'DOUBLE' for col in AF_COLS}

af_fmt_tags = "".join([f"\\t%INFO/{c}" for c in AF_COLS])
vcf_format_full = "%CHROM\t%POS\t%ID\t%REF\t%ALT\t%INFO/RS\t%INFO/GENEINFO\t%INFO/VC" + af_fmt_tags + "\n"
vcf_format_no_af = "%CHROM\t%POS\t%ID\t%REF\t%ALT\t%INFO/RS\t%INFO/GENEINFO\t%INFO/VC\n"

con = duckdb.connect()

print("==================================================================")
print("  Stage B: Building circadian_full parquet (Corrected & Deduplicated)")
print("==================================================================")

for acc, chr_name in acc_to_chr.items():
    parquet_path = os.path.join(OUT_DIR, f"circadian_full_{chr_name}.parquet")
    if os.path.exists(parquet_path):
        print(f"SKIP: {chr_name} already done")
        continue

    print(f"\nProcessing {chr_name} ({acc})...")

    annotated_vcf = os.path.join(DBSNP_ANNOTATED_DIR, f"dbsnp_{chr_name}_gnomadAF.vcf.gz")
    renamed_vcf = os.path.join(DBSNP_RENAMED_DIR, f"dbsnp_{chr_name}.vcf.gz")
    tmp_tsv = os.path.join(TMP_DIR, f"dbsnp_{chr_name}.tsv")

    # --- 1. Load dbSNP + gnomAD ---
    if os.path.exists(annotated_vcf):
        cmd = f"bcftools query -f '{vcf_format_full}' '{annotated_vcf}' > '{tmp_tsv}'"
        subprocess.run(cmd, shell=True, check=True)
        con.execute(f"""
            CREATE OR REPLACE TEMP TABLE dbsnp_df AS
            SELECT * FROM read_csv('{tmp_tsv}', delim='\t', header=False, nullstr='.',
                                    names={BASE_COLS + AF_COLS},
                                    types={ {**DTYPES_BASE, **DTYPES_AF} })
        """)
    elif os.path.exists(renamed_vcf):
        cmd = f"bcftools query -f '{vcf_format_no_af}' '{renamed_vcf}' > '{tmp_tsv}'"
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
        con.execute(f"CREATE OR REPLACE TEMP TABLE dbsnp_df AS {pad_select}")
    else:
        print(f"  SKIP: no dbsnp source found for {chr_name}")
        continue

    # --- 2. Load circadian BIAS (Extract True ALT from hgvsg) ---
    circ_bias_file = os.path.join(CIRC_BIAS_DIR, f"circadian_classifications_{acc}.tsv")
    if os.path.exists(circ_bias_file):
        con.execute(f"""
            CREATE OR REPLACE TEMP TABLE circ_bias_clean AS
            SELECT
                '{chr_name}' AS CHROM,
                CAST(position AS BIGINT) AS POS,
                refAllele AS REF,
                CASE 
                    WHEN hgvsg LIKE '%>%' THEN RIGHT(hgvsg, 1)
                    ELSE altAllele 
                END AS ALT,
                variantType AS circ_bias_variantType,
                consequence AS circ_bias_consequence,
                acmgClassification AS circ_acmgClassification,
                hgvsg AS circ_bias_hgvsg,
                hgvsc AS circ_bias_hgvsc,
                hgvsp AS circ_bias_hgvsp,
                aaChange AS circ_bias_aaChange,
                pubmedIds AS circ_bias_pubmedIds,
                associatedDiseases AS circ_bias_associatedDiseases,
                transcript AS circ_bias_transcript
            FROM read_csv('{circ_bias_file}', delim='\t', header=True,
                           nullstr=['.', 'n/a', ''], ignore_errors=True,
                           types={{'position': 'BIGINT', 'refAllele': 'VARCHAR',
                                   'altAllele': 'VARCHAR', 'pubmedIds': 'VARCHAR'}})
        """)
    else:
        con.execute("""
            CREATE OR REPLACE TEMP TABLE circ_bias_clean AS
            SELECT '' AS CHROM, CAST(0 AS BIGINT) AS POS, '' AS REF, '' AS ALT,
                   '' AS circ_bias_variantType, '' AS circ_bias_consequence,
                   '' AS circ_acmgClassification, '' AS circ_bias_hgvsg,
                   '' AS circ_bias_hgvsc, '' AS circ_bias_hgvsp, '' AS circ_bias_aaChange,
                   '' AS circ_bias_pubmedIds, '' AS circ_bias_associatedDiseases,
                   '' AS circ_bias_transcript WHERE 1=0
        """)

    # --- 3. Left join & Deduplicate into 1 Row per Variant ---
    con.execute(f"""
        COPY (
            SELECT
                d.CHROM, d.POS, d.REF, d.ALT,
                FIRST(d.ID) AS ID,
                FIRST(d.RS) AS RS,
                FIRST(d.GENEINFO) AS GENEINFO,
                FIRST(d.VC) AS VC,

                FIRST(d.AF_joint_raw) AS AF_joint_raw, FIRST(d.AF_joint_XX) AS AF_joint_XX, FIRST(d.AF_joint_XY) AS AF_joint_XY,
                FIRST(d.AF_joint_afr) AS AF_joint_afr, FIRST(d.AF_joint_afr_XX) AS AF_joint_afr_XX, FIRST(d.AF_joint_afr_XY) AS AF_joint_afr_XY,
                FIRST(d.AF_joint_ami) AS AF_joint_ami, FIRST(d.AF_joint_ami_XX) AS AF_joint_ami_XX, FIRST(d.AF_joint_ami_XY) AS AF_joint_ami_XY,
                FIRST(d.AF_joint_amr) AS AF_joint_amr, FIRST(d.AF_joint_amr_XX) AS AF_joint_amr_XX, FIRST(d.AF_joint_amr_XY) AS AF_joint_amr_XY,
                FIRST(d.AF_joint_asj) AS AF_joint_asj, FIRST(d.AF_joint_asj_XX) AS AF_joint_asj_XX, FIRST(d.AF_joint_asj_XY) AS AF_joint_asj_XY,
                FIRST(d.AF_joint_eas) AS AF_joint_eas, FIRST(d.AF_joint_eas_XX) AS AF_joint_eas_XX, FIRST(d.AF_joint_eas_XY) AS AF_joint_eas_XY,
                FIRST(d.AF_joint_fin) AS AF_joint_fin, FIRST(d.AF_joint_fin_XX) AS AF_joint_fin_XX, FIRST(d.AF_joint_fin_XY) AS AF_joint_fin_XY,
                FIRST(d.AF_joint_mid) AS AF_joint_mid, FIRST(d.AF_joint_mid_XX) AS AF_joint_mid_XX, FIRST(d.AF_joint_mid_XY) AS AF_joint_mid_XY,
                FIRST(d.AF_joint_nfe) AS AF_joint_nfe, FIRST(d.AF_joint_nfe_XX) AS AF_joint_nfe_XX, FIRST(d.AF_joint_nfe_XY) AS AF_joint_nfe_XY,
                FIRST(d.AF_joint_remaining) AS AF_joint_remaining, FIRST(d.AF_joint_remaining_XX) AS AF_joint_remaining_XX, FIRST(d.AF_joint_remaining_XY) AS AF_joint_remaining_XY,
                FIRST(d.AF_joint_sas) AS AF_joint_sas, FIRST(d.AF_joint_sas_XX) AS AF_joint_sas_XX, FIRST(d.AF_joint_sas_XY) AS AF_joint_sas_XY,
                FIRST(d.AF_joint) AS AF_joint, FIRST(d.faf95_joint) AS faf95_joint,

                MAX(b.circ_bias_variantType) AS circ_bias_variantType,
                MAX(b.circ_bias_consequence) AS circ_bias_consequence,
                MAX(b.circ_acmgClassification) AS circ_acmgClassification,
                MAX(b.circ_bias_hgvsg) AS circ_bias_hgvsg,
                string_agg(DISTINCT b.circ_bias_hgvsc, ', ') AS circ_bias_hgvsc,
                string_agg(DISTINCT b.circ_bias_hgvsp, ', ') AS circ_bias_hgvsp,
                string_agg(DISTINCT b.circ_bias_aaChange, ', ') AS circ_bias_aaChange,
                MAX(b.circ_bias_pubmedIds) AS circ_bias_pubmedIds,
                MAX(b.circ_bias_associatedDiseases) AS circ_bias_associatedDiseases,
                string_agg(DISTINCT b.circ_bias_transcript, ', ') AS circ_bias_transcript

            FROM dbsnp_df d
            LEFT JOIN circ_bias_clean b
                ON d.POS = b.POS AND d.REF = b.REF AND d.ALT = b.ALT
            GROUP BY d.CHROM, d.POS, d.REF, d.ALT
        ) TO '{parquet_path}' (FORMAT PARQUET, COMPRESSION 'ZSTD');
    """)

    if os.path.exists(tmp_tsv):
        os.remove(tmp_tsv)

    row_count = con.execute(f"SELECT COUNT(*) FROM read_parquet('{parquet_path}')").fetchone()[0]
    print(f"  DONE: {parquet_path} ({row_count:,} rows)")

print("\n==================================================================")
print("  Stage B complete: circadian_full_<chr>.parquet written")
print("==================================================================")