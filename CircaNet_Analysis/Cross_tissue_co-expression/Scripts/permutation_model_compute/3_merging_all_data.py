#!/usr/bin/env python3

import os
import glob
import argparse
import pandas as pd


def parse_args():

    parser = argparse.ArgumentParser(
        description="Merge per-seed MultiCens null global centrality results."
    )

    parser.add_argument(
        "--source",
        required=True,
        help="Source tissue, e.g. hypothalamus"
    )

    parser.add_argument(
        "--target",
        required=True,
        help="Target tissue, e.g. lung"
    )

    return parser.parse_args()


def main():

    args = parse_args()

    base_dir = "/home/anshu/Harsh/Permutation_test"

    source = args.source
    target = args.target

    hormone = f"{source}_{target}"

    # --------------------------------------------------------
    # Null directory
    # Original heart_atrial_hypothalamus uses null_perm
    # Other pairs use null_perm_<source>_<target>
    # --------------------------------------------------------

    if hormone == "heart_atrial_hypothalamus":
        null_dir = os.path.join(base_dir, "null_perm")
    else:
        null_dir = os.path.join(
            base_dir,
            f"null_perm_{hormone}"
        )

    # --------------------------------------------------------
    # Output directory inside the null folder
    # --------------------------------------------------------

    output_dir = os.path.join(
        null_dir,
        "output"
    )

    per_seed_dir = os.path.join(
        output_dir,
        "per_seed"
    )

    # --------------------------------------------------------
    # Check directories
    # --------------------------------------------------------

    if not os.path.isdir(per_seed_dir):
        raise FileNotFoundError(
            f"Per-seed directory does not exist:\n{per_seed_dir}"
        )

    # --------------------------------------------------------
    # Find completed permutation files
    # --------------------------------------------------------

    per_seed_files = glob.glob(
        os.path.join(
            per_seed_dir,
            f"{hormone}_seed*.parquet"
        )
    )

    # Sort files by seed number
    per_seed_files = sorted(
        per_seed_files,
        key=lambda x: int(
            os.path.basename(x)
            .split("_seed")[-1]
            .replace(".parquet", "")
        )
    )

    n_files = len(per_seed_files)

    print("=" * 70)
    print("Merge null global centrality results")
    print("=" * 70)
    print(f"Source tissue : {source}")
    print(f"Target tissue : {target}")
    print(f"Pair          : {hormone}")
    print(f"Null directory: {null_dir}")
    print(f"Per-seed dir  : {per_seed_dir}")
    print(f"Completed perms: {n_files}")
    print("=" * 70)

    if n_files == 0:
        raise FileNotFoundError(
            f"No completed permutation files found in:\n{per_seed_dir}"
        )

    # --------------------------------------------------------
    # Read and merge
    # --------------------------------------------------------

    all_results = pd.concat(
        [
            pd.read_parquet(f)
            for f in per_seed_files
        ],
        ignore_index=True
    )

    # --------------------------------------------------------
    # Split source and target
    # --------------------------------------------------------

    source_all = all_results[
        all_results["layer"] == "source"
    ][
        ["gene_name", "GC_score", "seed"]
    ]

    target_all = all_results[
        all_results["layer"] == "target"
    ][
        ["gene_name", "GC_score", "seed"]
    ]

    # --------------------------------------------------------
    # Output filenames
    # --------------------------------------------------------

    source_file = os.path.join(
        output_dir,
        f"{hormone}_source_null_global_centrality_{n_files}perm.csv"
    )

    target_file = os.path.join(
        output_dir,
        f"{hormone}_target_null_global_centrality_{n_files}perm.csv"
    )

    # --------------------------------------------------------
    # Save
    # --------------------------------------------------------

    source_all.to_csv(
        source_file,
        index=False
    )

    target_all.to_csv(
        target_file,
        index=False
    )

    # --------------------------------------------------------
    # Summary
    # --------------------------------------------------------

    print()
    print("Merge complete.")
    print()
    print("Source shape:", source_all.shape)
    print("Target shape:", target_all.shape)
    print()
    print("Source output:")
    print(source_file)
    print()
    print("Target output:")
    print(target_file)


if __name__ == "__main__":
    main()