import duckdb
import numpy as np
import pandas as pd
from sklearn.metrics import confusion_matrix, precision_recall_fscore_support

PARQ = "/home/anshu/Shweta/Circanet/master_parquet_with_genes/*.parquet"
TSV = "clinvar_1star_AF_EVO.tsv"   # all starred P/LP and B/LB SNVs

# 1. Benchmark set (one row per variant)
bench = pd.read_csv(TSV, sep="\t",
                    usecols=["CHROM", "POS", "REF", "ALT", "CLNSIG", "CLNREVSTAT", "label"])
bench = bench.drop_duplicates(["CHROM", "POS", "REF", "ALT"])
bench.columns = bench.columns.str.lower()

# 2. Fetch the stored BIAS call for each variant from the master parquet files
con = duckdb.connect()
con.register("bench", bench)
acmg = con.execute(f"""
    SELECT b.chrom, b.pos, b.ref, b.alt,
           any_value(NULLIF(p.acmgclassification, '')) AS acmg,
           count(DISTINCT NULLIF(p.acmgclassification, '')) AS n_acmg_values
    FROM bench b
    LEFT JOIN read_parquet('{PARQ}') p
      ON p.chrom = b.chrom AND p.pos = b.pos AND p.ref = b.ref AND p.alt = b.alt
    GROUP BY b.chrom, b.pos, b.ref, b.alt
""").df()
acmg.columns = acmg.columns.str.lower()
print("variants with conflicting BIAS values across duplicate rows:",
      (acmg.n_acmg_values > 1).sum())

d = bench.merge(acmg.drop(columns="n_acmg_values"), on=["chrom", "pos", "ref", "alt"])

# 3. Labels
stars = {"criteria_provided,_single_submitter": 1,
         "criteria_provided,_multiple_submitters,_no_conflicts": 2,
         "reviewed_by_expert_panel": 3,
         "practice_guideline": 4}
d["stars"] = d.clnrevstat.map(stars)
d["truth"] = np.where(d.label == 1, "P/LP", "B/LB")
d["pred"] = (d.acmg.str.lower()
               .map({"pathogenic": "P/LP", "likely pathogenic": "P/LP",
                     "benign": "B/LB", "likely benign": "B/LB",
                     "uncertain": "VUS"})
               .fillna("not classified"))
print(d.pred.value_counts())

# 4. Metrics in the style of the BIAS-2015 paper
def metrics(x):
    labs = ["P/LP", "B/LB"]
    p, r, f, _ = precision_recall_fscore_support(x.truth, x.pred, labels=labs, zero_division=0)
    cm = confusion_matrix(x.truth, x.pred, labels=["P/LP", "B/LB", "VUS", "not classified"])
    n_pl, n_bl = cm[0].sum(), cm[1].sum()
    spec = [1 - cm[1, 0] / n_bl, 1 - cm[0, 1] / n_pl]    # P/LP: B/LB not called P/LP; B/LB: vice versa
    return pd.DataFrame({"class": labs, "precision": p, "recall": r, "specificity": spec,
                         "F1": f, "n_P/LP": n_pl, "n_B/LB": n_bl,
                         "coverage": (x.pred.isin(labs)).mean()})

sets = {"all starred (>=1 star)": d,
        ">=2 stars": d[d.stars >= 2],
        "1 star only": d[d.stars == 1],
        "2 stars only": d[d.stars == 2],
        "3 to 4 stars (expert panel, guideline)": d[d.stars >= 3]}

res = pd.concat({k: metrics(v) for k, v in sets.items()}, names=["set"]).reset_index(level=0)
pd.set_option("display.width", 200)
print(res.round(3).to_string(index=False))
res.to_csv("bias_precision_recall_by_review_status.csv", index=False)

# 5. Confusion matrix for the main set
print(pd.crosstab(d.truth, d.pred))