#!/usr/bin/env python3
"""
Empirical P-values of observed MultiCens global centrality against the donor-permutation null,
for every tissue pair.

For each pair (source, target):
  null (from 3_merging_all_pairs.py), in <null_dir>/output/:
      <src>_<tgt>_source_null_global_centrality_<N>perm.csv
      <src>_<tgt>_target_null_global_centrality_<N>perm.csv
  observed (original analysis), in --real-dir:
      <src>_source_tissue_centrality_wrt_<tgt>.csv
      <tgt>_target_tissue_centrality_results_wrt_<src>.csv
  output, in <null_dir>/output/:
      <N><src>_source_tissue_centrality_wrt_<tgt>_with_pvalues.csv
      <N><tgt>_target_tissue_centrality_wrt_<src>_with_pvalues.csv

Per gene i:  P_i = (1 + #{b : g_b(i) >= g_obs(i)}) / (1 + B)
plus null_mean, null_sd and z_score, as in p-value_test.py.

Pairs already done (both output files present) are skipped unless --force.

Usage:
    python 4_pvalues_all_pairs.py --dry-run
    python 4_pvalues_all_pairs.py
    python 4_pvalues_all_pairs.py --pairs hypothalamus:skeletal_muscle --force
"""

import os
import glob
import argparse
import pandas as pd

TISSUES = [
    "heart_atrial", "heart_ventricle", "hypothalamus", "kidney",
    "liver", "lung", "skeletal_muscle",
]
LEGACY_DIR = {"null_perm": ("heart_atrial", "hypothalamus")}

REAL_SOURCE_TMPL = "{src}_source_tissue_centrality_wrt_{tgt}.csv"
REAL_TARGET_TMPL = "{tgt}_target_tissue_centrality_results_wrt_{src}.csv"
OUT_SOURCE_TMPL = "{n}{src}_source_tissue_centrality_wrt_{tgt}_with_pvalues.csv"
OUT_TARGET_TMPL = "{n}{tgt}_target_tissue_centrality_wrt_{src}_with_pvalues.csv"


def parse_args():
    ap = argparse.ArgumentParser(description="Empirical P-values for all tissue pairs.")
    ap.add_argument("--base-dir", default="/home/anshu/Harsh/Permutation_test")
    ap.add_argument("--real-dir", default=None,
                    help="Folder with the observed centrality CSVs [default: <base-dir>/output]")
    ap.add_argument("--n-perm", type=int, default=1000)
    ap.add_argument("--pairs", nargs="*", default=None, help="Restrict to source:target pairs")
    ap.add_argument("--force", action="store_true", help="Recompute even if outputs exist")
    ap.add_argument("--dry-run", action="store_true", help="Report status only; write nothing")
    return ap.parse_args()


def split_pair(suffix):
    for t in TISSUES:
        if suffix.startswith(t + "_") and suffix[len(t) + 1:] in TISSUES:
            return t, suffix[len(t) + 1:]
    return None


def discover(base_dir, n_perm):
    """{(src, tgt): null_dir}; when a pair has two folders, prefer the one holding the merged null."""
    found = {}
    for d in sorted(glob.glob(os.path.join(base_dir, "null_perm*"))):
        if not os.path.isdir(d):
            continue
        name = os.path.basename(d)
        pair = LEGACY_DIR.get(name) or (split_pair(name[len("null_perm_"):]) if name.startswith("null_perm_") else None)
        if pair:
            found.setdefault(pair, []).append(d)
    chosen = {}
    for (src, tgt), dirs in found.items():
        merged = [d for d in dirs if os.path.exists(os.path.join(
            d, "output", f"{src}_{tgt}_source_null_global_centrality_{n_perm}perm.csv"))]
        chosen[(src, tgt)] = (merged or dirs)[0]
        if len(dirs) > 1:
            print(f"  ! {src}_{tgt} has {len(dirs)} folders; using {os.path.basename(chosen[(src, tgt)])}")
    return chosen


def load_real(path):
    df = pd.read_csv(path)
    df = df[["gene_name", "GC_score"]].rename(columns={"GC_score": "GC_score_real"})
    if df["gene_name"].duplicated().any():
        print(f"    ! {os.path.basename(path)}: {df['gene_name'].duplicated().sum()} duplicated gene names")
    return df


