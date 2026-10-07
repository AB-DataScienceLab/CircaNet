#!/usr/bin/env Rscript

library(WGCNA)
library(Hmisc)
library(R.utils)
library(EnsDb.Hsapiens.v86)
library(arrow)
library(argparse)

# ============================================================
# ARGUMENTS
# ============================================================

parser <- ArgumentParser(
  description = "Donor-permutation null model for GTEx cross-tissue analysis"
)

parser$add_argument(
  "--source",
  required = TRUE,
  help = "Source tissue: heart_atrial, heart_ventricle, hypothalamus, kidney, liver, lung, skeletal_muscle"
)

parser$add_argument(
  "--target",
  required = TRUE,
  help = "Target tissue: heart_atrial, heart_ventricle, hypothalamus, kidney, liver, lung, skeletal_muscle"
)

parser$add_argument(
  "--n_perm",
  type = "integer",
  default = 1000,
  help = "Number of permutations [default: 1000]"
)

parser$add_argument(
  "--threads",
  type = "integer",
  default = 32,
  help = "Number of WGCNA threads [default: 32]"
)

args <- parser$parse_args()

source_tissue <- args$source
target_tissue <- args$target
n_perm <- args$n_perm
n_threads <- args$threads

# ============================================================
# CONFIG
# ============================================================

base_dir <- "/home/anshu/Harsh/Permutation_test"

cov_path <- file.path(
  base_dir,
  "GTEx_Analysis_v11_eQTL_covariates"
)

exp_path <- file.path(
  base_dir,
  "GTEx_Analysis_v11_eQTL_expression_matrices"
)

output_dir <- file.path(
  base_dir,
  "output"
)

# Pair name
pair_name <- paste0(
  source_tissue,
  "_",
  target_tissue
)

# Null permutation output directory
out_dir <- file.path(
  base_dir,
  paste0(
    "null_perm_",
    source_tissue,
    "_",
    target_tissue
  )
)

dir.create(
  out_dir,
  showWarnings = FALSE,
  recursive = TRUE
)

cat("\n============================================\n")
cat("Cross-tissue permutation analysis\n")
cat("============================================\n")
cat("Source tissue :", source_tissue, "\n")
cat("Target tissue :", target_tissue, "\n")
cat("Pair name     :", pair_name, "\n")
cat("Output folder :", out_dir, "\n")
cat("Permutations  :", n_perm, "\n")
cat("WGCNA threads :", n_threads, "\n")
cat("============================================\n\n")

allowWGCNAThreads(
  nThreads = n_threads
)

# ============================================================
# FUNCTIONS
# ============================================================

remove_extra_ensembl_ids <- function(
    tissue_df,
    ensembl_gene_list,
    HGNC_gene_mapping
) {

  for (i in length(ensembl_gene_list):1) {

    temp <- HGNC_gene_mapping[
      which(
        HGNC_gene_mapping == ensembl_gene_list[i]
      ),
      2
    ]

    if (length(temp) < 1) {
      tissue_df <- tissue_df[
        -i,
        ,
        drop = FALSE
      ]
    }
  }

  tissue_df
}


# ------------------------------------------------------------
# GTEx tissue mapping
# ------------------------------------------------------------

