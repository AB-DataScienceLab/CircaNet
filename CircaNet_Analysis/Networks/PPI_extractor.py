import requests
import pandas as pd
from io import StringIO
import time
from urllib.parse import urlencode
import re

# -------------------------------------------------
# Helper functions to extract IDs (ANNOTATION ONLY)
# -------------------------------------------------
def extract_ensp(string_id):
    """
    Extract ENSP ID if present, else None
    Example: 9606.ENSP00000354587 -> ENSP00000354587
    """
    if pd.isna(string_id):
        return None
    m = re.search(r"(ENSP\d+)", string_id)
    return m.group(1) if m else None


def extract_uniprot(string_id):
    """
    Extract UniProt ID if present
    Example: 9606.Q9Y6K9 -> Q9Y6K9
    """
    if pd.isna(string_id):
        return None
    m = re.search(r"\.(\w+)$", string_id)
    return m.group(1) if m else None


# -------------------------------------------------
# FUNCTION 1: Fetch STRING data for ONE protein
# -------------------------------------------------
def get_string_data(uniprot_id, score_threshold=0.7):
    """
    Fetch STRING interaction partners for a UniProt ID.
    - Do NOT drop any identifiers
    - Annotate ENSP and UniProt IDs
    - Split into high and low confidence
    """

    base_url = "https://string-db.org/api/tsv/interaction_partners"
    params = {
        "identifiers": uniprot_id,
        "required_score": 0  # fetch ALL interactions
    }

    url = f"{base_url}?{urlencode(params)}"

    try:
        response = requests.get(url, timeout=15)

        # HTTP error
        if response.status_code != 200:
            print(f"[ERROR] {uniprot_id}: HTTP {response.status_code}")
            return None, None, "error"

        # No interactions
        if not response.text.strip():
            return None, None, "no_interactions"

        df = pd.read_csv(StringIO(response.text), sep="\t")

        # -------------------------------------------------
        # Detect STRING ID columns safely
        # -------------------------------------------------
        if {"stringId_A", "stringId_B"}.issubset(df.columns):
            colA, colB = "stringId_A", "stringId_B"
        elif {"protein1", "protein2"}.issubset(df.columns):
            colA, colB = "protein1", "protein2"
        else:
            print(f"[WARNING] Unknown STRING format for {uniprot_id}")
            return None, None, "unknown_format"

        # -------------------------------------------------
        # Add annotation columns (NO DROPPING)
        # -------------------------------------------------
        df["input_uniprot_id"] = uniprot_id

        df["proteinA_string_id"] = df[colA]
        df["proteinB_string_id"] = df[colB]

        df["proteinA_ENSP"] = df[colA].apply(extract_ensp)
        df["proteinB_ENSP"] = df[colB].apply(extract_ensp)

        df["proteinA_uniprot"] = df[colA].apply(extract_uniprot)
        df["proteinB_uniprot"] = df[colB].apply(extract_uniprot)

        # -------------------------------------------------
        # Split by confidence
        # -------------------------------------------------
        high_conf_df = df[df["score"] >= score_threshold].copy()
        low_conf_df  = df[df["score"] < score_threshold].copy()

        return high_conf_df, low_conf_df, "has_interactions"

    except requests.RequestException as e:
        print(f"[REQUEST FAILED] {uniprot_id}: {e}")
        return None, None, "error"


# -------------------------------------------------
# FUNCTION 2: Process MANY UniProt IDs
# -------------------------------------------------
def process_uniprot_ids(uniprot_ids, score_threshold=0.7, delay=1):

    high_conf_all = []
    low_conf_all = []
    no_interaction_ids = []

    for i, uniprot_id in enumerate(uniprot_ids, start=1):
        print(f"Processing {i}/{len(uniprot_ids)}: {uniprot_id}")

        high_df, low_df, status = get_string_data(uniprot_id, score_threshold)

        if status == "no_interactions":
            no_interaction_ids.append(uniprot_id)

        if high_df is not None and not high_df.empty:
            high_conf_all.append(high_df)

        if low_df is not None and not low_df.empty:
            low_conf_all.append(low_df)

        # Respect STRING API
        time.sleep(delay)

    high_conf_final = (
        pd.concat(high_conf_all, ignore_index=True)
        if high_conf_all else pd.DataFrame()
    )

    low_conf_final = (
        pd.concat(low_conf_all, ignore_index=True)
        if low_conf_all else pd.DataFrame()
    )

    no_int_final = pd.DataFrame(
        no_interaction_ids,
        columns=["input_uniprot_id"]
    )

    return high_conf_final, low_conf_final, no_int_final


# -------------------------------------------------
# MAIN SCRIPT
# -------------------------------------------------
if __name__ == "__main__":

    # Load UniProt IDs
    try:
        uniprot_df = pd.read_csv("Prot_ID.csv")
        uniprot_ids = (
            uniprot_df.iloc[:, 0]
            .dropna()
            .astype(str)
            .tolist()
        )
    except Exception as e:
        print(f"[FATAL] Failed to read Prot_ID.csv: {e}")
        exit(1)

    print(f"Loaded {len(uniprot_ids)} UniProt IDs")

    # Run STRING retrieval
    high_conf_df, low_conf_df, no_int_df = process_uniprot_ids(uniprot_ids)

    # Save outputs
    if not high_conf_df.empty:
        high_conf_df.to_csv(
            "string_allIDs_high_confidence.csv",
            index=False
        )
        print("✔ Saved high-confidence interactions")

    if not low_conf_df.empty:
        low_conf_df.to_csv(
            "string_allIDs_low_confidence.csv",
            index=False
        )
        print("✔ Saved low-confidence interactions")

    if not no_int_df.empty:
        no_int_df.to_csv(
            "string_no_interactions.csv",
            index=False
        )
        print("✔ Saved proteins with no interactions")

    print("🎯 STRING PPI retrieval completed successfully.")
