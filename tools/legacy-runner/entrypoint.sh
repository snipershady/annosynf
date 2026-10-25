#!/usr/bin/env bash
# Comandi del container legacy-runner (vedi Dockerfile). I percorsi sono interni al container:
# /legacy = Annocript in sola lettura, /in = input in sola lettura, /out = output.
set -euo pipefail

usage() {
    cat >&2 <<'USAGE'
Usage:
  legacy-runner versions                         versioni degli strumenti installati
  legacy-runner dna2pep <fasta> <none|strict|any> <out>
                                                 dna2pep come lo lancia Annocript (-O tab -o <mode> -r all)
  legacy-runner cpc2 <fasta> <out>               CPC2 come lo lancia Annocript, più <out>.scaled (vettori
                                                 delle feature dopo svm-scale, per diagnosticare il port)
  legacy-runner cpc2orf <fasta> <out>            ORF scelto da FindCDS di CPC2 (solo strand +) e sua traduzione
                                                 Biopython: "id<TAB>inizio 0-based<TAB>integrity<TAB>ORF<TAB>peptide"
                                                 (integrity grezza di FindCDS: 0 se non c'è ORF)
  legacy-runner composition <fasta> <out>        extractPercentages di LIB/output_and_stats.pm: <out> per
                                                 sequenza (ATGC_Percentages.txt) e <out>.averages (medie)
  legacy-runner slices <fasta> <n> <out>         create_slices di LIB/fasta_management.pm con n slice; <out> =
                                                 "id<TAB>slice" per ogni sequenza, nell'ordine delle slice
  legacy-runner rewrite <fasta> <out>            riscrittura con Bio::SeqIO (formato delle slice e dei FASTA
                                                 scritti da BioPerl: header completo, 60 colonne)
  legacy-runner fasomerecords <fasta> <list> <out>
                                                 faSomeRecords (UCSC) come in LIB/db_creator.pm
  legacy-runner geneticcode <id> <out>           tabella di traduzione che dna2pep usa con "-m <id>" (mod_translate):
                                                 "codone<TAB>aa interno<TAB>aa come primo codone<TAB>annotazione"
  legacy-runner translation <fasta> <outdir>     traduzioni di mod_translate (dna2pep) con il codice 1:
                                                 <outdir>/codons.tsv per tutti i 3375 codoni IUPAC e
                                                 <outdir>/sequences.tsv per le sequenze del FASTA (frame 1)
  legacy-runner shell                            shell interattiva
USAGE
    exit 64
}

# Copia di cleanHeader (LIB/execute_programs.pm): ogni header ridotto al primo token.
clean_header() {
    perl -ne 'if (/^>/) { my @p = split(" ", $_); print $p[0], "\n" } else { print }' "$1" > "$2"
}

cmd="${1:-}"
[[ -n "$cmd" ]] || usage
shift

case "$cmd" in
    versions)
        python --version 2>&1
        python -c 'import numpy, Bio; print("numpy " + numpy.__version__); print("biopython " + Bio.__version__)'
        perl -e 'print "perl $^V\n"'
        perl -MBio::Root::Version -e 'print "bioperl $Bio::Root::Version::VERSION\n"'
        perl -MBio::SearchIO -MBio::SeqIO -MBio::DB::SeqFeature::Store -MDBI -MDBD::mysql -MParallel::ForkManager \
             -MTerm::ReadKey -MList::MoreUtils -MError -MCGI -MArchive::Extract -MLWP::UserAgent -e 'print "perl modules ok\n"'
        R --version | head -n 1
        test -x "$CPC_HOME/libs/libsvm/libsvm-3.18/svm-scale" && test -x "$CPC_HOME/libs/libsvm/libsvm-3.18/svm-predict" && echo "libsvm 3.18 ok"
        test -x "$DNA2PEP_HOME/dna2pep.py" && echo "dna2pep 1.1 ok"
        # Senza argomenti faSomeRecords stampa l'uso ed esce con 255: basta che parta (librerie presenti).
        usage_text="$(faSomeRecords 2>&1 || true)"
        [[ "$usage_text" == "faSomeRecords - "* ]] && echo "faSomeRecords ok"
        ;;
    dna2pep)
        [[ $# -eq 3 ]] || usage
        fasta="$1" mode="$2" out="$3"
        [[ "$mode" =~ ^(none|strict|any)$ ]] || usage
        work="$(mktemp -d)"
        clean_header "$fasta" "$work/cleanh.fasta"
        "$DNA2PEP_HOME/dna2pep.py" -O tab -o "$mode" -r all "$work/cleanh.fasta" > "$out"
        rm -rf "$work"
        ;;
    cpc2)
        [[ $# -eq 2 ]] || usage
        fasta="$1" out="$2"
        work="$(mktemp -d)"
        clean_header "$fasta" "$work/cleanh.fasta"
        # Annocript: "python CPC2.py -i <clean> -o <out>" (solo strand +).
        python "$CPC_HOME/bin/CPC2.py" -i "$work/cleanh.fasta" -o "$out"
        # Stesso vettore che CPC2 passa a svm-scale (colonne 3..6 dell'output), poi scalato con cpc2.range.
        awk -F'\t' '!/^#/ { print "999 1:" $3 " 2:" $4 " 3:" $5 " 4:" $6 }' "$out" > "$work/features.svm"
        "$CPC_HOME/libs/libsvm/libsvm-3.18/svm-scale" -r "$CPC_HOME/data/cpc2.range" "$work/features.svm" > "$out.scaled"
        rm -rf "$work"
        ;;
    cpc2orf)
        [[ $# -eq 2 ]] || usage
        fasta="$1" out="$2"
        work="$(mktemp -d)"
        clean_header "$fasta" "$work/cleanh.fasta"
        # Stessa preparazione di calculate_potential (strip, U -> T, maiuscole), poi FindCDS e mRNA_translate
        # del modulo originale; -B: niente .pyc nell'immagine.
        python -B - "$work/cleanh.fasta" "$out" <<'PYTHON'
import re
import sys
sys.path.insert(0, "/opt/CPC2-beta/bin")
import CPC2
import seqio

fasta, out = sys.argv[1], sys.argv[2]
with open(out, "w") as handle:
    for seq in seqio.fasta_read(fasta):
        rna = re.sub("U", "T", str(seq.seq).strip(), flags=re.I).upper()
        cds, start, strand, integrity = CPC2.FindCDS(rna).longest_orf("+")
        handle.write("\t".join([seq.id, str(start), str(integrity), cds, str(CPC2.mRNA_translate(cds))]) + "\n")
PYTHON
        rm -rf "$work"
        ;;
    composition)
        [[ $# -eq 2 ]] || usage
        fasta="$1" out="$2"
        # Funzione originale del legacy; totSeqs come lo calcola Annocript (grep -c '>').
        total="$(grep -c '>' "$fasta")"
        perl -I/legacy -MLIB::output_and_stats -e '
            my ($fasta, $total, $out) = @ARGV;
            my @averages = LIB::output_and_stats::extractPercentages($fasta, $total, $out);
            open(my $fh, ">", "$out.averages") or die "Cannot write $out.averages: $!";
            print $fh join("\t", qw(Adenine Guanine Thymine Cytosine N GCcontent)), "\n", join("\t", @averages), "\n";
        ' "$fasta" "$total" "$out"
        ;;
    slices)
        [[ $# -eq 3 ]] || usage
        fasta="$1" count="$2" out="$3"
        work="$(mktemp -d)"
        # create_slices fa chdir nella cartella delle slice: percorsi assoluti.
        perl -I/legacy -MLIB::fasta_management -e 'LIB::fasta_management::create_slices(@ARGV)' \
            "$(realpath "$fasta")" slice "$count" "$work" > /dev/null
        for slice in $(find "$work/slice" -name 'slice_*.fasta' | sort -V); do
            number="${slice##*_}"
            awk -v n="${number%.fasta}" '/^>/ { print substr($1, 2) "\t" n }' "$slice"
        done > "$out"
        rm -rf "$work"
        ;;
    rewrite)
        [[ $# -eq 2 ]] || usage
        perl -MBio::SeqIO -e '
            my $in = Bio::SeqIO->new(-file => $ARGV[0], -format => "fasta");
            my $out = Bio::SeqIO->new(-file => ">$ARGV[1]", -format => "fasta");
            while (my $seq = $in->next_seq) { $out->write_seq($seq) }
        ' "$1" "$2"
        ;;
    fasomerecords)
        [[ $# -eq 3 ]] || usage
        faSomeRecords "$1" "$2" "$3"
        ;;
    geneticcode)
        [[ $# -eq 2 ]] || usage
        out="$(realpath "$2")"
        (cd "$DNA2PEP_HOME" && python -c '
import sys, mod_translate
table = mod_translate.parseMatrixFile(sys.argv[1])
with open(sys.argv[2], "w") as out:
    for b1 in "TCAG":
        for b2 in "TCAG":
            for b3 in "TCAG":
                codon = b1 + b2 + b3
                out.write("%s\t%s\t%s\t%s\n" % (codon, table.d_all[codon], table.d_first[codon], mod_translate.annotate(codon, table)))
' "$1" "$out")
        ;;
    translation)
        [[ $# -eq 2 ]] || usage
        mkdir -p "$2"
        fasta="$(realpath "$1")" outdir="$(realpath "$2")"
        (cd "$DNA2PEP_HOME" && python -c '
import sys, itertools, mod_translate
table = mod_translate.parseMatrixFile("1")
iupac = "ACGTRYMKWSBDHVN"
with open(sys.argv[2] + "/codons.tsv", "w") as out:
    out.write("codon\tfirst\tinternal\tfirst_stop\tannotation\n")
    for letters in itertools.product(iupac, repeat=3):
        codon = "".join(letters)
        out.write("%s\t%s\t%s\t%s\t%s\n" % (codon,
            mod_translate.translate(codon, table, True, True),
            mod_translate.translate(codon, table, False, True),
            mod_translate.translate(codon, table, True, False),
            mod_translate.annotate(codon, table)))
with open(sys.argv[2] + "/sequences.tsv", "w") as out:
    out.write("id\tstop_at_first_stop\tread_through\tinternal_only\tannotation\n")
    name, chunks = None, []
    def flush():
        if name is not None:
            seq = "".join(chunks)
            out.write("%s\t%s\t%s\t%s\t%s\n" % (name,
                mod_translate.translate(seq, table, True, False),
                mod_translate.translate(seq, table, True, True),
                mod_translate.translate(seq, table, False, True),
                mod_translate.annotate(seq, table)))
    for line in open(sys.argv[1]):
        line = line.strip()
        if line.startswith(">"):
            flush()
            name, chunks = line[1:].split()[0], []
        elif line:
            chunks.append(line)
    flush()
' "$fasta" "$outdir")
        ;;
    shell)
        exec bash
        ;;
    *)
        usage
        ;;
esac
