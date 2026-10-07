#!/usr/bin/env python
"""
Global centrality on null permutation matrices for a pair of tissues (two layers).

Example:
    python run_null_centrality.py --tissues hypothalamus lung
    python run_null_centrality.py --tissues heart_atrial hypothalamus

First tissue = layer 1 ("source"), second tissue = layer 2 ("target").
For --tissues hypothalamus lung, everything lives in:
    /home/anshu/Harsh/Permutation_test/null_perm_hypothalamus_lung/
        <tag>_null_seed<N>.parquet            (input)
        output/per_seed/<tag>_seed<N>.parquet (results)

where tag = <tissue1>_<tissue2> (e.g. hypothalamus_lung).
"""
import os
import argparse
# leave numpy import for after we can control threading via threadpoolctl instead of env vars,
# since env vars set after numpy/BLAS is loaded often don't take effect
import numpy as np
import pandas as pd
import copy
from concurrent.futures import ProcessPoolExecutor, as_completed
from threadpoolctl import threadpool_limits   # pip install threadpoolctl if not present

# ---------------- ARGUMENTS ----------------
def parse_args():
    ap = argparse.ArgumentParser(description="Null permutation global centrality")
    ap.add_argument("--tissues", required=True, nargs=2, metavar=("TISSUE1", "TISSUE2"),
                    help="Exactly two tissues in layer order, e.g. hypothalamus lung")
    ap.add_argument("--root", default="/home/anshu/Harsh/Permutation_test",
                    help="Root folder that contains the null_perm_* folders")
    ap.add_argument("--n-perm", type=int, default=1000)
    ap.add_argument("--workers", type=int, default=8,
                    help="Parallel processes. workers * threads should be about total cores")
    ap.add_argument("--threads", type=int, default=1,
                    help="BLAS threads per worker")
    ap.add_argument("--gene-list", default=None,
                    help="Optional explicit path to the gene list csv (column 'x'). "
                         "Default: output/<tag>_eQTL_gene_list_with_snap_v8_lt_latest_15k.csv")
    return ap.parse_args()

# ---------------- CENTRALITY FUNCTIONS (unchanged) ----------------
def unit_vector(vector):
    return vector / np.linalg.norm(vector)

def angle_between(v1, v2):
    v1_u = unit_vector(v1)
    v2_u = unit_vector(v2)
    return np.arccos(np.clip(np.dot(v1_u, v2_u), -1.0, 1.0))

def local_centrality(A_tilde_full, num_layers, p):
    n_ = int(np.shape(A_tilde_full)[0] / num_layers)
    N_ = int(np.shape(A_tilde_full)[0])
    A_tilde = np.zeros_like(A_tilde_full, dtype=np.float32)
    for i in range(num_layers):
        A_tilde[(i*n_):((i+1)*n_), (i*n_):((i+1)*n_)] = A_tilde_full[(i*n_):((i+1)*n_), (i*n_):((i+1)*n_)]
    ones_t = np.ones((N_,)) / N_
    l = np.copy(ones_t)
    count = 0
    current_angle = np.zeros(3,)
    while count < 70:
        l_new = (p * A_tilde.dot(l)) + (1 - p) * ones_t
        current_angle[0] = current_angle[1]
        current_angle[1] = current_angle[2]
        current_angle[2] = angle_between(l, l_new)
        if ((current_angle[0] == current_angle[1] and current_angle[1] == current_angle[2]) or current_angle[2] == 0):
            break
        l = copy.deepcopy(l_new)
        count += 1
    for i in range(num_layers):
        l[(i*n_):((i+1)*n_)] = l[(i*n_):((i+1)*n_)] / l[(i*n_):((i+1)*n_)].sum()
    return l

def global_centrality(A_tilde_full, num_layers, p):
    l = local_centrality(A_tilde_full, num_layers, p)
    N_ = int(np.shape(A_tilde_full)[0])
    n_ = int(N_ / num_layers)
    A_tilde = A_tilde_full / np.sum(A_tilde_full, axis=0)
    A = np.zeros_like(A_tilde_full, dtype=np.float32)
    for i in range(num_layers):
        A[(i*n_):((i+1)*n_), (i*n_):((i+1)*n_)] = copy.deepcopy(A_tilde[(i*n_):((i+1)*n_), (i*n_):((i+1)*n_)])
    C = A_tilde - A
    ones_t = np.ones((N_,)) / N_
    g = copy.deepcopy(ones_t)
    counter = 0
    current_angle = np.zeros(3,)
    while counter < 150:
        g_new = (p * (((A + C).dot(g)) + C.dot(l))) + ((1 - p) * ones_t)
        current_angle[0] = current_angle[1]
        current_angle[1] = current_angle[2]
        current_angle[2] = angle_between(g, g_new)
        if ((current_angle[0] == current_angle[1] and current_angle[1] == current_angle[2]) or current_angle[2] == 0):
            break
        g = copy.deepcopy(g_new)
        counter += 1
    new_g = g / g.sum()
    return l, new_g

