library(R.utils)
library(EnsDb.Hsapiens.v86)

cov_path = ("E:/circadian_rhythm/MultiCens/GTEx_Analysis_v8_eQTL_covariates/")
exp_path = ("E:/circadian_rhythm/Multicens/GTEx_Analysis_v8_eQTL_expression_matrices/")


# First step helps in cleaning the data and covariate adjustments

remove_extra_ensembl_ids <- function(tissue_df, ensembl_gene_list, HGNC_gene_mapping)
{
  for (i in length(ensembl_gene_list):1)
  {
    temp = HGNC_gene_mapping[which(HGNC_gene_mapping == ensembl_gene_list[i]), 2]
    if (length(temp)<1)
    {
      tissue_df <- tissue_df[-c(i),]
      print(i)
    }
  }
  tissue_df
}


add_column <- function(tissue_incomplete, column_name)
{
  num_genes = dim(tissue_incomplete)[1]
  random_vector = runif(n = num_genes, min = 0.0001, max = 0.00011)
  df = as.data.frame(tissue_incomplete)
  names(random_vector) <- column_name
  tissue_complete = cbind(df, random_vector)
  names(tissue_complete)[names(tissue_complete) == "random_vector"] <- column_name
  data.matrix(tissue_complete)
}



get_tissue <- function(tissue_name)
{
  tissue_map <- c(
    pituitary    = "Pituitary",
    adrenal      = "Adrenal_Gland",
    adipose      = "Adipose_Visceral_Omentum",
    muscle       = "Muscle_Skeletal",
    heart        = "Heart_Atrial_Appendage",
    kidney       = "Kidney_Cortex",
    liver        = "Liver",
    thyroid      = "Thyroid",
    hypothalamus = "Brain_Hypothalamus",
    pancreas = "Pancreas"
  )
  
  if (!tissue_name %in% names(tissue_map)) {
    stop("Tissue not found")
  }
  
  gtex_name <- tissue_map[[tissue_name]]
  
  file              <- paste0(exp_path, gtex_name, ".v8.normalized_expression.bed.gz")
  decompressed_file <- paste0(exp_path, gtex_name, ".v8.normalized_expression.bed")
  data              <- try(gunzip(file, remove = FALSE), silent = TRUE)
  covariates        <- read.delim(paste0(cov_path, gtex_name, ".v8.covariates.txt"),
                                  sep = '\t', header = T, stringsAsFactors = F, check.names = F)
  
  #----------- Processing Tissue data --------#
  
  #----------- Adjust for covariates ---------#
  
  tissue_df <- as.data.frame(read.csv(decompressed_file, header = T, sep = "\t", stringsAsFactors = FALSE))
  ensembl_gene_list <- strtrim(tissue_df$gene_id, 15)
  HGNC_gene_mapping <- ensembldb::select(EnsDb.Hsapiens.v86, keys = ensembl_gene_list, keytype = "GENEID", columns = c("SYMBOL"))
  
  tissue_df <- remove_extra_ensembl_ids(tissue_df, ensembl_gene_list, HGNC_gene_mapping)
  headers   <- ensembldb::select(EnsDb.Hsapiens.v86, keys = strtrim(tissue_df$gene_id, 15), keytype = "GENEID", columns = c("SYMBOL"))[, 2]
  
  gene_tpm           <- t(tissue_df[, 5:dim(tissue_df)[2]])
  colnames(gene_tpm) <- headers
  gene_tpm           <- t(gene_tpm)
  colnames(gene_tpm) <- gsub("\\.", "-", colnames(gene_tpm))
  gene_tpm           <- gene_tpm[, colnames(covariates)[-1]]
  
  old_row <- covariates$ID
  cov2    <- data.frame(t(covariates[, -1]))
  colnames(cov2) <- old_row
  
  i <- 1
  residuals <- apply(gene_tpm, 1, function(y) {
    if ((i %% 1000) == 0) cat(i, '\n')
    i <<- i + 1
    r <- residuals(lm(y ~ ., data.frame(y = y, cov2)))
    return(r)
  })
  
  residuals
}





# Loading both liver and hypothalamus data to find common samples and genes for the two tissues.
pancreas <- get_tissue("pancreas")
muscle <- get_tissue("muscle")

# dimensions
cat("pancreas dimensions (samples x genes):", dim(pancreas), "\n")
cat("Muscle dimensions:", dim(muscle), "\n")


# Find common individuals (rows) and genes (columns)
pancreas_muscle_common_samples <- Reduce(
  intersect, 
  list(rownames(pancreas), rownames(muscle))
)

pancreas_muscle_common_cols <- Reduce(
  intersect,
  list(colnames(pancreas), colnames(muscle))
)

# alternate way to show this 
insulin = Reduce(intersect, list(rownames(pancreas),rownames(muscle)))
insulin_cols = Reduce(intersect, list(colnames(pancreas),colnames(muscle)))




# Module to adjust p-values, calculates spearman correlation
# In a supraadjacency matrix, p-values are adjusted for each block independently

