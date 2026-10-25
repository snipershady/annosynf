# Golden dei port nativi

Output dei programmi esterni e di alcune funzioni di Annocript 2.0.1 sui FASTA di `tests/Fixtures/fasta/`, prodotti **una volta**
dal container `legacy-runner` (TODO.md step 0.2). Sono il riferimento dei test di parità dei port PHP:
`OrfFinder` contro dna2pep (step 2.5), `CpcOrfFinder` contro FindCDS di CPC2 (step 3.1), `Cpc2Translator` contro Biopython (step 3.2), `FickettScore` contro CPC2 (step 3.3), `IsoelectricPoint` contro Biopython (step 3.4), `Libsvm*` contro libsvm (step 3.5), `Cpc2Predictor` contro CPC2 (step 3.6), `SequenceComposition` contro
`extractPercentages` (step 1.4), `FastaFormatter`/`FastaSlicer`/`FastaSubset` contro Bio::SeqIO, `create_slices`
e `faSomeRecords` (step 1.5), `GeneticCode` contro la tabella di dna2pep (step 2.1), `Translator` contro mod_translate (step 2.2), `OrfFinder` e `Dna2PepLineFormatter` contro dna2pep, campo per campo e byte per byte (step 2.3–2.5). Dopo la fase 11 restano come
dati di regressione congelati: nessun test li rigenera né richiede il container.

## Contenuto

| File | Origine | Righe |
|---|---|---|
| `<set>/dna2pep_none.tab`, `dna2pep_strict.tab`, `dna2pep_any.tab` | dna2pep 1.1, una modalità per file | una per sequenza |
| `<set>/cpc2.tsv` | CPC2-beta, output completo con intestazione | intestazione + una per sequenza |
| `<set>/cpc2_orf.tsv` | ORF scelto da `FindCDS` di CPC2 e sua traduzione Biopython (comando `cpc2orf`): `id, inizio 0-based, integrity (0 = nessun ORF), ORF, peptide` | una per sequenza |
| `cpc2_cases/cpc2_orf.tsv`, `cpc2.tsv`, `cpc2_scaled.svm` | gli stessi file di CPC2 sui casi limite di `fasta/cpc2_orf_cases.fasta` (pareggi tra frame, ATG interni, ORF senza stop, IUPAC, minuscole e U, nessun ATG; per il Fickett una base, molte N, omopolimero) | una per sequenza |
| `cpc2_cases/codons.tsv` | `mRNA_translate` di CPC2 (Biopython 1.76, `Seq.translate()`) per tutti i 4913 codoni su `ACGTRYMKWSBDHVNX-`; `!` dove Biopython solleva TranslationError (comando `cpc2codons`) | 4913 |
| `cpc2_cases/isoelectric.tsv` | `ProtParam.ProteinAnalysis(p).isoelectric_point()` di Biopython 1.76 sui peptidi di `peptides/isoelectric_cases.tsv` (terminali, residui carichi, stop interni, casi casuali): `nome, repr, str` (comando `isoelectric`) | 80 |
| `libsvm/tiny.predictions` | `svm-predict -b 1` di libsvm 3.18 sul modello minimo `libsvm/tiny.model` (etichette 0 1, un vettore di supporto senza feature) e sui vettori di `libsvm/tiny.svm` (comando `svmpredict`) | intestazione + 8 |
| `<set>/cpc2_scaled.svm` | vettori delle feature di CPC2 dopo `svm-scale -r cpc2.range` | una per sequenza |
| `<set>/atgc_percentages.tsv` | `extractPercentages` di `LIB/output_and_stats.pm` (il file `ATGC_Percentages.txt`) | intestazione + una per sequenza |
| `<set>/atgc_percentages.tsv.averages` | le medie restituite dalla stessa funzione (A, G, T, C, N, GC complessivo) | intestazione + una |
| `<set>/slices_3.tsv`, `slices_10.tsv` | `create_slices` di `LIB/fasta_management.pm` con 3 e 10 slice: `id<TAB>slice` | una per sequenza |
| `format/bioperl_rewrite.fasta` | `fasta/format_cases.fasta` riscritto con `Bio::SeqIO` (casi limite del formato) | — |
| `genetic_code/table_1.tsv` | tabella che dna2pep usa di default (`-m 1`), scritta da `mod_translate`: `codone, aa interno, aa come primo codone, annotazione` | 64 |
| `translation/codons.tsv` | `mod_translate.translate`/`annotate` (codice 1) per tutti i 3375 codoni IUPAC: come primo codone, interno, fermandosi allo stop, annotazione | 3375 |
| `<set>/translation/sequences.tsv` | le stesse funzioni sulle sequenze del FASTA in frame 1 | una per sequenza |
| `orf_cases/dna2pep_none.tab`, `dna2pep_strict.tab`, `dna2pep_any.tab` | dna2pep sui casi limite di `fasta/orf_cases.fasta` (pareggi, filamento inverso, IUPAC, U, minuscole, sequenze scartate) | una per sequenza accettata |
| `subset/example_subset.fasta` | `faSomeRecords` di `example` con la lista `fasta/example_subset_ids.txt` (id fuori ordine, uno ripetuto, uno inesistente) | 30 sequenze |
| `SHA256SUMS` | checksum di input e output | — |

`<set>` = `trial` (110 sequenze) ed `example` (250 sequenze), da `trial_transcriptome.fasta` ed
`example_transcriptome.fasta`.