def compute_pvalues(real_df, null_df, expected_perm):
    """Same result as compute_pvalues() in p-value_test.py, vectorised (no groupby.apply)."""
    n_perm = null_df["seed"].nunique()
    if n_perm != expected_perm:
        print(f"    ! null has {n_perm} distinct seeds, expected {expected_perm}")

    merged = null_df.merge(real_df, on="gene_name", how="left")
    ge = (merged["GC_score"] >= merged["GC_score_real"]).groupby(merged["gene_name"]).sum()
    pvals = ((ge + 1) / (n_perm + 1)).rename("empirical_p").reset_index()

    null_stats = null_df.groupby("gene_name")["GC_score"].agg(null_mean="mean", null_sd="std").reset_index()

    result = real_df.merge(pvals, on="gene_name").merge(null_stats, on="gene_name")
    result["z_score"] = (result["GC_score_real"] - result["null_mean"]) / result["null_sd"]

    dropped = len(real_df) - len(result)
    if dropped:
        print(f"    ! {dropped} observed genes have no null values and were dropped")
    return result


def main():
    a = parse_args()
    real_dir = a.real_dir or os.path.join(a.base_dir, "output")
    N = a.n_perm
    print("=" * 76)
    print(f"Empirical P-values | base: {a.base_dir} | observed: {real_dir} | N = {N}"
          + ("  [DRY RUN]" if a.dry_run else ""))
    print("=" * 76)

    pairs = discover(a.base_dir, N)
    if a.pairs:
        wanted = {tuple(p.split(":")) for p in a.pairs}
        for w in wanted - set(pairs):
            print(f"  ! requested pair not found: {':'.join(w)}")
        pairs = {p: d for p, d in pairs.items() if p in wanted}

    summary = []
    for (src, tgt), null_dir in sorted(pairs.items()):
        tag = f"{src}_{tgt}"
        out_dir = os.path.join(null_dir, "output")
        null_src = os.path.join(out_dir, f"{tag}_source_null_global_centrality_{N}perm.csv")
        null_tgt = os.path.join(out_dir, f"{tag}_target_null_global_centrality_{N}perm.csv")
        real_src = os.path.join(real_dir, REAL_SOURCE_TMPL.format(src=src, tgt=tgt))
        real_tgt = os.path.join(real_dir, REAL_TARGET_TMPL.format(src=src, tgt=tgt))
        out_src = os.path.join(out_dir, OUT_SOURCE_TMPL.format(n=N, src=src, tgt=tgt))
        out_tgt = os.path.join(out_dir, OUT_TARGET_TMPL.format(n=N, src=src, tgt=tgt))

        if not a.force and os.path.exists(out_src) and os.path.exists(out_tgt):
            summary.append((tag, "already done", "skip"))
            continue
        missing = [os.path.basename(f) for f in (null_src, null_tgt, real_src, real_tgt) if not os.path.exists(f)]
        if missing:
            summary.append((tag, "inputs missing", "; ".join(missing)))
            continue
        if a.dry_run:
            summary.append((tag, "ready", "would compute"))
            continue

        print(f"\n  {tag}")
        try:
            notes = []
            for layer, real_f, null_f, out_f in [("source", real_src, null_src, out_src),
                                                 ("target", real_tgt, null_tgt, out_tgt)]:
                res = compute_pvalues(load_real(real_f), pd.read_csv(null_f), N)
                tmp = out_f + ".tmp"
                res.to_csv(tmp, index=False)
                os.replace(tmp, out_f)
                notes.append(f"{layer}: {len(res)} genes")
                print(f"    {layer}: {len(res)} genes -> {os.path.basename(out_f)}")
            summary.append((tag, "done", "; ".join(notes)))
        except Exception as e:
            summary.append((tag, "FAILED", str(e)))

    print("\n" + "=" * 76)
    w = max([len(t) for t, _, _ in summary] + [4])
    for tag, status, note in summary:
        print(f"{tag:<{w}}  {status:<15}  {note}")
    if not summary:
        print("No null_perm* folders found.")
    print("=" * 76)


if __name__ == "__main__":
    main()
