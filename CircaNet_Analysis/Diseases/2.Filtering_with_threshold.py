import pandas as pd
import numpy as np
from scipy.stats import skew, kurtosis, spearmanr, pearsonr
import logging
import sys

# -------------------------------
# CONFIGURATION
# -------------------------------
INPUT_FILE = "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/ReAnalysis/Disease/1OpenTarget/Threshold_determination/association_overall_direct.tsv"
OUTPUT_FILE = "filtered_gda.tsv"

SCORE_THRESHOLD = 0.2197   # P90
EVIDENCE_THRESHOLD = 2


# -------------------------------
# LOGGING SETUP
# -------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s  %(message)s",
    datefmt="%H:%M:%S"
)

log = logging.getLogger()


# -------------------------------
# MAIN
# -------------------------------
def main():

    log.info("╔══════════════════════════════════════╗")
    log.info("║   GDA FILTERING + LOG ANALYSIS       ║")
    log.info("╚══════════════════════════════════════╝")

    # -------------------------------
    # LOAD DATA
    # -------------------------------
    log.info("STEP 1 – LOADING DATA")
    df = pd.read_csv(INPUT_FILE, sep="\t")

    log.info(f"Rows loaded : {len(df):,}")
    log.info(f"Columns     : {list(df.columns)}")

    # -------------------------------
    # FILTERING
    # -------------------------------
    log.info("STEP 2 – APPLYING FILTER")

    filtered = df[
        (df["associationScore"] >= SCORE_THRESHOLD) &
        (df["evidenceCount"] >= EVIDENCE_THRESHOLD)
    ]

    log.info(f"Thresholds applied:")
    log.info(f"  associationScore >= {SCORE_THRESHOLD}")
    log.info(f"  evidenceCount   >= {EVIDENCE_THRESHOLD}")

    log.info(f"Rows before : {len(df):,}")
    log.info(f"Rows after  : {len(filtered):,}")
    log.info(f"Retained %  : {(len(filtered)/len(df))*100:.2f}%")

    # -------------------------------
    # BASIC STATS
    # -------------------------------
    log.info("STEP 3 – BASIC STATS")

    log.info(f"Unique genes    : {filtered['targetId'].nunique():,}")
    log.info(f"Unique diseases : {filtered['diseaseId'].nunique():,}")

    # -------------------------------
    # SCORE DISTRIBUTION
    # -------------------------------
    scores = filtered["associationScore"]

    log.info("STEP 4 – SCORE DISTRIBUTION")

    log.info(f"mean     : {scores.mean():.4f}")
    log.info(f"median   : {scores.median():.4f}")
    log.info(f"std      : {scores.std():.4f}")
    log.info(f"skewness : {skew(scores):.4f}")
    log.info(f"kurtosis : {kurtosis(scores):.4f}")
    log.info(f"min      : {scores.min():.4f}")
    log.info(f"max      : {scores.max():.4f}")

    for p in [50, 75, 90, 95, 99]:
        val = np.percentile(scores, p)
        log.info(f"P{p:<2}     : {val:.4f}")

    # -------------------------------
    # EVIDENCE DISTRIBUTION
    # -------------------------------
    evidence = filtered["evidenceCount"]

    log.info("STEP 5 – EVIDENCE DISTRIBUTION")

    log.info(f"mean   : {evidence.mean():.2f}")
    log.info(f"median : {evidence.median():.2f}")
    log.info(f"min    : {evidence.min()}")
    log.info(f"max    : {evidence.max()}")

    for p in [50, 75, 90, 95]:
        val = np.percentile(evidence, p)
        log.info(f"P{p:<2}     : {val}")

    # -------------------------------
    # CORRELATION
    # -------------------------------
    log.info("STEP 6 – SCORE vs EVIDENCE")

    pearson_r, _ = pearsonr(scores, evidence)
    spearman_r, _ = spearmanr(scores, evidence)

    log.info(f"Pearson r  : {pearson_r:.4f}")
    log.info(f"Spearman ρ : {spearman_r:.4f}")

    # -------------------------------
    # TOP GENES / DISEASES
    # -------------------------------
    log.info("STEP 7 – TOP ENTITIES")

    top_genes = filtered["targetId"].value_counts().head(5)
    top_diseases = filtered["diseaseId"].value_counts().head(5)

    log.info("Top Genes:")
    for gene, count in top_genes.items():
        log.info(f"  {gene} : {count}")

    log.info("Top Diseases:")
    for dis, count in top_diseases.items():
        log.info(f"  {dis} : {count}")

    # -------------------------------
    # SAVE OUTPUT
    # -------------------------------
    log.info("STEP 8 – SAVING FILE")

    filtered.to_csv(OUTPUT_FILE, sep="\t", index=False)

    log.info(f"Saved: {OUTPUT_FILE}")

    log.info("══════════════════════════════════════")
    log.info("DONE ✅")


# -------------------------------
if __name__ == "__main__":
    main()
