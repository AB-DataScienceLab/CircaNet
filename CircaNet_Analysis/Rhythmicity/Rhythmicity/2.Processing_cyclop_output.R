library(data.table)
library(dplyr)
library(tidyr)
library(ComplexHeatmap)
library(circlize)

# --- THE CRITICAL FIX: PATHS ---
# We point to exactly where the Julia log showed your files were
phase_dir <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/GTEX_CIRCADIAN_RESULTS/"
expr_dir  <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/data/tissues_for_julia/"
final_dir <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/ATLAS_FINAL_OUTPUTS/"

if(!dir.exists(final_dir)) dir.create(final_dir)
ht_opt$message = FALSE

# 1. TEST THE PATH (Safety Check)
first_test_file <- list.files(expr_dir)[1]
if(is.na(first_test_file)) {
  stop("ABORT: PI Briefing - I still can't see the files in '", expr_dir, "'. Please double check your 'data' folder location!")
} else {
  message("PATH SUCCESS: I found your expression files starting with: ", first_test_file)
}

# 2. LOAD FILES
phase_files <- list.files(phase_dir, pattern = "_Phases.csv$")
atlas_summary <- data.frame(Tissue = character(), Total_Tested = numeric(), 
                            Rhythmic_q05 = numeric(), stringsAsFactors = FALSE)

# 3. ANALYSIS LOOP
for (pf in phase_files) {
  organ_name <- gsub("_Phases.csv", "", pf)
  message("\n--- ANALYZING: ", organ_name)
  
  # Search for matching Ready file (handles naming logic better)
  possible_expr_file <- file.path(expr_dir, paste0(organ_name, "_Ready.csv"))
  
  if(!file.exists(possible_expr_file)) {
    # Fallback for tissues with dashes like 'Adipose-Subcutaneous'
    possible_expr_file <- list.files(expr_dir, pattern = organ_name, full.names = TRUE)[1]
  }
  
  tryCatch({
    # Load
    ph_data <- fread(file.path(phase_dir, pf))
    ex_raw  <- fread(possible_expr_file)
    
    colnames(ex_raw)[1] <- "Gene_Symbol"
    genes_only <- ex_raw[!(Gene_Symbol %in% c("Batch_D", "Site_D", "Center_D"))]
    colnames(genes_only) <- gsub("-", "_", colnames(genes_only))
    ph_data$ID <- gsub("-", "_", ph_data$ID)
    
    # Intersect and Process
    common <- intersect(colnames(genes_only)[-1], ph_data$ID)
    if(length(common) < 50) next
    
    expr_sub <- genes_only %>%
      group_by(Gene_Symbol) %>%
      summarise(across(all_of(common), ~ mean(as.numeric(.), na.rm = TRUE))) %>%
      ungroup()
    
    mat_data <- as.matrix(expr_sub[, -1]); rownames(mat_data) <- expr_sub$Gene_Symbol
    ph_sub <- ph_data[match(common, ph_data$ID), ]
    phases_vec <- ph_sub$Phase
    
    # Cosinor Core
    res_p <- apply(mat_data, 1, function(row_vals) {
      if(var(row_vals, na.rm=TRUE) == 0) return(NA)
      s <- summary(lm(log2(row_vals+1) ~ sin(phases_vec) + cos(phases_vec)))$fstatistic
      if(is.null(s)) return(NA)
      return(pf(s[1], s[2], s[3], lower.tail = FALSE))
    })
    
    res_df <- data.frame(Gene_Symbol = rownames(mat_data), P_Value = res_p) %>%
      filter(!is.na(P_Value)) %>%
      mutate(FDR_q = p.adjust(P_Value, method = "BH")) %>%
      arrange(FDR_q)
    
    fwrite(res_df, file.path(final_dir, paste0(organ_name, "_Discovery_List.csv")))
    
    n_q05  <- sum(res_df$FDR_q < 0.05, na.rm = TRUE)
    atlas_summary <- rbind(atlas_summary, data.frame(Tissue = organ_name, Total_Tested = nrow(res_df), Rhythmic_q05 = n_q05))
    message("SUCCESS: Found ", n_q05, " rhythmic genes.")
    
    # DRAW HEATMAP (Only for high-signal tissues)
    top_hits <- res_df %>% filter(FDR_q < 1e-4) %>% pull(Gene_Symbol)
    if(length(top_hits) >= 25) {
      hm_mat <- mat_data[rownames(mat_data) %in% head(top_hits, 1000), ]
      hm_z   <- t(apply(hm_mat, 1, scale))
      colnames(hm_z) <- common
      hm_sorted <- hm_z[order(apply(hm_z[, order(phases_vec)], 1, function(x) which.max(caTools::runmean(x, 10)))), order(phases_vec)]
      
      pdf(file.path(final_dir, paste0(organ_name, "_Atlas.pdf")), width = 10, height = 7)
      print(Heatmap(apply(hm_sorted, 2, scale), name="Z", cluster_columns=F, cluster_rows=F, show_row_names=F, show_column_names=F,
                    column_title=paste(organ_name, "Temporal Atlas"), use_raster=T, col=colorRamp2(c(-2,0,2), c("blue","white","red"))))
      dev.off()
    }
    
  }, error = function(e) message("FAIL: ", organ_name, " Error: ", e$message))
}

# Save Master Summary
fwrite(atlas_summary, file.path(final_dir, "HUMAN_CIRCADIAN_ATLAS_SUMMARY.csv"))
message("MISSION COMPLETED. All folders analyzed.")
