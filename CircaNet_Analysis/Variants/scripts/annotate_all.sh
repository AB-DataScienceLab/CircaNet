#!/bin/bash
set -euo pipefail

DBSNP_DIR=/home/anshu/Shweta/Circanet/ClinVar/split/renamed
GNOMAD_DIR=/home/anshu/Shweta/gnomad
OUT_DIR=/home/anshu/Shweta/Circanet/annotated
TMP_DIR=/home/anshu/Shweta/Circanet/tmp_dbsnp
mkdir -p "$OUT_DIR" "$TMP_DIR"

TAGS="INFO/AF_joint_raw,INFO/AF_joint_XX,INFO/AF_joint_XY,\
INFO/AF_joint_afr,INFO/AF_joint_afr_XX,INFO/AF_joint_afr_XY,\
INFO/AF_joint_ami,INFO/AF_joint_ami_XX,INFO/AF_joint_ami_XY,\
INFO/AF_joint_amr,INFO/AF_joint_amr_XX,INFO/AF_joint_amr_XY,\
INFO/AF_joint_asj,INFO/AF_joint_asj_XX,INFO/AF_joint_asj_XY,\
INFO/AF_joint_eas,INFO/AF_joint_eas_XX,INFO/AF_joint_eas_XY,\
INFO/AF_joint_fin,INFO/AF_joint_fin_XX,INFO/AF_joint_fin_XY,\
INFO/AF_joint_mid,INFO/AF_joint_mid_XX,INFO/AF_joint_mid_XY,\
INFO/AF_joint_nfe,INFO/AF_joint_nfe_XX,INFO/AF_joint_nfe_XY,\
INFO/AF_joint_remaining,INFO/AF_joint_remaining_XX,INFO/AF_joint_remaining_XY,\
INFO/AF_joint_sas,INFO/AF_joint_sas_XX,INFO/AF_joint_sas_XY,\
INFO/AF_joint,INFO/faf95_joint"

annotate_one () {
  set -euo pipefail
  chr=$1
  dbsnp="$DBSNP_DIR/dbsnp_${chr}.vcf.gz"
  gnomad="$GNOMAD_DIR/gnomad.joint.v4.1.sites.${chr}.vcf.bgz"
  norm_dbsnp="$TMP_DIR/dbsnp_${chr}_norm.vcf.gz"
  out="$OUT_DIR/dbsnp_${chr}_gnomadAF.vcf.gz"

  if [[ ! -f "$dbsnp" || ! -f "$gnomad" ]]; then
    echo "SKIP: $chr (missing input)"
    return 0
  fi

  echo "START: $chr..."

  # 1. Normalize dbSNP to a temporary file and index it
  bcftools norm -m -any -Oz "$dbsnp" -o "$norm_dbsnp"
  tabix -p vcf "$norm_dbsnp"

  # 2. Annotate the indexed normalized dbSNP file against gnomAD
  bcftools annotate -a "$gnomad" -c "$TAGS" "$norm_dbsnp" -Oz -o "$out"

  # 3. Index final output and clean up temporary dbSNP file
  tabix -p vcf "$out"
  rm -f "$norm_dbsnp" "$norm_dbsnp.tbi"

  echo "DONE: $chr"
}
export -f annotate_one
export DBSNP_DIR GNOMAD_DIR OUT_DIR TMP_DIR TAGS

CHRS=(chr1 chr2 chr3 chr4 chr5 chr6 chr7 chr8 chr9 chr10 chr11 chr12 chr13 chr14 chr15 chr16 chr17 chr18 chr19 chr20 chr21 chr22 chrX chrY)

printf '%s\n' "${CHRS[@]}" | xargs -P 12 -n1 bash -c 'annotate_one "$1"' _ 2>&1 | tee annotate_log.txt