adjust_p <- function(n, hormone, prob_matrix)
{
  L <- 2
  N <- L * n
  prob_matrix_adj <- matrix(0,N,N)
  current_prob = matrix(0,n,n)
  print("part 1 done")
  if(TRUE)
  {
    for (i in 1:L)
    {
      for (j in 1:L)
      {
        print("dimension")
        print(dim(current_prob))
        print(((i-1)*n)+1)
        print(((i)*n))
        print(((j-1)*n)+1)
        print(((j)*n))
        current_prob = prob_matrix[(((i-1)*n)+1):(((i)*n)),(((j-1)*n)+1):(((j)*n))]
        prob_matrix_adj[(((i-1)*n)+1):(((i)*n)),(((j-1)*n)+1):(((j)*n))] <- matrix(p.adjust(as.vector(current_prob), method='fdr'),ncol=n)
        print("i")
        print(i)
      }
    }
  }
  print("writing probability matrix")
  #write.table(prob_matrix_adj,file=paste("./data/", hormone, "_eQTL_spearman_prob_matrix_adj_removed_CF_lt_R_latest_15k.csv", sep=""), append = FALSE, sep = ",",row.names = TRUE, col.names = TRUE, qmethod = c("escape", "double"))
  prob_matrix_adj
}


count_cf <- function(tissue)
{
  library(sva)
  library("WGCNA", quietly = T)
  drops <- c("tiss_dat...2.")
  tissue = tissue[, !(names(tissue) %in% drops)]
  tissue_variances <- apply(X=tissue, MARGIN=2, FUN=var)
  tissue_sorted <- sort(tissue_variances, decreasing=TRUE, index.return=TRUE)$ix[1:10000]
  tissue = tissue[,tissue_sorted]
  mod=matrix(1,nrow=dim(tissue)[1],ncol=1)
  colnames(mod)="Intercept"
  nsv=num.sv(t(tissue),mod, method = "be") ## num.sv requires data matrix with features(genes) in the rows and samples in the column
  print(paste("Number of PCs estimated to be removed:", nsv))
}





# Updated write_corr function ----------------------------------------------

