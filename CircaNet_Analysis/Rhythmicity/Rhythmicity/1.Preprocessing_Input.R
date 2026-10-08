# 1. THE TOOLS 
library(data.table)
library(dplyr)

# 2. PATHS (GTEx v11)
tpm_path  <- "GTEx_Analysis_2026-05-19_v11_RNASeQCv2.4.3_gene_tpm.gct.gz"
anno_path <- "GTEx_Analysis_v11_Annotations_SampleAttributesDS.txt"
out_dir   <- "tissues_for_julia"

# Create folder safely
if(!dir.exists(out_dir)) dir.create(out_dir)

# 3. METADATA LOADING
meta <- fread(anno_path)

# Filter for tissues with Ph.D. quality sample size (N >= 100)
tissue_list <- meta %>% 
  group_by(SMTSD) %>% 
  summarize(n=n()) %>% 
  filter(n >= 100) %>% 
  pull(SMTSD)

# Pre-extract file headers once to save CPU time
all_headers <- colnames(fread(tpm_path, skip = 2, nrows = 0))

# 4. START AUTOMATION LOOP
for (tiss in tissue_list) {
  
  # Clean Tissue Name: "Heart - Left Ventricle" -> "Heart_LeftVentricle"
  safe_name <- gsub(" - ", "_", tiss)
  safe_name <- gsub(" ", "_", safe_name)
  safe_name <- gsub("[[:punct:]]", "", safe_name)
  
  message(">>> PI PROTOCOL: Extracting ", tiss)
  
  # Matching Logic
  ids <- meta[SMTSD == tiss, SAMPID]
  cols <- which(all_headers == "Description" | gsub("\\.", "-", all_headers) %in% ids)
  
  if(length(cols) < 101) next # Final safety check on size
  
  # Efficient Load
  raw <- fread(tpm_path, skip = 2, select = cols)
  colnames(raw)[1] <- "Gene_Symbol"
  
  # 5. DEDUPLICATION (Smashes duplicates into 1 Average Row)
  # The '.' inside summarize ensures all columns are converted to numeric numbers
  raw_dedup <- raw %>% 
    group_by(Gene_Symbol) %>% 
    summarise(across(everything(), ~ mean(as.numeric(.), na.rm = TRUE))) %>% 
    ungroup()
  
  # 6. QUALITY FILTERS
  # Filter A: Remove noisy genes that aren't 'turned on' (Avg < 0.5 TPM)
  raw_dedup <- raw_dedup[rowMeans(raw_dedup[,-1]) > 0.5, ]
  
  # 7. TRANSFORMATION
  # log2 correction ensures data is symmetric (Required for high-impact journals)
  raw_dedup[,-1] <- log2(raw_dedup[,-1] + 1)
  
  # 8. JULIA COMPATIBILITY HEADERS
  # We add two dummy rows so CYCLOPS doesn't crash on technical noise (NaNs)
  # The software 'Fit' function sees the 'Batch_D' and knows what to do.
  headers <- data.frame(
    Gene_Symbol = c("Batch_D", "Site_D"), 
    matrix("dummy_value", nrow = 2, ncol = ncol(raw_dedup)-1)
  )
  colnames(headers) <- colnames(raw_dedup)
  
  # GLUE, EXPORT AND FREE RAM
  final <- rbind(headers, raw_dedup)
  fwrite(final, file.path(out_dir, paste0(safe_name, "_Ready.csv")))
  
  rm(raw, raw_dedup, final, headers); gc()
  
  message("--- SUCCESS: ", safe_name)
}

print("PI FINAL BRIEFING: All tissues exported and ready for Julia machine learning.")
