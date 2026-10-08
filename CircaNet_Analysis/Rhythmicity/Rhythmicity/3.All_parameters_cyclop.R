library(data.table)
library(dplyr)
library(tidyr)

# --- DIRECTORY CONFIGURATION ---
phase_dir <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/GTEX_CIRCADIAN_RESULTS/"
expr_dir  <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/data/tissues_for_julia/"
final_dir <- "/home/shwphd/Shweta/Circadian_rhythm_Phd_data/Ab_nhi_krugi_change/Cyclops/ATLAS_DETAILED_STATS/"

if(!dir.exists(final_dir)) dir.create(final_dir)

# Helper function to prevent NA errors
safe_pf <- function(f, df1, df2) {
  if (is.na(f)) return(NA)
  return(pf(f, df1, df2, lower.tail = FALSE))
}

# --- BATCH PROCESSING LOOP ---
phase_files <- list.files(phase_dir, pattern = "_Phases.csv$")

for (pf in phase_files) {
  organ_name <- gsub("_Phases.csv", "", pf)
  message("\n>>> PH.D. ANALYSIS: Extracting Phasic Metrics for ", organ_name)
  
  expr_file <- file.path(expr_dir, paste0(organ_name, "_Ready.csv"))
  if(!file.exists(expr_file)) {
    expr_file <- list.files(expr_dir, pattern = organ_name, full.names = TRUE)[1]
  }
  
  tryCatch({
    # 1. Loading & Alignment
    ph_data <- fread(file.path(phase_dir, pf))
    ex_raw  <- fread(expr_file)
    colnames(ex_raw)[1] <- "Gene_Symbol"
    
    # 2. Basic cleanup (Removal of headers, dash cleanup)
    genes_only <- ex_raw[!(Gene_Symbol %in% c("Batch_D", "Site_D", "Center_D"))]
    colnames(genes_only) <- gsub("-", "_", colnames(genes_only))
    ph_data$ID <- gsub("-", "_", ph_data$ID)
    
    # 3. Synchronizing Donor Overlap
    common <- intersect(colnames(genes_only)[-1], ph_data$ID)
    
    # Efficient averaging of same-named genes
    expr_sub <- genes_only %>% 
      group_by(Gene_Symbol) %>% 
      summarise(across(all_of(common), ~ mean(as.numeric(.), na.rm = TRUE))) %>% 
      ungroup()
    
    mat_data <- as.matrix(expr_sub[, -1])
    rownames(mat_data) <- expr_sub$Gene_Symbol
    mode(mat_data) <- "numeric"
    
    # PRE-FILTER: Keep only rows that are not all zeroes
    mat_data <- mat_data[rowSums(mat_data > 0.1) > 10, ]
    
    phases_vec <- ph_data$Phase[match(common, ph_data$ID)]
    
    # 4. MATH ENGINE: Cosinor extraction
    calc_metrics <- function(y, p) {
      # Return 5 NAs if calculation is impossible
      if (var(y, na.rm=TRUE) == 0 || is.na(var(y))) return(c(NA, NA, NA, NA, NA))
      
      log_y <- log2(y + 1)
      fit   <- lm(log_y ~ sin(p) + cos(p))
      s     <- summary(fit)
      f     <- s$fstatistic
      
      if(is.null(f)) return(c(NA, NA, NA, NA, NA))
      
      pv  <- pf(f[1], f[2], f[3], lower.tail = FALSE)
      mes <- coef(fit)[1]
      b1  <- coef(fit)[2]
      b2  <- coef(fit)[3]
      amp <- sqrt(b1^2 + b2^2)
      ac  <- atan2(b1, b2)
      if (ac < 0) ac <- ac + 2*pi
      
      return(c(P_Value=pv, Mesor=mes, Amplitude=amp, Acrophase_Rad=ac, R2=s$r.squared))
    }
    
    # apply returns a matrix where rows are metrics
    res_matrix <- apply(mat_data, 1, calc_metrics, p = phases_vec)
    
    # 5. RECONSTRUCT RESULTS DATA FRAME (Strong approach)
    res_df <- as.data.frame(t(res_matrix))
    colnames(res_df) <- c("P_Value", "Mesor", "Amplitude", "Acrophase_Rad", "R_Squared")
    res_df$Gene_Symbol <- rownames(res_df)
    
    # 6. CALCULATE FINAL DISCOVERIES
    final_output <- res_df %>%
      filter(!is.na(P_Value)) %>%
      mutate(across(c(P_Value, Mesor, Amplitude, Acrophase_Rad, R_Squared), as.numeric)) %>%
      mutate(FDR_q = p.adjust(P_Value, method = "BH")) %>%
      mutate(Relative_Amplitude = Amplitude / (Mesor + 0.1)) %>%
      select(Gene_Symbol, Mesor, Amplitude, Relative_Amplitude, Acrophase_Rad, R_Squared, P_Value, FDR_q) %>%
      arrange(FDR_q)
    
    save_name <- file.path(final_dir, paste0(organ_name, "_FULL_STATS.csv"))
    fwrite(final_output, save_name)
    message("--- SUCCESS: Generated detailed report for ", organ_name)
    
  }, error = function(e) message("FAIL: ", organ_name, " Error detail: ", e$message))
}

print("PI SUMMARY: Cross-Tissue Circadian Parameter Atlas created.")