#!/usr/bin/env bash
# Genera i golden dei port nativi (TODO.md step 0.2, 1.4, 1.5) con il container legacy-runner, eseguendo il
# codice originale come lo usa Annocript. Per ogni *_transcriptome.fasta di tests/Fixtures/fasta/: dna2pep 1.1
# (modalità none, strict, any), CPC2-beta, composizione in basi (extractPercentages), traduzioni di mod_translate
# e slice (create_slices con 3 e 10 slice). Inoltre: riscrittura con Bio::SeqIO di format_cases.fasta, faSomeRecords di
# example_subset_ids.txt su example_transcriptome.fasta, la tabella del codice genetico 1 di dna2pep e dna2pep
# sui casi limite di orf_cases.fasta; l'ORF scelto da CPC2 (FindCDS) sui set e, con l'output completo di CPC2, sui
# casi limite di cpc2_orf_cases.fasta; la traduzione Biopython di tutti i codoni IUPAC e il punto isoelettrico di isoelectric_cases.tsv; svm-predict su un modello minimo; la versione della release (getDBVersion) dai file di
# tests/Fixtures/reference/release/; il parsing degli header UniProtKB e UniRef (createUniprotKBDB, createUnirefDB) di
# tests/Fixtures/reference/headers/; la goTable di createGOTable sugli OBO di tests/Fixtures/reference/go/;
# enzymeTable, mapping accession → enzimi e versione di createEnzymeTable su tests/Fixtures/reference/enzyme/;
# pathwaysTable e mapping accession → pathway di createPathwayTableAndMapping su tests/Fixtures/reference/pathway/;
# cdTable e mapping dominio → GO di createCDTable e createPfamGO su tests/Fixtures/reference/cdd/;
# mapping proteina/cluster → GO di extractFilteredMappingFile + createUniprotGO su tests/Fixtures/reference/idmapping/. Scrive in tests/Fixtures/golden/<set>/ e aggiorna i checksum in SHA256SUMS.
#
#   tools/legacy-runner/golden-ports.sh
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
runner="$project_dir/tools/legacy-runner/run.sh"
golden_dir="$project_dir/tests/Fixtures/golden"