get_tissue <- function(tissue_name) {

  tissue_map <- c(
    heart_atrial    = "Heart_Atrial_Appendage",
    heart_ventricle = "Heart_Left_Ventricle",
    hypothalamus    = "Brain_Hypothalamus",
    kidney          = "Kidney_Cortex",
    liver           = "Liver",
    lung            = "Lung",
    skeletal_muscle = "Muscle_Skeletal"
  )

  if (!tissue_name %in% names(tissue_map)) {

    stop(
      "Tissue not found: ",
      tissue_name,
      "\nAllowed tissues: ",
      paste(
        names(tissue_map),
        collapse = ", "
      )
    )
  }

  gtex_name <- tissue_map[[tissue_name]]

  # ----------------------------------------------------------
  # Expression files
  # ----------------------------------------------------------

  expression_gz <- file.path(
    exp_path,
    paste0(
      gtex_name,
      ".v11.normalized_expression.bed.gz"
    )
  )

  expression_file <- file.path(
    exp_path,
    paste0(
      gtex_name,
      ".v11.normalized_expression.bed"
    )
  )

  if (!file.exists(expression_file)) {

    if (!file.exists(expression_gz)) {

      stop(
        "Expression file not found for ",
        tissue_name,
        ":\n",
        expression_gz
      )
    }

    cat(
      "Decompressing:",
      expression_gz,
      "\n"
    )

    gunzip(
      expression_gz,
      remove = FALSE,
      overwrite = FALSE
    )
  }

  # ----------------------------------------------------------
  # Covariates
  # ----------------------------------------------------------

  cov_file <- file.path(
    cov_path,
    paste0(
      gtex_name,
      ".v11.covariates.txt"
    )
  )

  if (!file.exists(cov_file)) {

    stop(
      "Covariate file not found for ",
      tissue_name,
      ":\n",
      cov_file
    )
  }

  covariates <- read.delim(
    cov_file,
    sep = "\t",
    header = TRUE,
    stringsAsFactors = FALSE,
    check.names = FALSE
  )

  # ----------------------------------------------------------
  # Expression data
  # ----------------------------------------------------------

  cat(
    "\nReading expression:",
    expression_file,
    "\n"
  )

  tissue_df <- as.data.frame(
    read.csv(
      expression_file,
      header = TRUE,
      sep = "\t",
      stringsAsFactors = FALSE
    )
  )

  ensembl_gene_list <- strtrim(
    tissue_df$gene_id,
    15
  )

  # ----------------------------------------------------------
  # Ensembl -> HGNC
  # ----------------------------------------------------------

  HGNC_gene_mapping <- ensembldb::select(
    EnsDb.Hsapiens.v86,
    keys = ensembl_gene_list,
    keytype = "GENEID",
    columns = c("SYMBOL")
  )

  tissue_df <- remove_extra_ensembl_ids(
    tissue_df,
    ensembl_gene_list,
    HGNC_gene_mapping
  )

  headers <- ensembldb::select(
    EnsDb.Hsapiens.v86,
    keys = strtrim(
      tissue_df$gene_id,
      15
    ),
    keytype = "GENEID",
    columns = c("SYMBOL")
  )[, 2]

  # ----------------------------------------------------------
  # Convert to gene x sample
  # ----------------------------------------------------------

  gene_tpm <- t(
    tissue_df[
      ,
      5:ncol(tissue_df)
    ]
  )

  colnames(gene_tpm) <- headers

  gene_tpm <- t(gene_tpm)

  colnames(gene_tpm) <- gsub(
    "\\.",
    "-",
    colnames(gene_tpm)
  )

  # ----------------------------------------------------------
  # Keep exactly samples in covariate file
  # ----------------------------------------------------------

  gene_tpm <- gene_tpm[
    ,
    colnames(covariates)[-1],
    drop = FALSE
  ]

  # ----------------------------------------------------------
  # Covariate adjustment
  # ----------------------------------------------------------

  old_row <- covariates$ID

  cov2 <- data.frame(
    t(
      covariates[
        ,
        -1
      ]
    )
  )

  colnames(cov2) <- old_row

  cat(
    tissue_name,
    ": residualizing",
    nrow(gene_tpm),
    "genes\n"
  )

  i <- 1

  residuals_matrix <- apply(
    gene_tpm,
    1,
    function(y) {

      if ((i %% 1000) == 0) {

        cat(
          tissue_name,
          ":",
          i,
          "\n"
        )
      }

      i <<- i + 1

      residuals(
        lm(
          y ~ .,
          data.frame(
            y = y,
            cov2
          )
        )
      )
    }
  )

  residuals_matrix
}


# ============================================================
# LOAD SOURCE AND TARGET TISSUES
# ============================================================

cat(
  "\nLoading source tissue:",
  source_tissue,
  "\n"
)

source_data <- get_tissue(
  source_tissue
)

cat(
  "\nLoading target tissue:",
  target_tissue,
  "\n"
)

target_data <- get_tissue(
  target_tissue
)

cat(
  "\nSource dimensions:",
  dim(source_data)[1],
  "x",
  dim(source_data)[2],
  "\n"
)

