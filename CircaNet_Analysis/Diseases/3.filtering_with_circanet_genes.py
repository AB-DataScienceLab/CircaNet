import pandas as pd

# -----------------------------
# Load data
# -----------------------------
df = pd.read_csv("filtered_gda.tsv", sep="\t")

print("\n===== INITIAL DATA =====")
print("Total rows:", len(df))
print("Unique genes:", df["targetId"].nunique())
print("Unique diseases:", df["diseaseId"].nunique())


# -----------------------------
# Step 1: Remove non-disease
# -----------------------------
bad_types = ["GO", "HP", "MP", "GSSO", "OBA"]

df["disease_type"] = df["diseaseId"].str.split("_").str[0]

df_clean = df[~df["disease_type"].isin(bad_types)]

print("\n===== AFTER REMOVING NON-DISEASE =====")
print("Rows:", len(df_clean))
print("Unique genes:", df_clean["targetId"].nunique())
print("Unique diseases:", df_clean["diseaseId"].nunique())


# -----------------------------
# Step 2: Apply threshold
# -----------------------------
SCORE_THRESHOLD = 0.2197
EVIDENCE_THRESHOLD = 2

df_thresh = df_clean[
    (df_clean["associationScore"] >= SCORE_THRESHOLD) &
    (df_clean["evidenceCount"] >= EVIDENCE_THRESHOLD)
]

print("\n===== AFTER THRESHOLD =====")
print("Rows:", len(df_thresh))
print("Unique genes:", df_thresh["targetId"].nunique())
print("Unique diseases:", df_thresh["diseaseId"].nunique())


# -----------------------------
# Step 3: Load circadian genes
# -----------------------------
with open("ensembl_ids") as f:
    circadian_genes = set(line.strip() for line in f)


# -----------------------------
# Step 4: Filter circadian
# -----------------------------
df_final = df_thresh[df_thresh["targetId"].isin(circadian_genes)]

print("\n===== FINAL (CIRCADIAN GDA) =====")
print("Rows:", len(df_final))
print("Unique genes:", df_final["targetId"].nunique())
print("Unique diseases:", df_final["diseaseId"].nunique())


# -----------------------------
# Step 5: Retention stats
# -----------------------------
print("\n===== RETENTION =====")
print("After disease cleaning: {:.2f}%".format(100 * len(df_clean)/len(df)))
print("After threshold: {:.2f}%".format(100 * len(df_thresh)/len(df)))
print("Final circadian: {:.2f}%".format(100 * len(df_final)/len(df)))


# -----------------------------
# Save final dataset
# -----------------------------
df_final.to_csv("final_circadian_gda.tsv", sep="\t", index=False)
