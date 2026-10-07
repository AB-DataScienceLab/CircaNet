#!/bin/bash
set -euo pipefail

GNOMAD_DIR=/home/anshu/Shweta/gnomad
CLINVAR_RENAMED=/home/anshu/Shweta/Circanet/ClinVar/BIAS_clinvar/clinvar_renamed.vcf.gz
OUT_DIR=/home/anshu/Shweta/Circanet/ClinVar/BIAS_clinvar/gnomad_annotated
mkdir -p "$OUT_DIR"

TAGS="INFO/AF_joint_raw,INFO/AF_joint_XX,INFO/AF_joint_XY,INFO/AF_joint_afr,INFO/AF_joint_afr_XX,INFO/AF_joint_afr_XY,INFO/AF_joint_ami,INFO/AF_joint_ami_XX,INFO/AF_joint_ami_XY,INFO/AF_joint_amr,INFO/AF_joint_amr_XX,INFO/AF_joint_amr_XY,INFO/AF_joint_asj,INFO/AF_joint_asj_XX,INFO/AF_joint_asj_XY,INFO/AF_joint_eas,INFO/AF_joint_eas_XX,INFO/AF_joint_eas_XY,INFO/AF_joint_fin,INFO/AF_joint_fin_XX,INFO/AF_joint_fin_XY,INFO/AF_joint_mid,INFO/AF_joint_mid_XX,INFO/AF_joint_mid_XY,INFO/AF_joint_nfe,INFO/AF_joint_nfe_XX,INFO/AF_joint_nfe_XY,INFO/AF_joint_remaining,INFO/AF_joint_remaining_XX,INFO/AF_joint_remaining_XY,INFO/AF_joint_sas,INFO/AF_joint_sas_XX,INFO/AF_joint_sas_XY,INFO/AF_joint,INFO/faf95_joint"

CHRS=(chr1 chr2 chr3 chr4 chr5 chr6 chr7 chr8 chr9 chr10 chr11 chr12 chr13 chr14 chr15 chr16 chr17 chr18 chr19 chr20 chr21 chr22 chrX chrY chrM)

annotate_one () {
  set -euo pipefail
  chr=$1
  gnomad="$GNOMAD_DIR/gnomad.joint.v4.1.sites.${chr}.vcf.bgz"
  out="$OUT_DIR/clinvar_${chr}_gnomadAF.vcf.gz"

  # Extract just this chromosome's records from the clinvar file first
  region_vcf="$OUT_DIR/clinvar_${chr}_only.vcf.gz"
  bcftools view -r "$chr" "$CLINVAR_RENAMED" -Oz -o "$region_vcf"
  tabix -p vcf "$region_vcf"

  if [[ "$chr" == "chrM" || ! -f "$gnomad" ]]; then
    # No gnomad data for MT, just carry the clinvar records through unannotated
    cp "$region_vcf" "$out"
    cp "$region_vcf.tbi" "$out.tbi"
    echo "DONE (no gnomad, chrM): $chr"
    rm -f "$region_vcf" "$region_vcf.tbi"
    return 0
  fi

  bcftools annotate -a "$gnomad" -c "$TAGS" "$region_vcf" -Oz -o "$out"
  tabix -p vcf "$out"
  rm -f "$region_vcf" "$region_vcf.tbi"
  echo "DONE: $chr"
}
export -f annotate_one
export GNOMAD_DIR CLINVAR_RENAMED OUT_DIR TAGS

printf '%s\n' "${CHRS[@]}" | xargs -P 6 -n1 bash -c 'annotate_one "$0"' 2>&1 | tee clinvar_annotate_log.txt