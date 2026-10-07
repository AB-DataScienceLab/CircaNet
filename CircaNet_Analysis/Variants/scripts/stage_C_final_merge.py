import os
import duckdb

CLINVAR_FULL_DIR = "/home/anshu/Shweta/Circanet/clinvar_full_parquet"
CIRCADIAN_FULL_DIR = "/home/anshu/Shweta/Circanet/circadian_full_parquet"
AM_DEDUP_FILE = "/home/anshu/Shweta/Circanet/ClinVar/Alphamissense_merged_unique.parquet"

OUT_DIR = "/home/anshu/Shweta/Circanet/master_parquet"
os.makedirs(OUT_DIR, exist_ok=True)

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

# Fields present on BOTH sides (circadian 'd' preferred, clinvar 'c' fallback)
# tuple: (output_name, d_column, c_column)
COALESCE_ANNOT = [
    ('RS',            'd.RS',            'c.RS'),
    ('ID',            'd.ID',            'c.ID'),
    ('GENEINFO',      'd.GENEINFO',      'c.GENEINFO'),
    ('VC',            'd.VC',            'c.CLNVC'),
    ('variantType',        'd.circ_bias_variantType',        'c.clinvar_variantType'),
    ('consequence',        'd.circ_bias_consequence',        'c.clinvar_consequence'),
    ('acmgClassification', 'd.circ_acmgClassification',      'c.clinvar_acmgClassification'),
    ('hgvsg',              'd.circ_bias_hgvsg',               'c.clinvar_hgvsg'),
    ('hgvsc',              'd.circ_bias_hgvsc',               'c.clinvar_hgvsc'),
    ('hgvsp',              'd.circ_bias_hgvsp',               'c.clinvar_hgvsp'),
    ('aaChange',           'd.circ_bias_aaChange',            'c.clinvar_aaChange'),
    ('pubmedIds',          'd.circ_bias_pubmedIds',           'c.clinvar_pubmedIds'),
    ('associatedDiseases', 'd.circ_bias_associatedDiseases',  'c.clinvar_associatedDiseases'),
    ('transcript',         'd.circ_bias_transcript',          'c.clinvar_transcript'),
]

con = duckdb.connect()

print("==================================================================")
print("  Final Merge: circadian_full + clinvar_full + AlphaMissense")
print("==================================================================")