write_corr <- function(hormone)
{
  library(WGCNA)
  library(Hmisc)
  
  hormone_map <- list(
    acth               = c("pituitary",   "adrenal"),
    adiponectin        = c("adipose_vis",  "muscle"),
    adrenaline         = c("adrenal",      "heart"),
    adrenocorticotropic= c("pituitary",   "adrenal"),
    aldosterone        = c("adrenal",      "kidney"),
    angiotensin        = c("liver",        "pituitary"),
    anp                = c("heart",        "kidney"),
    calcitonin         = c("thyroid",      "kidney"),
    corticotropin      = c("hypothalamus", "pituitary"),
    cortisol           = c("adrenal",      "liver"),
    crh                = c("hypothalamus", "pituitary"),
    estradiol          = c("ovary",        "pituitary"),
    follitropin        = c("pituitary",    "ovary"),
    gastrin            = c("intestine",    "stomach"),
    ghr                = c("hypothalamus", "pituitary"),
    ghrelin            = c("stomach",      "hypothalamus"),
    glp1               = c("intestine",    "pancreas"),
    glucagon           = c("pancreas",     "liver"),
    hcg                = c("pituitary",    "ovary"),
    insulin            = c("pancreas",     "muscle"),
    leptin             = c("adipose_vis",  "hypothalamus"),
    luteinizing        = c("pituitary",    "ovary"),
    norepinephrine     = c("adrenal",      "intestine"),
    oxytocin           = c("hypothalamus", "uterus"),
    progesterone       = c("ovary",        "uterus"),
    prolactin          = c("pituitary",    "breast"),
    relaxin            = c("ovary",        "uterus"),
    somatostatin       = c("hypothalamus", "pituitary"),
    somatotrophin      = c("pituitary",    "liver"),
    thyrotropin        = c("pituitary",    "thyroid"),
    thyroxin           = c("thyroid",      "liver"),
    trh                = c("hypothalamus", "pituitary"),
    tsh                = c("pituitary",    "thyroid"),
    vasopressin        = c("hypothalamus", "kidney"),
    vitamind           = c("kidney",       "intestine")
  )
  
  if (!hormone %in% names(hormone_map)) stop("Hormone not found")
  
  rows <- get(hormone)
  cols <- get(paste0(hormone, "_cols"))
  mapping <- hormone_map[[hormone]]
  
  source_tissue <- get(mapping[1])[rows, cols]
  target_tissue <- get(mapping[2])[rows, cols]
  print(dim(source_tissue))
  print(dim(target_tissue))
  

  
  print(hormone)
  snap_genes <- c(read.csv(paste("data/SNAP_data/unique_genes_", hormone, ".csv", sep = ""),
                           sep = ",", header = FALSE, stringsAsFactors = FALSE))
  
  snap_genes_inGTEx     <- intersect(snap_genes, colnames(source_tissue))
  snap_genes_inGTEx_ind <- match(snap_genes_inGTEx, colnames(source_tissue))
  
  source_variances <- apply(X = source_tissue, MARGIN = 2, FUN = var)
  source_sorted    <- sort(source_variances, decreasing = TRUE, index.return = TRUE)$ix[1:10000]
  target_variances <- apply(X = target_tissue, MARGIN = 2, FUN = var)
  target_sorted    <- sort(target_variances, decreasing = TRUE, index.return = TRUE)$ix[1:10000]
  
  union_columns <- union(union(source_sorted, target_sorted), snap_genes_inGTEx_ind)
  source_tissue <- source_tissue[, union_columns]
  target_tissue <- target_tissue[, union_columns]
  
  print("source and target tissues found")
  write.csv(colnames(source_tissue),
            file = paste("./data/", hormone, "_eQTL_gene_list_with_snap_v8_lt_latest_15k.csv", sep = ""),
            row.names = FALSE)
  
  df                 <- cbind(source_tissue, target_tissue)
  spearman_corraltion <- rcorr(as.matrix(df), type = "spearman")
  corr_matrix        <- spearman_corraltion$r
  prob_matrix        <- spearman_corraltion$P
  print("spearman correlation calculated")
  print(dim(prob_matrix))
  
  n <- length(colnames(source_tissue))
  corr_matrix[is.na(corr_matrix)] <- 0
  prob_matrix[is.na(prob_matrix)] <- 0.99
  prob_matrix_adj <- adjust_p(n, hormone, prob_matrix)
  
  write.table(corr_matrix,
              file = paste("./data/", hormone, "_eQTL_spearman_corr_matrix_with_snap_removed_CF_lt_R_latest_15k.csv", sep = ""),
              append = FALSE, sep = ",", row.names = TRUE, col.names = TRUE, qmethod = c("escape", "double"))
  
  print("finding s_sec scores")
  source_genes_full  <- apply(unique(read.csv(paste("./data/", hormone, "_source_genes_full.csv", sep = ""),
                                              header = FALSE, stringsAsFactors = FALSE)), 2, toupper)
  target_genes_full  <- apply(unique(read.csv(paste("./data/", hormone, "_target_genes_full.csv", sep = ""),
                                              header = FALSE, stringsAsFactors = FALSE)), 2, toupper)
  current_hormone_genes <- colnames(source_tissue)
  
  print(hormone)
  print("length of current_hormone_genes - genes per tissue")
  print(length(current_hormone_genes))
  print("No. of samples")
  print(dim(source_tissue)[1])
  
  compute_s_sec <- function(genes_full, prob_matrix, col_offset, n) {
    genes   <- intersect(genes_full, current_hormone_genes)
    indices <- match(genes, current_hormone_genes)
    mat     <- prob_matrix[col_offset, indices]
    mat[mat == 0] <- 0.00001
    s_sec   <- if (dim(as.matrix(mat))[2] < 2) -log(mat) else rowSums(-log(mat))
    list(genes = genes, s_sec = s_sec)
  }
  
  prob_matrix[prob_matrix == 0] <- 0.00001
  
  target_result <- compute_s_sec(target_genes_full, prob_matrix, col_offset = 1:n,          n = n)
  source_result <- compute_s_sec(source_genes_full, prob_matrix, col_offset = (n + 1):(2*n), n = n)
  
  write.csv(target_result$genes,
            file = paste("./data/", hormone, "_eQTL_target_genes_with_snap_lt_human_latest_15k.csv", sep = ""),
            row.names = FALSE)
  write.csv(source_result$genes,
            file = paste("./data/", hormone, "_eQTL_source_genes_with_snap_lt_human_latest_15k.csv", sep = ""),
            row.names = FALSE)
  
  print(paste("final source genes:", length(source_result$genes)))
  print(paste("final target genes:", length(target_result$genes)))
  
  write.csv(target_result$s_sec,
            file = paste("./output/", hormone, "_eQTL_s_sec_spearman_with_snap_removed_CF_lt_v8_latest_15k.csv", sep = ""))
  write.csv(source_result$s_sec,
            file = paste("./output/", hormone, "_eQTL_source_s_sec_spearman_with_snap_removed_CF_lt_v8_latest_15k.csv", sep = ""))
}



if(TRUE){
  ptm <- proc.time()
  hormones = c('insulin')
  library(foreach)
  library(doParallel)
  cores=detectCores()
  cl <- makeCluster(7) 
  registerDoParallel(cl)
  
# Exporting everything from global environment to each worker
  clusterExport(cl, varlist = ls(), envir = .GlobalEnv)
  
# Loading required libraries on each worker
  clusterEvalQ(cl, {
    library(WGCNA)
    library(Hmisc)
    library(EnsDb.Hsapiens.v86)
  })
  
  
  print("starting parallel code")
  foreach(i=1:length(hormones)) %dopar% {
    write_corr(hormones[i]) 
  }
  ##stop cluster
  stopCluster(cl)
  print("code complete")
  print(proc.time() - ptm)
}
