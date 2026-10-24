import duckdb
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt

con = duckdb.connect("circanet_bench.duckdb", read_only=True)

def q(sql, params=()):
    d = con.execute(sql, list(params)).df()
    d.columns = d.columns.str.lower()
    return d

def percentile(table, col, x, key, extra_sql="", extra_params=()):
    """Mid-rank percentile of x in a background (higher = more pathogenic-looking).
    The query variant itself is excluded. key = (chrom, pos, ref, alt)."""
    sql = f"""
        SELECT count(*),
               count(*) FILTER (WHERE {col} < ?),
               count(*) FILTER (WHERE {col} = ?)
        FROM {table}
        WHERE {col} IS NOT NULL
          AND NOT (chrom = ? AND pos = ? AND ref = ? AND alt = ?)
          {extra_sql}"""
    n, lo, eq = con.execute(sql, [x, x, *key, *extra_params]).fetchone()
    return (100 * (lo + eq / 2) / n if n else np.nan), n

qv = q("SELECT * FROM qv")      # columns: label, chrom, pos, ref, alt
rows = []

# AlphaMissense
for v in qv.itertuples():
    key = (v.chrom, int(v.pos), v.ref, v.alt)
    am = q("""SELECT gene, am_pathogenicity, am_class, am_protein_variant
              FROM am_unique WHERE chrom=? AND pos=? AND ref=? AND alt=?""", key)
    for a in am.itertuples():
        for bg, sql, prm in [("same gene", "AND gene = ?", (a.gene,)),
                             ("all genes", "", ())]:
            p, n = percentile("am_unique", "am_pathogenicity", a.am_pathogenicity, key, sql, prm)
            rows.append(dict(variant=v.label, tool="AlphaMissense", scope="all possible missense",
                             background=bg, score=a.am_pathogenicity, call=a.am_class,
                             note=a.am_protein_variant, pct=p, n=n))

# EVEE (consequence matched, plus one unmatched overall rank)
for v in qv.itertuples():
    key = (v.chrom, int(v.pos), v.ref, v.alt)
    ev = q("""SELECT gene, evee_score, evee_class, cons_class
              FROM evee_unique WHERE chrom=? AND pos=? AND ref=? AND alt=?""", key)
    for e in ev.itertuples():
        for scope, s in [("SNV only", "AND var_class = 'SNV'"), ("all variant types", "")]:
            bgs = {
                "same gene, same consequence": (f"{s} AND gene = ? AND cons_class = ?", (e.gene, e.cons_class)),
                "all genes, same consequence": (f"{s} AND cons_class = ?", (e.cons_class,)),
                "all genes, all consequences": (s, ()),
                "B/LB only, same consequence": (f"{s} AND label = 'BLB' AND cons_class = ?", (e.cons_class,)),
                "P/LP only, same consequence": (f"{s} AND label = 'PLP' AND cons_class = ?", (e.cons_class,)),
            }
            for bg, (sql, prm) in bgs.items():
                p, n = percentile("evee_unique", "evee_score", e.evee_score, key, sql, prm)
                rows.append(dict(variant=v.label, tool="EVEE", scope=scope, background=bg,
                                 score=e.evee_score, call=e.evee_class,
                                 note=e.cons_class, pct=p, n=n))

res = pd.DataFrame(rows)
res.to_csv("four_variants_ranks.csv", index=False)

# Compact table: "percentile (n=background size)"
res["cell"] = res.pct.round(1).astype(str) + " (n=" + res.n.map("{:,}".format) + ")"
summary = res.pivot_table(index=["variant", "tool", "scope", "score", "call"],
                          columns="background", values="cell", aggfunc="first")
pd.set_option("display.width", 250); pd.set_option("display.max_columns", None)
print(summary)
summary.to_csv("four_variants_ranks_summary.csv")

# Figure: four variants marked on background score distributions
am_bg = q("SELECT am_pathogenicity FROM am_unique USING SAMPLE 1000000 ROWS").am_pathogenicity
ev_bg = q("SELECT evee_score, label, cons_class FROM evee_unique WHERE var_class = 'SNV'")

def mark(ax, sub):
    ymax = ax.get_ylim()[1]
    for r in sub.itertuples():
        ax.axvline(r.score, color="black", ls="--", lw=1.3)
        ax.text(r.score, ymax * 0.97, r.variant, rotation=90, va="top", ha="right", fontsize=9)

fig, ax = plt.subplots(1, 3, figsize=(16, 4.5))

ax[0].hist(am_bg, bins=60, density=True, color="lightgray")
ax[0].set(title="AlphaMissense, all possible missense", xlabel="am_pathogenicity", ylabel="Density")
mark(ax[0], res[(res.tool == "AlphaMissense") & (res.background == "all genes")])

for i, cons in enumerate(["missense", "splice"], start=1):
    sub = ev_bg[ev_bg.cons_class == cons]
    ax[i].hist(sub[sub.label == "BLB"].evee_score, bins=50, density=True, alpha=0.6,
               color="tab:blue", label="B/LB")
    ax[i].hist(sub[sub.label == "PLP"].evee_score, bins=50, density=True, alpha=0.6,
               color="tab:red", label="P/LP")
    ax[i].set(title=f"EVEE, {cons} SNVs", xlabel="EVEE score")
    mark(ax[i], res[(res.tool == "EVEE") & (res.scope == "SNV only") & (res.note == cons)
                    & (res.background == "all genes, same consequence")])
    ax[i].legend(loc="upper center")
plt.tight_layout(); plt.savefig("four_variants_background.png", dpi=300)