import os
import duckdb

AM_TSV = "/home/anshu/Shweta/Circanet/ClinVar/Alphamissense_merged.tsv"
AM_PARQUET_OUT = "/home/anshu/Shweta/Circanet/ClinVar/Alphamissense_merged_unique.parquet"

con = duckdb.connect()

print("==================================================================")
print("  Creating Clean Unique AlphaMissense Parquet (1 Row / Variant)")
print("==================================================================")

con.execute(f"""
    COPY (
        SELECT 
            CHROM,
            CAST(POS AS BIGINT) AS POS,
            REF,
            ALT,
            MAX(uniprot_id) AS uniprot_id,
            string_agg(DISTINCT transcript_id, ', ') AS am_transcript,
            string_agg(DISTINCT protein_variant, ', ') AS am_protein_variant,
            MAX(CAST(am_pathogenicity AS DOUBLE)) AS am_pathogenicity,
            MAX_BY(am_class, CAST(am_pathogenicity AS DOUBLE)) AS am_class
        FROM read_csv('{AM_TSV}', delim='\t', header=True)
        GROUP BY CHROM, POS, REF, ALT
    ) TO '{AM_PARQUET_OUT}' (FORMAT PARQUET, COMPRESSION 'ZSTD');
""")

# Verify row counts
row_count = con.execute(f"SELECT COUNT(*) FROM read_parquet('{AM_PARQUET_OUT}')").fetchone()[0]
unique_cnt = con.execute(f"SELECT COUNT(DISTINCT (CHROM, POS, REF, ALT)) FROM read_parquet('{AM_PARQUET_OUT}')").fetchone()[0]

print(f"\nSUCCESS!")
print(f"  • Total Unique Rows Written : {row_count:,}")
print(f"  • Unique Variant Coordinates: {unique_cnt:,}")
print(f"  • File Saved To             : {AM_PARQUET_OUT}")
print("==================================================================")