for fasta in "$project_dir"/tests/Fixtures/fasta/*_transcriptome.fasta; do
    set_name="$(basename "$fasta" _transcriptome.fasta)"
    relative="tests/Fixtures/fasta/$(basename "$fasta")"
    out="$golden_dir/$set_name"
    mkdir -p "$out"
    export LEGACY_RUNNER_OUT="$out"

    for mode in none strict any; do
        "$runner" dna2pep "$relative" "$mode" "/out/dna2pep_$mode.tab"
    done

    "$runner" cpc2 "$relative" /out/cpc2.tsv 2>/dev/null
    mv "$out/cpc2.tsv.scaled" "$out/cpc2_scaled.svm"
    "$runner" cpc2orf "$relative" /out/cpc2_orf.tsv 2>/dev/null

    "$runner" composition "$relative" /out/atgc_percentages.tsv

    "$runner" translation "$relative" /out/translation

    for slices in 3 10; do
        "$runner" slices "$relative" "$slices" "/out/slices_$slices.tsv"
    done
done

# codons.tsv non dipende dal FASTA: se ne tiene una copia sola.
mkdir -p "$golden_dir/translation"
cmp "$golden_dir/trial/translation/codons.tsv" "$golden_dir/example/translation/codons.tsv"
mv "$golden_dir/trial/translation/codons.tsv" "$golden_dir/translation/codons.tsv"
rm "$golden_dir/example/translation/codons.tsv"

mkdir -p "$golden_dir/format" "$golden_dir/subset" "$golden_dir/genetic_code" "$golden_dir/orf_cases" "$golden_dir/cpc2_cases"
export LEGACY_RUNNER_OUT="$golden_dir/cpc2_cases"
"$runner" cpc2orf tests/Fixtures/fasta/cpc2_orf_cases.fasta /out/cpc2_orf.tsv 2>/dev/null
"$runner" cpc2 tests/Fixtures/fasta/cpc2_orf_cases.fasta /out/cpc2.tsv 2>/dev/null
mv "$golden_dir/cpc2_cases/cpc2.tsv.scaled" "$golden_dir/cpc2_cases/cpc2_scaled.svm"
"$runner" cpc2codons /out/codons.tsv
"$runner" isoelectric tests/Fixtures/peptides/isoelectric_cases.tsv /out/isoelectric.tsv
mkdir -p "$golden_dir/libsvm"
LEGACY_RUNNER_OUT="$golden_dir/libsvm" "$runner" svmpredict tests/Fixtures/libsvm/tiny.svm tests/Fixtures/libsvm/tiny.model /out/tiny.predictions
for mode in none strict any; do
    LEGACY_RUNNER_OUT="$golden_dir/orf_cases" "$runner" dna2pep tests/Fixtures/fasta/orf_cases.fasta "$mode" "/out/dna2pep_$mode.tab"
done
LEGACY_RUNNER_OUT="$golden_dir/genetic_code" "$runner" geneticcode 1 /out/table_1.tsv
LEGACY_RUNNER_OUT="$golden_dir/format" "$runner" rewrite tests/Fixtures/fasta/format_cases.fasta /out/bioperl_rewrite.fasta
LEGACY_RUNNER_OUT="$golden_dir/subset" "$runner" fasomerecords tests/Fixtures/fasta/example_transcriptome.fasta \
    tests/Fixtures/fasta/example_subset_ids.txt /out/example_subset.fasta

mkdir -p "$golden_dir/reference"
LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" dbversion /out/release_versions.tsv \
    $(cd "$project_dir" && ls tests/Fixtures/reference/release/* | LC_ALL=C sort)
for type in uniprotkb uniref; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" headers "$type" "tests/Fixtures/reference/headers/$type.headers" \
        "/out/${type}_headers.tsv" 2>/dev/null
done
for obo in go-basic typedef_between_terms; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" goterms "tests/Fixtures/reference/go/$obo.obo" "/out/go_${obo}.tsv"
done
for dat in enzyme enzyme_last_without_dr; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" enzymes "tests/Fixtures/reference/enzyme/$dat.dat" "/out/enzymes_${dat}.tsv"
done
for txt in pathway pathway_without_proteins; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" pathways "tests/Fixtures/reference/pathway/$txt.txt" "/out/pathways_${txt}.tsv"
done
for pfam2go in pfam2go pfam2go_missing_first; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" domains tests/Fixtures/reference/cdd/cddid_all.tbl \
        "tests/Fixtures/reference/cdd/$pfam2go" "/out/domains_${pfam2go}.tsv"
done
for target in uniprotkb UniRef50 UniRef90 UniRef100; do
    LEGACY_RUNNER_OUT="$golden_dir/reference" "$runner" idmapping tests/Fixtures/reference/idmapping/idmapping_selected.tab \
        "$target" "/out/idmapping_${target}.tsv"
done

# Checksum degli input e degli output: un golden rigenerato deve risultare identico.
(cd "$project_dir/tests/Fixtures" && sha256sum fasta/*_transcriptome.fasta fasta/format_cases.fasta fasta/example_subset_ids.txt fasta/orf_cases.fasta fasta/cpc2_orf_cases.fasta peptides/isoelectric_cases.tsv libsvm/tiny.model libsvm/tiny.svm reference/release/* reference/headers/* reference/go/* reference/enzyme/* reference/pathway/* reference/cdd/* reference/idmapping/* \
    golden/*/*.tab golden/*/cpc2.tsv golden/*/cpc2_scaled.svm golden/*/cpc2_orf.tsv golden/cpc2_cases/codons.tsv golden/cpc2_cases/isoelectric.tsv golden/libsvm/tiny.predictions golden/reference/release_versions.tsv golden/reference/*_headers.tsv golden/reference/go_*.tsv golden/reference/enzymes_* golden/reference/pathways_* golden/reference/domains_* golden/reference/idmapping_* golden/*/atgc_percentages.tsv golden/*/atgc_percentages.tsv.averages \
    golden/*/slices_*.tsv golden/format/bioperl_rewrite.fasta golden/subset/example_subset.fasta golden/genetic_code/table_1.tsv golden/translation/codons.tsv golden/*/translation/sequences.tsv golden/orf_cases/*.tab) > "$golden_dir/SHA256SUMS"
echo "Golden written to $golden_dir"
