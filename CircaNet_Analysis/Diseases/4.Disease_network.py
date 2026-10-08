"""Step 1: disease-disease network from gene-disease associations.
Input columns: diseaseId, targetId. Hypergeometric overlap test + Jaccard,
BH-FDR over all possible disease pairs, p-values kept in log space."""

import argparse, json, os, pickle
from collections import defaultdict
from itertools import combinations
from math import comb, log

import networkx as nx
import numpy as np
import pandas as pd
from scipy.special import logsumexp
from scipy.stats import hypergeom

LN10 = np.log(10.0)


def load_gda(path, disease_col="diseaseId", gene_col="targetId"):
    if not path or not os.path.exists(path):
        raise FileNotFoundError(f"Input file not found: {path}")
    sep = "\t" if path.endswith(".tsv") else ","
    df = pd.read_csv(path, sep=sep, usecols=[disease_col, gene_col]).dropna()
    df = df.rename(columns={disease_col: "diseaseId", gene_col: "targetId"})
    df["diseaseId"] = df["diseaseId"].astype(str).str.strip()
    df["targetId"] = df["targetId"].astype(str).str.strip()
    df = df.drop_duplicates()
    print(f"[LOAD] {len(df)} links | {df.diseaseId.nunique()} diseases | {df.targetId.nunique()} genes")
    return df


def build_gene_sets(df, min_genes, background):
    gs = df.groupby("diseaseId")["targetId"].apply(frozenset).to_dict()
    if background is not None:  # test is invalid if genes fall outside the universe
        gs = {d: g & background for d, g in gs.items()}
    gs = {d: g for d, g in gs.items() if len(g) >= min_genes}
    print(f"[GENE SETS] {len(gs)} diseases with >= {min_genes} genes")
    return gs


def candidate_pairs(gene_sets, min_shared, max_degree):
    g2d = defaultdict(list)
    for d, genes in gene_sets.items():
        for g in genes:
            g2d[g].append(d)
    counts, skipped = defaultdict(int), 0
    for g, ds in g2d.items():
        if max_degree and len(ds) > max_degree:
            skipped += 1
            continue
        for pair in combinations(sorted(ds), 2):
            counts[pair] += 1
    if skipped:
        print(f"[CANDIDATES][WARN] {skipped} hub gene(s) skipped; pairs sharing only hub genes are not scored")
    pairs = [p for p, k in counts.items() if k >= min_shared]
    print(f"[CANDIDATES] {len(pairs):,} pairs share >= {min_shared} gene(s)")
    return pairs, skipped


def log_hypergeom_sf(k, M, na, nb):
    """ln P(X >= k); recomputed via logpmf where sf() underflows."""
    p = hypergeom.sf(k - 1, M, na, nb)
    with np.errstate(divide="ignore"):
        logp = np.log(p)
    bad = ~np.isfinite(logp) | (p < 1e-250)
    for i in np.where(bad)[0]:
        j = np.arange(k[i], min(na[i], nb[i]) + 1)
        logp[i] = logsumexp(hypergeom.logpmf(j, M, na[i], nb[i]))
    print(f"[STATS] {int(bad.sum()):,} pairs recomputed in log space (p < 1e-250)")
    return np.minimum(logp, 0.0)


def bh_log(logp, m_total):
    """Benjamini-Hochberg in log space; m_total = full test family size."""
    t = len(logp)
    order = np.argsort(logp, kind="mergesort")
    lq = logp[order] + log(m_total) - np.log(np.arange(1, t + 1))
    lq = np.minimum.accumulate(lq[::-1])[::-1]
    out = np.empty(t)
    out[order] = np.minimum(lq, 0.0)
    return out


def sci(log10_vals):
    """'1.73x10^-618' (not 1.73e-618, which pandas/Excel read back as 0)."""
    out = []
    for v in log10_vals:
        e = int(np.floor(v))
        m = 10.0 ** (v - e)
        if round(m, 2) >= 10.0:
            m, e = m / 10.0, e + 1
        out.append(f"{m:.2f}x10^{e}")
    return out


def build_edges(gene_sets, universe, a):
    pairs, skipped = candidate_pairs(gene_sets, a.min_shared, a.max_gene_degree or None)
    if not pairs:
        raise ValueError("No overlapping disease pairs found.")
    M = len(universe)
    rows = []
    for x, y in pairs:
        sa, sb = gene_sets[x], gene_sets[y]
        shared = sa & sb
        k = len(shared)
        if k >= a.min_shared:
            rows.append((x, y, k, k / (len(sa) + len(sb) - k), ";".join(sorted(shared)), len(sa), len(sb)))
    df = pd.DataFrame(rows, columns=["disease_A", "disease_B", "n_shared", "jaccard",
                                     "shared_genes", "size_A", "size_B"])
    if (df[["size_A", "size_B"]].to_numpy() > M).any():
        raise ValueError("A gene set is larger than the background universe.")

    logp = log_hypergeom_sf(df.n_shared.to_numpy(), M, df.size_A.to_numpy(), df.size_B.to_numpy())
    D = len(gene_sets)
    m_total = comb(D, 2) if a.bh_family == "all_pairs" else len(df)
    print(f"[FDR] BH family m = {m_total:,} ({a.bh_family}); tested pairs = {len(df):,}")
    logq = bh_log(logp, m_total)

    df["neg_log10_p"] = -logp / LN10
    df["neg_log10_padj"] = -logq / LN10
    df["pval_raw_sci"] = sci(-df.neg_log10_p)
    df["pval_adj_sci"] = sci(-df.neg_log10_padj)
    term = df.neg_log10_padj if a.score_cap is None else df.neg_log10_padj.clip(upper=a.score_cap)
    df["composite_score"] = df.jaccard * term
    df = df.drop(columns=["size_A", "size_B"])

    sig = df[logq < log(a.fdr_alpha)]
    print(f"[FILTER] {len(sig):,} / {len(df):,} edges at FDR < {a.fdr_alpha}")
    if a.min_jaccard is not None:  # applied after testing; does not change the BH family
        sig = sig[sig.jaccard >= a.min_jaccard]
    if a.max_jaccard is not None:
        sig = sig[sig.jaccard < a.max_jaccard]
    stats = {"bh_family": a.bh_family, "bh_family_size_m": int(m_total),
             "n_tested_pairs": int(len(df)), "n_significant_fdr": int((logq < log(a.fdr_alpha)).sum()),
             "n_edges_final": int(len(sig)), "n_hub_genes_skipped": int(skipped)}
    return sig.sort_values("composite_score", ascending=False).reset_index(drop=True), stats