for chr_name in CHRS:
    circadian_path = os.path.join(CIRCADIAN_FULL_DIR, f"circadian_full_{chr_name}.parquet")
    clinvar_path = os.path.join(CLINVAR_FULL_DIR, f"clinvar_full_{chr_name}.parquet")
    out_path = os.path.join(OUT_DIR, f"master_{chr_name}.parquet")

    if os.path.exists(out_path):
        print(f"SKIP: {chr_name} already done")
        continue

    print(f"\nProcessing {chr_name}...")

    d_exists = os.path.exists(circadian_path)
    c_exists = os.path.exists(clinvar_path)

    if not d_exists and not c_exists:
        print(f"  SKIP: no circadian_full or clinvar_full data for {chr_name}")
        continue

    # --- Load AlphaMissense (dedup) filtered to this chromosome ---
    con.execute(f"""
        CREATE OR REPLACE TEMP TABLE am_df AS
        SELECT
            CHROM, CAST(POS AS BIGINT) AS POS, REF, ALT,
            uniprot_id,
            am_transcript,
            am_protein_variant,
            am_pathogenicity,
            am_class
        FROM read_parquet('{AM_DEDUP_FILE}')
        WHERE CHROM = '{chr_name}'
    """)

    # --- Load circadian_full (dbsnp + gnomAD + circadian BIAS) ---
    if d_exists:
        con.execute(f"CREATE OR REPLACE TEMP TABLE d_df AS SELECT * FROM read_parquet('{circadian_path}')")
    else:
        cols = "CHROM VARCHAR, POS BIGINT, ID VARCHAR, REF VARCHAR, ALT VARCHAR, RS VARCHAR, GENEINFO VARCHAR, VC VARCHAR"
        for c in AF_COLS:
            cols += f", {c} DOUBLE"
        cols += (", circ_bias_variantType VARCHAR, circ_bias_consequence VARCHAR, circ_acmgClassification VARCHAR,"
                 " circ_bias_hgvsg VARCHAR, circ_bias_hgvsc VARCHAR, circ_bias_hgvsp VARCHAR, circ_bias_aaChange VARCHAR,"
                 " circ_bias_pubmedIds VARCHAR, circ_bias_associatedDiseases VARCHAR, circ_bias_transcript VARCHAR")
        con.execute(f"CREATE OR REPLACE TEMP TABLE d_df AS SELECT * FROM (SELECT NULL::STRUCT({cols}) x) WHERE 1=0")

    # --- Load clinvar_full (ClinVar + gnomAD + ClinVar-BIAS + EVEE) ---
    if c_exists:
        con.execute(f"CREATE OR REPLACE TEMP TABLE c_df AS SELECT * FROM read_parquet('{clinvar_path}')")
    else:
        cols = "CHROM VARCHAR, POS BIGINT, ID VARCHAR, REF VARCHAR, ALT VARCHAR, CLNSIG VARCHAR, CLNDN VARCHAR, CLNREVSTAT VARCHAR, CLNVC VARCHAR, GENEINFO VARCHAR, RS VARCHAR"
        for c in AF_COLS:
            cols += f", {c} DOUBLE"
        cols += (", clinvar_variantType VARCHAR, clinvar_consequence VARCHAR, clinvar_acmgClassification VARCHAR,"
                 " clinvar_hgvsg VARCHAR, clinvar_hgvsc VARCHAR, clinvar_hgvsp VARCHAR, clinvar_aaChange VARCHAR,"
                 " clinvar_pubmedIds VARCHAR, clinvar_associatedDiseases VARCHAR, clinvar_transcript VARCHAR,"
                 " evee_clinvar_significance VARCHAR, evee_clinvar_review_status VARCHAR, evo2_score DOUBLE, EVO2_prediction VARCHAR")
        con.execute(f"CREATE OR REPLACE TEMP TABLE c_df AS SELECT * FROM (SELECT NULL::STRUCT({cols}) x) WHERE 1=0")

    # --- Master coordinate union across all 3 sources ---
    con.execute("""
        CREATE OR REPLACE TEMP TABLE master_coords AS
        SELECT CHROM, POS, REF, ALT FROM d_df
        UNION
        SELECT CHROM, POS, REF, ALT FROM c_df
        UNION
        SELECT CHROM, POS, REF, ALT FROM am_df
    """)

    coalesce_select = ",\n                ".join(
        [f"COALESCE({d_col}, {c_col}) AS {name}" for name, d_col, c_col in COALESCE_ANNOT]
    )
    af_coalesce_select = ",\n                ".join(
        [f"COALESCE(d.{c}, c.{c}) AS {c}" for c in AF_COLS]
    )

    con.execute(f"""
        COPY (
            SELECT
                mc.CHROM, mc.POS, mc.REF, mc.ALT,

                {coalesce_select},

                -- AF_joint columns, dbsnp/gnomAD side preferred, ClinVar/gnomAD side as fallback
                {af_coalesce_select},

                -- ClinVar-exclusive fields, never coalesced
                c.CLNSIG, c.CLNDN, c.CLNREVSTAT,

                -- EVEE, ClinVar-exclusive
                c.evee_clinvar_significance, c.evee_clinvar_review_status,
                c.evo2_score, c.EVO2_prediction,

                -- AlphaMissense, no conflict source
                am.uniprot_id, am.am_transcript, am.am_protein_variant,
                am.am_pathogenicity, am.am_class

            FROM master_coords mc
            LEFT JOIN d_df d ON mc.POS = d.POS AND mc.REF = d.REF AND mc.ALT = d.ALT
            LEFT JOIN c_df c ON mc.POS = c.POS AND mc.REF = c.REF AND mc.ALT = c.ALT
            LEFT JOIN am_df am ON mc.POS = am.POS AND mc.REF = am.REF AND mc.ALT = am.ALT
        ) TO '{out_path}' (FORMAT PARQUET, COMPRESSION 'ZSTD');
    """)

    row_count = con.execute(f"SELECT COUNT(*) FROM read_parquet('{out_path}')").fetchone()[0]
    print(f"  DONE: {out_path} ({row_count} rows)")

print("\n==================================================================")
print("  Final master parquet complete for all chromosomes")
print("==================================================================")