## Come sono stati prodotti

```bash
tools/legacy-runner/run.sh build          # immagine annosynf/legacy-runner:2.0.1
tools/legacy-runner/golden-ports.sh       # scrive qui e aggiorna SHA256SUMS
(cd tests/Fixtures && sha256sum -c golden/SHA256SUMS)
```

Per ogni FASTA il container replica quello che fa Annocript (§A.8 di CLAUDE.md):

1. `cleanHeader`: ogni header ridotto al primo token (`split(" ", $row)` di Perl);
2. dna2pep: `dna2pep.py -O tab -o <none|strict|any> -r all cleanh.fasta > dna2pep_<mode>.tab`;
3. CPC2: `python CPC2.py -i cleanh.fasta -o cpc2.tsv` (solo strand `+`, senza `-r`);
4. `cpc2_scaled.svm`: le colonne 3–6 di `cpc2.tsv` scritte come `999 1:<peptide_length> 2:<Fickett> 3:<pI> 4:<integrity>`
   (lo stesso vettore che CPC2 costruisce) e passate a `svm-scale -r data/cpc2.range` di libsvm 3.18.
   CPC2 cancella i propri file intermedi: questo è l'input esatto di `svm-predict`, per localizzare una
   differenza tra feature, scaling e predizione;
5. composizione: la funzione originale `LIB::output_and_stats::extractPercentages` caricata da `/legacy`,
   con `totSeqs` = `grep -c '>'` come in Annocript (step 1.4);
6. slice: la funzione originale `LIB::fasta_management::create_slices`; riscrittura con `Bio::SeqIO`;
   `faSomeRecords` del legacy (`USEFUL/faSomeRecords`, con `libpng12` di Debian 8 nel container) (step 1.5).

La generazione è deterministica: tre esecuzioni consecutive hanno dato checksum identici (Python 2 non
randomizza l'hash, quindi anche l'ordine di visita dei frame di dna2pep, che decide i pareggi, è stabile).

## Versioni

| Componente | Versione / checksum |
|---|---|
| Immagine | `annosynf/legacy-runner:2.0.1`, `sha256:2cccbf289692dea37f24a70df6500d89de0a88ba6f1e4374f5b341cbfd5fd032` (base `python:2.7.18-slim-buster`) |
| Python / numpy / Biopython | 2.7.18 / 1.16.6 / 1.76 |
| dna2pep | 1.1, `dna2pep-1.1.tar.gz` sha256 `62ee2217b086163b7f413f29835e4a972084c0d3b438a33176fb6adf789ece21` |
| CPC2 | beta, `CPC2-beta.tar.gz` sha256 `1df1ef723159055d26ff41de8f9ac84df8bcb097eea264cb4b1d1c53d9aa8bcf` |
| libsvm | 3.18 (dal tarball di CPC2) |
| Modello | `cpc2.model` sha256 `1b97ab1de1d30936f767b4484f9ef0a06a126d4f7728ffcd52d82207ef8175fb`, `cpc2.range` sha256 `68edb1808e3e11f6faa058fc9cb36ee9ad9e7b63f1e06312fe9eeea3a93b2776` (identici a `resources/cpc2/`) |

## Formati

**dna2pep** (`-O tab`), 4 campi separati da TAB:

```
<id>_rframe<±1..3>_ORF  <peptide>  <annotazione per codone: * M m .>  /orf_mode="<mode>"; /dna="<DNA dell'ORF>";
```

Il frame è quello di dna2pep (`1,2,3` sulla sequenza, `-1,-2,-3` sul reverse complement); Annocript ne ricava
strand = segno e frame = `|f| − 1`.

**CPC2** (TAB, con intestazione):

```
#ID  transcript_length  peptide_length  Fickett_score  pI  ORF_integrity  coding_probability  label
```

I numeri sono scritti con `str()` di Python 2 (12 cifre significative per i float); `coding_probability` è
stampata da `svm-predict` con `%g` (6 cifre significative). Il port (step 3.6) riproduce l'output byte per
byte; i test confrontano byte per byte tutte le colonne tranne `coding_probability`, con tolleranza 1e-6:
dipende da exp() della libm, che tra versioni di glibc differisce nell'ultimo bit (step 3.7). Attenzione per il port: CPC2 passa a `svm-scale` i valori **già convertiti con `str()`**
(pI arrotondato a 12 cifre significative), quindi lo scaling va fatto sullo stesso arrotondamento.

## Riepilogo dei dati

| Set | dna2pep (per modalità) | CPC2 |
|---|---|---|
| trial | 110 righe, nessun "NO ORF FOUND" | 4 coding, 106 noncoding; `ORF_integrity` −1: 10 |
| example | 250 righe, nessun "NO ORF FOUND" | 121 coding, 129 noncoding; `ORF_integrity` −1: 18 |

Caso limite: `Transcript_135` di `example` ha `coding_probability` stampata `0.5` ma etichetta `coding` (il
valore reale è appena sopra 0.5 e `%g` lo arrotonda). In ogni vettore di `cpc2_scaled.svm` ci sono tutte e 4
le feature: `svm-scale` omette quelle scalate a 0, ma qui non capita.

## Test

`tests/Golden/GoldenFixturesTest` (suite `golden`, `composer test:golden`) verifica checksum, ordine e
invarianti di questi file; gli helper di lettura e confronto sono in `tests/Support/Golden/`.