def build_graph(edges, gene_sets, out_dir):
    G = nx.Graph()
    for d, g in gene_sets.items():
        G.add_node(d, n_genes=len(g))
    for r in edges.itertuples(index=False):
        s = float(r.composite_score)
        G.add_edge(r.disease_A, r.disease_B, weight=s, distance=1.0 / (1.0 + s),
                   jaccard=float(r.jaccard), neg_log10_padj=float(r.neg_log10_padj),
                   n_shared=int(r.n_shared), shared_genes=r.shared_genes)
    iso = list(nx.isolates(G))
    if iso:
        pd.DataFrame({"diseaseId": iso}).to_csv(f"{out_dir}/excluded_isolated_diseases.tsv", sep="\t", index=False)
    G.remove_nodes_from(iso)
    print(f"[GRAPH] {G.number_of_nodes()} nodes | {G.number_of_edges()} edges | {len(iso)} isolates removed")
    return G


def centrality(G):
    bc = nx.betweenness_centrality(G, weight="distance")
    cc = nx.closeness_centrality(G, distance="distance")
    pr = nx.pagerank(G, weight="weight")
    return pd.DataFrame({
        "disease": list(G.nodes()),
        "degree": [G.degree(n) for n in G],
        "weighted_degree": [G.degree(n, weight="weight") for n in G],
        "betweenness": [bc[n] for n in G], "closeness": [cc[n] for n in G],
        "pagerank": [pr[n] for n in G]}).set_index("disease").round(4)


def main():
    p = argparse.ArgumentParser(description="Step 1: disease network construction")
    p.add_argument("--input", default="data/circadian_gda.tsv")
    p.add_argument("--disease-col", default="diseaseId", help="disease ID column name in input")
    p.add_argument("--gene-col", default="targetId", help="gene ID column name in input")
    p.add_argument("--background", default=None, help="one-column reference gene panel")
    p.add_argument("--output-dir", default="output")
    p.add_argument("--fdr-alpha", type=float, default=0.05)
    p.add_argument("--min-genes", type=int, default=2)
    p.add_argument("--min-shared", type=int, default=1)
    p.add_argument("--max-gene-degree", type=int, default=2000, help="0 disables hub guard")
    p.add_argument("--bh-family", choices=["all_pairs", "tested"], default="all_pairs")
    p.add_argument("--min-jaccard", type=float, default=None)
    p.add_argument("--max-jaccard", type=float, default=None)
    p.add_argument("--score-cap", type=float, default=None)
    a = p.parse_args()
    os.makedirs(a.output_dir, exist_ok=True)

    df = load_gda(a.input, a.disease_col, a.gene_col)
    if a.background and os.path.exists(a.background):
        universe = set(pd.read_csv(a.background, header=None)[0].astype(str).str.strip())
        print(f"[BACKGROUND] reference panel: {len(universe)} genes")
        gene_sets = build_gene_sets(df, a.min_genes, universe)
    else:
        universe = set(df.targetId)
        print("[BACKGROUND][WARN] no --background given; universe = genes observed in this dataset")
        gene_sets = build_gene_sets(df, a.min_genes, None)

    edges, stats = build_edges(gene_sets, universe, a)
    G = build_graph(edges, gene_sets, a.output_dir)
    cent = centrality(G)

    edges.to_csv(f"{a.output_dir}/disease_edges.tsv", sep="\t", index=False)
    cent.to_csv(f"{a.output_dir}/centrality_scores.tsv", sep="\t")
    pd.DataFrame({"diseaseId": list(gene_sets), "n_genes": [len(v) for v in gene_sets.values()]}) \
        .to_csv(f"{a.output_dir}/disease_gene_counts.tsv", sep="\t", index=False)
    with open(f"{a.output_dir}/disease_graph.pkl", "wb") as f:
        pickle.dump(G, f)
    with open(f"{a.output_dir}/gene_sets.pkl", "wb") as f:
        pickle.dump(gene_sets, f)
    meta = {**vars(a), "background_universe_size": len(universe), "n_diseases": len(gene_sets),
            "n_nodes_final": G.number_of_nodes(), "n_edges_final_graph": G.number_of_edges(), **stats}
    with open(f"{a.output_dir}/run_metadata.json", "w") as f:
        json.dump(meta, f, indent=2)
    print(f"[DONE] outputs in '{a.output_dir}/'")


if __name__ == "__main__":
    main()