cat(
  "Target dimensions:",
  dim(target_data)[1],
  "x",
  dim(target_data)[2],
  "\n"
)


# ============================================================
# COMMON DONORS
# ============================================================

rows <- intersect(
  rownames(source_data),
  rownames(target_data)
)

cat(
  "\nCommon donors:",
  length(rows),
  "\n"
)

if (length(rows) < 10) {

  stop(
    "Very few common donors found: ",
    length(rows)
  )
}

source <- source_data[
  rows,
  ,
  drop = FALSE
]

target <- target_data[
  rows,
  ,
  drop = FALSE
]


# ============================================================
# COMMON GENES
# ============================================================

cols <- intersect(
  colnames(source),
  colnames(target)
)

source <- source[
  ,
  cols,
  drop = FALSE
]

target <- target[
  ,
  cols,
  drop = FALSE
]

cat(
  "Common genes before gene-list filtering:",
  length(cols),
  "\n"
)


# ============================================================
# EXACT GENE LIST FROM ORIGINAL REAL ANALYSIS
# ============================================================

gene_list_file <- file.path(
  output_dir,
  paste0(
    pair_name,
    "_eQTL_gene_list_with_snap_v8_lt_latest_15k.csv"
  )
)

cat(
  "\nGene-list file:\n",
  gene_list_file,
  "\n"
)

if (!file.exists(gene_list_file)) {

  stop(
    "Exact real-analysis gene list not found:\n",
    gene_list_file
  )
}

real_gene_list <- read.csv(
  gene_list_file,
  stringsAsFactors = FALSE,
  check.names = FALSE
)[[1]]

cat(
  "Exact real-analysis gene count:",
  length(real_gene_list),
  "\n"
)


# ============================================================
# CHECK GENE AVAILABILITY
# ============================================================

missing_genes <- setdiff(
  real_gene_list,
  intersect(
    colnames(source),
    colnames(target)
  )
)

if (length(missing_genes) > 0) {

  stop(
    "Genes from the real analysis are missing ",
    "from the current matrices: ",
    length(missing_genes)
  )
}


# ============================================================
# EXACT GENE ORDER
# ============================================================

source <- source[
  ,
  real_gene_list,
  drop = FALSE
]

target <- target[
  ,
  real_gene_list,
  drop = FALSE
]

stopifnot(
  identical(
    colnames(source),
    colnames(target)
  )
)

cat(
  "Final gene universe:",
  ncol(source),
  "\n"
)

cat(
  "Final donor count:",
  nrow(source),
  "\n"
)


# ============================================================
# RANK EACH TISSUE ONCE
# ============================================================

cat(
  "\nRanking expression values once...\n"
)

source_ranked <- apply(
  as.matrix(source),
  2,
  rank
)

target_ranked <- apply(
  as.matrix(target),
  2,
  rank
)

storage.mode(
  source_ranked
) <- "double"

storage.mode(
  target_ranked
) <- "double"


# ============================================================
# FIXED WITHIN-TISSUE CORRELATION BLOCKS
# ============================================================

cat(
  "\nCalculating",
  source_tissue,
  "-",
  source_tissue,
  "block...\n"
)

SS <- WGCNA::cor(
  source_ranked,
  source_ranked,
  method = "pearson",
  nThreads = n_threads,
  use = "pairwise.complete.obs"
)

cat(
  "Calculating",
  target_tissue,
  "-",
  target_tissue,
  "block...\n"
)

TT <- WGCNA::cor(
  target_ranked,
  target_ranked,
  method = "pearson",
  nThreads = n_threads,
  use = "pairwise.complete.obs"
)

SS[is.na(SS)] <- 0
TT[is.na(TT)] <- 0

# Absolute values, matching MultiCens input
SS <- abs(SS)
TT <- abs(TT)

storage.mode(SS) <- "double"
storage.mode(TT) <- "double"


# ============================================================
# NULL PERMUTATION FUNCTION
# ============================================================

