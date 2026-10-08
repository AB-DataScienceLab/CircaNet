import pandas as pd
import numpy as np
import os
import sys

# ══════════════════════════════════════════════════════════════════════════════
# CONFIG
# ══════════════════════════════════════════════════════════════════════════════
GCT_FILE  = "GTEx_Analysis_2025-08-22_v11_RNASeQCv2.4.3_gene_median_tpm.gct"
GENE_FILE = "circadian_genes.txt" 
OUT_DIR   = "."
# ══════════════════════════════════════════════════════════════════════════════

print("1. Loading and Filtering Data...")
# Load GTEx GCT (skip 2 header rows)
df = pd.read_csv(GCT_FILE, sep="\t", skiprows=2)
df['Name'] = df['Name'].str.replace(r"\.\d+$", "", regex=True)

if os.path.exists(GENE_FILE):
    with open(GENE_FILE, 'r') as f:
        target_genes = [line.strip().split('.')[0] for line in f]
    df = df[df['Name'].isin(target_genes)].copy()
    print(f"   Filtered to {len(df)} genes.")

# Matrix Preparation
gene_ids = df['Name'].values
gene_syms = df['Description'].values
tissues = df.columns[2:].tolist()
X_raw = df.iloc[:, 2:].values.astype(float)

# 2. STANDARD TRANSFORMATION (Reviewer-Friendly: Log2(TPM + 1))
print("2. Applying Log2(TPM + 1) transformation...")
X = np.log2(X_raw + 1)
n_tissues = X.shape[1]

# 3. COMPUTING TSPEX METRICS (Vectorized for Stability)
print("3. Computing tspex-standard metrics...")

# --- TAU (Yanai et al.) ---
x_max = np.max(X, axis=1, keepdims=True)
x_max_adj = np.where(x_max == 0, 1, x_max) # Avoid division by zero
tau = np.sum(1 - (X / x_max_adj), axis=1) / (n_tissues - 1)
tau = np.where(x_max.flatten() == 0, 0, tau)

# --- GINI Coefficient ---
def calc_gini(arr):
    sorted_arr = np.sort(arr, axis=1)
    index = np.arange(1, n_tissues + 1)
    return (np.sum((2 * index - n_tissues - 1) * sorted_arr, axis=1)) / (n_tissues * np.sum(sorted_arr, axis=1) + 1e-12)
gini = calc_gini(X)

# --- SPM (Specificity Measure - Xiao et al.) ---
# This generates a value for EVERY tissue (Distribution)
spm_denom = np.sqrt(np.sum(X**2, axis=1))
spm_dist = X / spm_denom[:, np.newaxis]
spm_max = np.max(spm_dist, axis=1)

# --- TSI (Tissue Specificity Index) ---
# This generates a value for EVERY tissue (Distribution)
tsi_denom = np.sum(X, axis=1)
tsi_dist = X / (tsi_denom[:, np.newaxis] + 1e-12)
tsi_max = np.max(tsi_dist, axis=1)

# --- Z-SCORE ---
mu = np.mean(X, axis=1, keepdims=True)
std = np.std(X, axis=1, keepdims=True) + 1e-12
z_dist = (X - mu) / std
z_max = np.max(z_dist, axis=1)

# 4. PREPARING OUTPUTS
print("4. Saving Standardized Outputs...")

# A. Summary Table (tissue_specificity.csv)
result = pd.DataFrame({
    "Gene_ID": gene_ids,
    "Gene_Symbol": gene_syms,
    "Top_Tissue": [tissues[i] for i in np.argmax(X, axis=1)],
    "Max_Log2_TPM": np.max(X, axis=1).round(3),
    "Tau": tau.round(4),
    "Gini": gini.round(4),
    "TSI_max": tsi_max.round(4),
    "SPM_max": spm_max.round(4),
    "Z_max": z_max.round(4),
})

# Standard Classification
result["Specificity_Class"] = np.where(result["Tau"] >= 0.85, "Highly Specific",
                              np.where(result["Tau"] <= 0.15, "Housekeeping", "Intermediate"))

result.to_csv(f"{OUT_DIR}/tissue_specificity.csv", index=False)

# B. Full Gene-Wise Distribution (Log2 TPM Matrix)
tpm_dist = pd.DataFrame(X, index=gene_ids, columns=tissues)
tpm_dist.insert(0, "Gene_Symbol", gene_syms)
tpm_dist.to_csv(f"{OUT_DIR}/tissue_distribution.csv")

# C. Full Metric Distributions (Optional but useful for heatmaps)
spm_df = pd.DataFrame(spm_dist, index=gene_ids, columns=tissues)
spm_df.to_csv(f"{OUT_DIR}/spm_distribution_matrix.csv")

print(f"\n✓ DONE! All files written to {OUT_DIR}")
print(f"- Summary: tissue_specificity.csv")
print(f"- Distribution (Log2TPM): tissue_distribution.csv")
print(f"- SPM Matrix: spm_distribution_matrix.csv")

# Final summary for console
print("\n--- Summary ---")
print(result["Specificity_Class"].value_counts().to_string())
