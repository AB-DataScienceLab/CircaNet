import pandas as pd
import time

start_time = time.time()

print("Loading files...")

f1 = pd.read_csv("Ids.txt", sep="\t")

f2 = pd.read_csv(
    "AlphaMissense_hg38.tsv.gz",
    sep="\t",
    compression="gzip",
    comment="#",
    header=None
)

f2.columns = [
    "CHROM", "POS", "REF", "ALT", "genome",
    "uniprot_id", "transcript_id",
    "protein_variant", "am_pathogenicity", "am_class"
]

print("Merging...")

f3 = pd.merge(f1, f2, on="uniprot_id")

print("Saving...")

f3.to_csv("merged.tsv", sep="\t", index=False)

end_time = time.time()

print(f"Total runtime: {round((end_time - start_time)/60, 2)} minutes")