# ---------------- WORKER STATE ----------------
# Set once per worker process via the pool initializer, so it works with both fork and spawn.
CFG = {}

def _init_worker(cfg):
    global CFG
    CFG = cfg

# ---------------- ONE PERMUTATION, WITH RESUME CHECK ----------------
def run_one_null(seed):
    tag          = CFG["tag"]
    input_dir    = CFG["input_dir"]
    per_seed_dir = CFG["per_seed_dir"]
    genes        = CFG["genes"]
    n            = len(genes)

    out_path = os.path.join(per_seed_dir, f"{tag}_seed{seed}.parquet")
    if os.path.exists(out_path):
        return f"seed {seed}: skipped (already done)"

    try:
        null_path = os.path.join(input_dir, f"{tag}_null_seed{seed}.parquet")
        null = pd.read_parquet(null_path)
        A_final = null.to_numpy(dtype=np.float32, copy=True)
        np.abs(A_final, out=A_final)

        assert A_final.shape == (2*n, 2*n), f"seed {seed}: shape mismatch {A_final.shape}"

        with threadpool_limits(limits=CFG["threads"]):
            _, global_cen_vector = global_centrality(A_final, num_layers=2, p=0.9)

        # VALIDITY CHECK: catches any seed where overflow actually corrupted the result
        gc_sum = np.nansum(global_cen_vector)
        if np.isnan(global_cen_vector).any() or np.isinf(global_cen_vector).any() or abs(gc_sum - 1.0) > 1e-3:
            return (f"seed {seed}: INVALID (sum={gc_sum}, "
                    f"has_nan={np.isnan(global_cen_vector).any()}, "
                    f"has_inf={np.isinf(global_cen_vector).any()})")

        result = pd.DataFrame({
            "gene_name": genes * 2,
            "layer": ["source"] * n + ["target"] * n,
            "GC_score": global_cen_vector,
            "seed": seed
        })
        result.to_parquet(out_path, index=False)
        del null, A_final, global_cen_vector
        return f"seed {seed}: done"
    except Exception as e:
        return f"seed {seed}: FAILED - {e}"

# ---------------- MAIN ----------------
if __name__ == "__main__":
    args = parse_args()

    tissues    = [t.strip() for t in args.tissues]
    tag        = "_".join(tissues)                     # hypothalamus_lung

    # Folder named after the tissues, e.g. .../null_perm_hypothalamus_lung
    input_dir    = os.path.join(args.root, f"null_perm_{tag}")
    output_dir   = os.path.join(input_dir, "output")
    per_seed_dir = os.path.join(output_dir, "per_seed")
    os.makedirs(per_seed_dir, exist_ok=True)

    if not os.path.isdir(input_dir):
        raise SystemExit(f"Input folder not found: {input_dir}")

    gene_list_path = args.gene_list or os.path.join(
        "output", f"{tag}_eQTL_gene_list_with_snap_v8_lt_latest_15k.csv"
    )
    genes = pd.read_csv(gene_list_path)["x"].tolist()

    print(f"tag          : {tag}")
    print(f"input dir    : {input_dir}")
    print(f"per-seed dir : {per_seed_dir}")
    print(f"gene list    : {gene_list_path} ({len(genes)} genes)")

    cfg = {
        "tag": tag,
        "input_dir": input_dir,
        "per_seed_dir": per_seed_dir,
        "genes": genes,
        "threads": args.threads,
    }

    seeds_to_run = [s for s in range(1, args.n_perm + 1)
                    if not os.path.exists(os.path.join(per_seed_dir, f"{tag}_seed{s}.parquet"))]
    print(f"{len(seeds_to_run)} of {args.n_perm} permutations remaining")

    with ProcessPoolExecutor(max_workers=args.workers,
                             initializer=_init_worker,
                             initargs=(cfg,)) as executor:
        futures = {executor.submit(run_one_null, s): s for s in seeds_to_run}
        for i, fut in enumerate(as_completed(futures), 1):
            print(f"[{i}/{len(seeds_to_run)}] {fut.result()}")

    print("All permutations finished. Run the combine step next.")