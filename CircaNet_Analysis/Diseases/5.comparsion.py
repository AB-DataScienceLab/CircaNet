import json
import numpy as np, pandas as pd
from scipy.stats import spearmanr

A, B = "output_fixed_full", "output_fixed_litexcl"

# 0. were the two runs comparable?
for d in (A, B):
    m = json.load(open(f"{d}/run_metadata.json"))
    keys = ["background_universe_size", "bh_family", "fdr_alpha", "min_genes", "min_shared",
            "n_diseases", "n_tested_pairs", "n_significant_fdr"]
    print(d, {k: m.get(k) for k in keys})

# 1. edges (unordered pairs)
cols = ["disease_A", "disease_B", "composite_score"]
def load(d):
    e = pd.read_csv(f"{d}/disease_edges.tsv", sep="\t", usecols=cols)
    swap = e.disease_A > e.disease_B
    e["u"] = np.where(swap, e.disease_B, e.disease_A)
    e["v"] = np.where(swap, e.disease_A, e.disease_B)
    return e
ea, eb = load(A), load(B)
m = ea.merge(eb, on=["u", "v"], suffixes=("_full", "_lit"))
print(f"\nEDGES  full {len(ea):,} | lit-excluded {len(eb):,} | in both {len(m):,}"
      f" | only full {len(ea)-len(m):,} | only lit {len(eb)-len(m):,}")
print(f"edges retained (in both / full): {len(m)/len(ea):.1%}   [alt: lit/full = {len(eb)/len(ea):.1%}]")
print(f"edge-weight spearman (edges in both): {spearmanr(m.composite_score_full, m.composite_score_lit)[0]:.3f}")

# 2. nodes + centrality
ca = pd.read_csv(f"{A}/centrality_scores.tsv", sep="\t", index_col=0)
cb = pd.read_csv(f"{B}/centrality_scores.tsv", sep="\t", index_col=0)
com = ca.index.intersection(cb.index)
print(f"\nNODES  full {len(ca):,} | lit-excluded {len(cb):,} | in both {len(com):,}"
      f" | only lit {len(cb.index.difference(ca.index))}")
print(f"nodes retained (in both / full): {len(com)/len(ca):.1%}   [alt: lit/full = {len(cb)/len(ca):.1%}]")
for k in ["degree", "weighted_degree", "closeness", "betweenness", "pagerank"]:
    rho = spearmanr(ca.loc[com, k], cb.loc[com, k])[0]
    t20 = len(set(ca[k].nlargest(20).index) & set(cb[k].nlargest(20).index))
    print(f"{k:16s} spearman {rho:.3f} | top-20 overlap {t20}/20")