run_and_save_null <- function(seed) {

  out_path <- file.path(
    out_dir,
    paste0(
      pair_name,
      "_null_seed",
      seed,
      ".parquet"
    )
  )

  # ----------------------------------------------------------
  # Skip existing result
  # ----------------------------------------------------------

  if (file.exists(out_path)) {

    cat(
      "Seed",
      seed,
      "already exists. Skipping.\n"
    )

    return(FALSE)
  }

  cat(
    "\nRunning seed:",
    seed,
    "\n"
  )

  set.seed(seed)

  # ----------------------------------------------------------
  # Randomize TARGET donor order only
  # ----------------------------------------------------------

  perm_idx <- sample(
    seq_len(
      nrow(target_ranked)
    )
  )

  target_ranked_null <- target_ranked[
    perm_idx,
    ,
    drop = FALSE
  ]

  # ----------------------------------------------------------
  # ONLY CROSS-TISSUE CORRELATION CHANGES
  # ----------------------------------------------------------

  ST_null <- WGCNA::cor(
    source_ranked,
    target_ranked_null,
    method = "pearson",
    nThreads = n_threads,
    use = "pairwise.complete.obs"
  )

  ST_null[is.na(ST_null)] <- 0

  ST_null <- abs(ST_null)

  storage.mode(ST_null) <- "double"

  # ----------------------------------------------------------
  # Build complete supra-adjacency
  # ----------------------------------------------------------

  null_matrix <- rbind(
    cbind(
      SS,
      ST_null
    ),
    cbind(
      t(ST_null),
      TT
    )
  )

  storage.mode(
    null_matrix
  ) <- "double"

  # ----------------------------------------------------------
  # Save as Float32 Parquet
  # ----------------------------------------------------------

  df_out <- as.data.frame(
    null_matrix
  )

  colnames(df_out) <- paste0(
    "V",
    seq_len(
      ncol(df_out)
    )
  )

  df_out[] <- lapply(
    df_out,
    function(x) {

      arrow::Array$create(
        x,
        type = float32()
      )
    }
  )

  arrow::write_parquet(
    df_out,
    out_path,
    compression = "zstd"
  )

  cat(
    "Saved:",
    out_path,
    "\n"
  )

  rm(
    perm_idx,
    target_ranked_null,
    ST_null,
    null_matrix,
    df_out
  )

  gc()

  TRUE
}


# ============================================================
# RUN PERMUTATIONS
# ============================================================

cat(
  "\n============================================\n"
)

cat(
  "Starting permutation analysis\n"
)

cat(
  "Source:",
  source_tissue,
  "\n"
)

cat(
  "Target:",
  target_tissue,
  "\n"
)

cat(
  "Target permutations:",
  n_perm,
  "\n"
)

cat(
  "Existing files will be skipped\n"
)

cat(
  "Output directory:",
  out_dir,
  "\n"
)

cat(
  "============================================\n\n"
)


ptm <- proc.time()

completed <- 0
skipped <- 0
failed <- 0

for (s in seq_len(n_perm)) {

  result <- tryCatch(

    run_and_save_null(s),

    error = function(e) {

      cat(
        "FAILED seed",
        s,
        ":",
        conditionMessage(e),
        "\n"
      )

      return(NA)
    }
  )

  if (isTRUE(result)) {

    completed <- completed + 1

  } else if (identical(result, FALSE)) {

    skipped <- skipped + 1

  } else {

    failed <- failed + 1
  }

  cat(
    "\nProgress:",
    s,
    "/",
    n_perm,
    "| completed:",
    completed,
    "| skipped:",
    skipped,
    "| failed:",
    failed,
    "\n"
  )
}


# ============================================================
# FINISH
# ============================================================

cat(
  "\n============================================\n"
)

cat(
  "Permutation analysis finished\n"
)

cat(
  "Source:",
  source_tissue,
  "\n"
)

cat(
  "Target:",
  target_tissue,
  "\n"
)

cat(
  "Completed:",
  completed,
  "\n"
)

cat(
  "Skipped:",
  skipped,
  "\n"
)

cat(
  "Failed:",
  failed,
  "\n"
)

cat(
  "Output directory:",
  out_dir,
  "\n"
)

cat(
  "============================================\n"
)

print(
  proc.time() - ptm
)