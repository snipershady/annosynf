# TODO — porting di Annocript in PHP ≥ 8.4

Roadmap operativa del porting. Riferimenti: `CLAUDE.md` (Parte A = legacy, Parte B = target, §B.13 =
milestone). Legenda: `[ ]` da fare, `[~]` in corso, `[x]` fatto, `[?]` decisione dell'utente.

Regole valide per **ogni** step:

- TDD: prima il test (fixture minima ricavata dai file reali), poi il codice; `composer qa` verde
  (Rector → php-cs-fixer → PHPStan max → PHPUnit con coverage 100% → test del browser).
- La logica pura sta in `src/Domain/*` (nessuna dipendenza da Symfony/Doctrine/IO), i parser lavorano su
  `iterable<string>` e restituiscono generatori; i side effect stanno dietro porte (`src/Application/Port`).
- Ogni step chiude con: test, toolchain verde, riga in `CHANGELOG.md` (quando esisterà) e spunta qui.
- Le correzioni dei bug legacy (§A.12) si fanno subito, e la differenza attesa va scritta nel golden test.
- Commit solo su richiesta.

---

## Dipendenze da eliminare (escluso NCBI BLAST+)

| Dipendenza legacy | Dove si usa | Sostituzione PHP | Step |
|---|---|---|---|
| Perl 5 + `Getopt::Long`, `Data::Dumper`, `Storable`, `Term::ReadKey`, `Error`, `List::MoreUtils` | tutto il codice, menu `--utils` | PHP 8.4, comandi `annosynf:*` | tutti |
| `Bio::SeqIO` (BioPerl) | lettura FASTA, alfabeto | `FastaReader`, `AlphabetDetector` | 1.1, 1.3 |
| `Bio::SearchIO` (BioPerl) | parsing output BLAST testuale | outfmt 7 + `TabularBlastParser` | 7.2 |
| `Bio::DB::SeqFeature::Store` + DB MySQL `gffDB_*` | sequenze e domini in fase 3 | tabelle `transcript*`, `blast_hsp` | 8.1 |
| dna2pep 1.1 (Python 2: `dna2pep.py`, `mod_translate.py`, `ncbi_genetic_codes.py`) | ORF più lungo | `OrfFinder` | 2.x |
| CPC2-beta (Python 2, numpy, Biopython `Seq`/`ProtParam`, binari libsvm 3.18 `svm-scale`/`svm-predict`) | potenziale codificante | `Cpc2Predictor` + libsvm in PHP | 3.x |
| UCSC `faSomeRecords` | sottoinsiemi FASTA per organismo | `FastaSubsetWriter` | 1.5 |
| `fgrep`, `grep -c`, `sed`, `cut`, `sort`, `uniq`, `cat`, `wc` (≈ 250 chiamate shell) | estrazioni da file enormi | parser in streaming | 1.1, 4.x |
| `gunzip`, `tar`, `IO::Uncompress::*`, `IO::Compress::Gzip`, `Archive::Extract` | decompressione sorgenti | `compress.zlib://` + estrattore tar in streaming | 5.3 |
| `LWP::*`, `HTTP::*`, `Net::FTP`, `File::Fetch`, `curl --head` | download e check URL | Symfony HttpClient su HTTPS (Range, retry, mirror) | 5.1 |
| `DBI`/`DBD::mysql`, client `mysql`, `mysqlimport` | DDL, query, bulk load | Doctrine DBAL/ORM/Migrations, `LOAD DATA LOCAL INFILE` via PDO | 6.x |
| `Parallel::ForkManager`, `nohup … &` | RPS parallelo, esecuzione in background | Messenger (chunk) + Semaphore/Lock su Redis | 7.4 |
| R (`tables_2_barplot.R`, `tables_2_pie_chart.R`, `ann_seqs_stats.R`) | grafici JPEG | statistiche in SQL/PHP + Chart.js (Symfony UX) | 9.x |
| `CGI.pm` | 3 pagine HTML statiche | Twig | 9.3 |
| MySQL `bigMemory`/hash in RAM | import mapping | import set-based (staging + `INSERT … SELECT`) | 6.3 |

Restano dipendenze runtime solo PHP (estensioni `pdo_mysql`, `redis`, `zlib`, `intl`, `mbstring`, `pcntl`)
e i binari BLAST+ (`blastx blastp blastn tblastn rpstblastn rpsblast makeblastdb blastdbcmd`).

---

## Fase 0 — Prerequisiti (prima di tradurre)

Il legacy non è eseguibile su questa macchina (mancano Python 2, BioPerl, R): senza il suo output non si
può dimostrare la parità (§B.12).

- [x] **0.1 Container `legacy-runner`** (`tools/legacy-runner/`, mai usato a runtime; resta nel repo fino
  alla fase 11, poi si elimina). Immagine `annosynf/legacy-runner:2.0.1` (~690 MB) su
  `python:2.7.18-slim-buster` (apt da `archive.debian.org`): Python 2.7.18, numpy 1.16.6, **Biopython 1.76**
  (compilato in uno stage di build), Perl 5.28 + BioPerl 1.7.2 e tutti i moduli usati da `LIB/*.pm`, R 3.5.2,
  client MySQL, libsvm 3.18 compilata dal tarball di CPC2, dna2pep 1.1, `faSomeRecords`. Il legacy entra
  come named build context in sola lettura (nulla copiato nel repo); a runtime: legacy su `/legacy:ro`,
  progetto su `/in:ro`, output su `/out` (`var/legacy-runner/`, o `LEGACY_RUNNER_OUT`), rete disattivata,
  uid dell'utente host. Comandi: `tools/legacy-runner/run.sh build|versions|dna2pep <fasta> <mode> <out>|cpc2 <fasta> <out>|shell`;
  `dna2pep` e `cpc2` replicano `cleanHeader` e gli argomenti con cui li lancia Annocript; `cpc2` salva
  anche `<out>.scaled` (vettori dopo `svm-scale`). Smoke test su `trial`: 110 righe per CPC2 e per ciascuna
  modalità di dna2pep. shellcheck pulito; hadolint solo avvisi attesi (motivati nel Dockerfile).
  Il dominio MySQL e la pipeline completa (per 8.3) si aggiungeranno qui con un servizio `mysql` dedicato.
- [x] **0.2 Golden dei port nativi**: `tools/legacy-runner/golden-ports.sh` scrive in
  `tests/Fixtures/golden/{trial,example}/` `dna2pep_{none,strict,any}.tab`, `cpc2.tsv` e `cpc2_scaled.svm`
  (input esatto di `svm-predict`), più `SHA256SUMS`; `README.md` con comandi, versioni, checksum, formati.
  Generazione deterministica (3 esecuzioni identiche). trial: 110 righe per file, CPC2 4 coding / 106
  noncoding; example: 250 righe, 121 coding / 129 noncoding; nessun "NO ORF FOUND". ~2 MB in totale.
  Nota per la fase 3: CPC2 scala i valori già passati per `str()` (12 cifre significative).
- [x] **0.3 Guardia architetturale**: `deptrac/deptrac` 4.7 (`deptrac.yaml`, `composer deptrac`, dentro
  `composer qa` dopo PHPStan, con `--fail-on-uncovered`). Strati: `Domain` → solo PHP core; `Application` →
  `Domain`, PHP core, `Psr\*`; `Adapter` (tutto il resto di `src/`) → tutto; le classi esterne ricadono in
  `PhpCore`/`Psr`/`Vendor`, quindi nessuna dipendenza sfugge. Verificato con classi sonda: 4 violazioni
  attese su 4 (Domain → Symfony, Entity, PSR; Application → Doctrine), nessun falso positivo.
- [x] **0.4 Struttura dei test**: suite `golden` (`tests/Golden`, `composer test:golden`, inclusa in
  `composer qa`). Helper in `tests/Support/Golden/`: `GoldenFixtures` (percorsi, righe in streaming,
  `SHA256SUMS`, id delle sequenze come il `cleanHeader` di Annocript), lettori dei formati legacy
  `Dna2PepRow` (con strand/frame/lunghezza come li ricava Annocript), `Cpc2Row`, `ScaledVector`, e
  `GoldenComparison` (raccoglie tutte le differenze, tolleranza numerica, eccezioni dichiarate con motivo che
  devono servire davvero). `tests/Golden/GoldenFixturesTest` verifica checksum, una riga per sequenza
  nell'ordine del FASTA e gli invarianti dei formati; gli helper hanno test unitari, casi malformati inclusi.
- [x] **0.5 Licenza del progetto**: **GPL-3.0-or-later** (`composer.json`, `LICENSE`, `NOTICE.md`), la più
  permissiva compatibile con Annocript (GPL-3.0-or-later) e con dna2pep (GPL-2.0-or-later).
- [?] **0.6 Licenza del modello CPC2** (`cpc2.model`/`cpc2.range`, §B.8.2): da verificare e citare in
  `NOTICE.md` prima di distribuire il software.

## Fase 1 — Dominio sequenze (M1) · elimina `Bio::SeqIO`, `faSomeRecords`, `grep -c`, `cleanHeader`

- [x] **1.1 `FastaRecord` + `FastaReader`** (`src/Domain/Sequence`): streaming su `iterable<string>` (un
  record pronto appena arriva l'header successivo), CRLF e righe vuote ignorate, spazi/tab tolti dalla
  sequenza, maiuscole invariate; `FastaRecord` conserva header grezzo, numero di riga e riga di sequenza più
  lunga (servono al validatore 1.2); `id()`/`description()` come Bio::SeqIO. Scelte diverse da BioPerl: testo
  prima del primo header → `InvalidFastaException` con numero di riga; `>` dentro una riga di sequenza non
  cancellato (lo segnalerà il validatore). Adapter `Infrastructure\Storage\TextFileReader`: righe in streaming
  senza limiti di lunghezza, gzip riconosciuto dal contenuto (`compress.zlib://`), `FileNotReadableException`.
  Golden `FastaReaderParityTest`: id, ordine e lunghezze identici al `transcript_length` di CPC2 legacy
  (360 sequenze).
- [x] **1.2 `FastaValidator`** (§B.6): errori bloccanti (file vuoto, testo prima del primo header, header
  vuoto, spazio dopo `>`, nome duplicato, sequenza vuota, caratteri non IUPAC) e avvisi (header o riga di
  sequenza > 80 caratteri, N > 10% sui nucleotidi, soglia configurabile) in `FastaValidationReport`: per
  ogni `FastaIssue` conteggio e prime 20 posizioni (riga + nome), più i caratteri non ammessi trovati. Enum
  `Alphabet` (dna/rna/protein) con gli insiemi IUPAC del legacy, confronto case-insensitive; il validatore
  riceve l'alfabeto (lo rileverà 1.3). Nel legacy erano tutti avvisi; ora gli errori bloccano l'analisi.
  Golden: `trial` ed `example` senza errori; unico avviso nuovo, N alte in `Transcript_143` (13%) e
  `Transcript_223` (22%) di `example`.
- [x] **1.3 `AlphabetDetector`**: euristica **reale** di BioPerl 1.7.2 (letta nel codice e verificata nel
  container; la spec diceva "≥ 85% di ACGTUN, U senza T ⇒ rna" ed è stata corretta in CLAUDE.md §A.6.1/§B.6):
  tolti `-.?`; lettera tra `EFIJLOPQXZ` ⇒ proteina; più del 70% in `ATUGCNWSKM` ⇒ nucleotidico (RNA se c'è una
  `U`), altrimenti proteina; senza lettere ⇒ sconosciuto. Applicata a ogni sequenza: alfabeto del file = maggioranza
  (pareggio ai nucleotidi, RNA se prevale tra i nucleotidi); `AlphabetDetection` con conteggi per alfabeto,
  `isMixed()` (nucleotidi + proteine), prime 20 sequenze della famiglia in minoranza e alfabeto della prima
  sequenza (quello del legacy). 25 casi di test con l'esito calcolato da BioPerl stesso; golden: `trial` ed
  `example` interamente DNA, come li ha trattati il legacy.
- [x] **1.4 `SequenceComposition`** (§A.10): A/C/G/T/N case-insensitive in % di `A+C+G+T+N`, GC sulla
  lunghezza, errori = altri caratteri (U compresa, come nel legacy che calcolava solo per il DNA);
  `CompositionSummarizer` → `CompositionSummary` (medie delle percentuali per sequenza, GC complessivo = G+C
  totali / basi totali), in un passaggio. Correzioni: percentuali `null` se una sequenza non ha A/C/G/T/N (il
  legacy moriva per divisione per zero) e medie solo sulle sequenze in cui sono definite. Golden prodotti con
  la funzione originale `extractPercentages` eseguita nel container (nuovo comando `composition` di
  legacy-runner, `atgc_percentages.tsv[.averages]`): parità a 1e-9 su tutte le 360 sequenze e sulle medie.
- [x] **1.5 `FastaFormatter`, `FastaSlicer`, `FastaSubset`** (+ `Infrastructure\Storage\FastaFileWriter`).
  `FastaFormatter`: 60 colonne, header `>id descrizione` come Bio::SeqIO o solo `>id` (`FastaHeaderStyle`),
  riga vuota per sequenza vuota. `FastaSlicer`: regola di `create_slices` (soglia `int(basi/n) − 1`, nuova slice
  quando le basi scritte la superano; possono uscire più o meno di n slice), in streaming; correzione: una slice
  non si chiude mai vuota (il legacy, con meno basi che slice, lasciava `slice_1.fasta` vuoto). `FastaSubset`:
  come `faSomeRecords` (id esatto, ordine del FASTA, duplicati/assenti nella lista ininfluenti, `exclude`).
  `FastaFileWriter`: FASTA e slice `<name>_<n>.fasta` scritti in modo atomico (temporaneo + rename, nessun file
  troncato se il processo si interrompe). legacy-runner: comandi `slices`, `rewrite`, `fasomerecords`; il binario
  `faSomeRecords` non partiva (mancava `libpng12`, ora da Debian 8 con sha256) e `versions` ora lo esegue davvero.
  Golden: formato e sottoinsieme identici **byte per byte** a Bio::SeqIO e faSomeRecords; slice identiche per
  trial/example con 3 e 10 slice.
- [x] **1.6 Comando `annosynf:fasta:check <file>`** (FASTA in chiaro o gzip): caso d'uso
  `Application\Sequence\CheckFasta` (porta `Application\Port\TextLines`, implementata da `TextFileReader`) →
  `FastaCheck`: alfabeto rilevato su tutto il file (DNA se non rilevabile), validazione con quell'alfabeto,
  composizione media solo per file nucleotidici leggibili; letture in streaming. Il comando mostra riepilogo,
  alfabeto (con l'avviso se Annocript, guardando solo la prima sequenza, avrebbe scelto diversamente, e se il
  file è misto), composizione, errori e avvisi con le prime 5 posizioni, caratteri non ammessi; esce con
  errore se ci sono problemi bloccanti o il file non è leggibile. Su `trial`: 110 sequenze, DNA, GC
  complessivo 40,84% come le statistiche legacy.

## Fase 2 — `OrfFinder`, port di dna2pep (M1) · elimina dna2pep / Python

Riferimento riga per riga: `docs/legacy/ports/{dna2pep,mod_translate,ncbi_genetic_codes}.py`, §A.8.1.

- [x] **2.1 `GeneticCode`** VO generico, costruito dalle stringhe NCBI `ncbieaa`/`sncbieaa` come
  `mod_translate.parseNcbiTable` (aa interno e aa come primo codone, ordine TCAG); `standard()` = tabella 1
  (default di dna2pep, `-m 1`); `isStop()`, `mark()` → enum `CodonMark` (`*` stop, `M` start stretto ATG, `m`
  start solo in prima posizione TTG/CTG, `.`). Traduce solo codoni esatti maiuscoli con T: normalizzazione e
  codoni degenerati IUPAC sono dello step 2.2. Aggiungere un altro codice NCBI = una chiamata a `fromNcbi()`.
  Golden `genetic_code/table_1.tsv` scritto da `mod_translate` nel container (comando `geneticcode`):
  64 codoni identici per aa interno, primo codone e annotazione.
- [x] **2.2 `Translator`**: port di `mod_translate` (translate, annotate, decode, condense). Normalizzazione
  (maiuscole, `U→T`); codoni IUPAC degenerati espansi ricorsivamente dalla prima posizione degenerata
  nell'ordine IUPAC di mod_translate (es. `M = CA`), traduzioni distinte → aa, `{D,N}`→`B`, `{E,Q}`→`Z`,
  altrimenti `X`; primo codone come start se richiesto (anche se non produce aa, il codone successivo non è più
  "primo"); senza lettura attraverso gli stop ci si ferma quando la **prima** traduzione possibile è `*` (es.
  `YAA` non ferma: CAA viene prima di TAA); annotazione `*` se **una** traduzione è stop. Triplette incomplete
  (e con caratteri non IUPAC) saltate, anche nell'annotazione; cache per codone. Golden scritti da
  mod_translate nel container (comando `translation`): tutti i 3375 codoni IUPAC × 4 modalità e le 360
  sequenze di trial/example in frame 1 × 4, identici.
  **Per 2.3**: dna2pep scarta l'intera sequenza (nessuna riga di output) se contiene un carattere fuori da
  `ATUGCYRSWKMBDHVN` (case-insensitive), es. `-` o `X`, che il nostro validatore ammette.
- [x] **2.3 Ricerca ORF** (`OrfFinder`, `Orf`, `ReadingFrame`, `OrfMode`): port del ciclo di dna2pep
  (`-r all`): annotazione per frame, segmenti dopo uno stop (`none`) o al primo start (`strict` M, `any` M/m)
  fino al primo `*` o a fine frame, segmento **strettamente** più lungo (vale anche un segmento vuoto), peptide
  ritradotto con il primo codone come start, DNA con le maiuscole dell'input, coordinate in nt sul filamento
  diretto. Ordine reale dei frame (dict Python 2, verificato): **`1, 3, 2, -1, -3, -2`** → nessuna differenza
  sui pareggi (la spec ipotizzava `1,2,3,-1,-2,-3` con differenze ammesse: corretta in CLAUDE.md).
  `accepts()`: dna2pep scarta l'intera sequenza con caratteri fuori da `ATUGCYRSWKMBDHVN`. Correzione: il
  complemento di dna2pep scambiava S e W (§A.12.19). Nuovo fixture `orf_cases.fasta` (19 casi limite) con
  golden di dna2pep; i test unitari usano solo esiti verificati con dna2pep (le prime aspettative scritte a
  mano ignoravano gli ORF sul filamento inverso). Parità campo per campo su trial/example/orf_cases × 3
  modalità: identica, salvo la `S` del caso `degenerate_codons` in `none` (dichiarata nel test).
- [x] **2.4 Risultato** (`length_aa`, strand, frame 0..2, coordinate nt, peptide ritradotto con il primo
  codone come start) + formatter della riga legacy `<id>_rframe<±f>_ORF…` per il confronto.
  `Orf` espone `length()`, `strand()`, `frameOffset()` (0..2, colonna LongOrfFrame), `start`/`end` sul
  filamento diretto e il peptide ritradotto. `Dna2PepLineFormatter` scrive la riga di dna2pepOut; senza ORF
  ("NO ORF FOUND") la riga c'è comunque, sul frame 1 con campi vuoti.
- [x] **2.5 Golden** contro 0.2 (trial + example + orf_cases, 3 modalità): `OrfFinderParityTest` confronta
  campi, lunghezza/strand/frame come li ricava Annocript, la riga formattata e l'intero file byte per byte;
  le coordinate devono delimitare il DNA dell'ORF. Nessuna differenza nei pareggi (ordine dei frame di
  dna2pep); unica differenza ammessa la correzione S↔W (`orf_cases none degenerate_codons`), dove il
  confronto dell'intero file è sostituito da quello riga per riga.

## Fase 3 — `Cpc2Predictor`, port di CPC2 (M1) · elimina Python 2, numpy, Biopython, libsvm

Riferimento: `docs/legacy/ports/CPC2.py`, `resources/cpc2/`, §A.8.2. Sviluppare confrontando i file
intermedi di 0.2.

- [x] **3.1 `CpcOrfFinder`**: solo strand `+`, `ATG` → stop incluso oppure fine sequenza (integrity −1),
  il più lungo, pareggio → il più a monte. `src/Domain/CodingPotential/{CpcOrfFinder,CpcOrf,OrfIntegrity}`.
  Dettagli di FindCDS verificati nel container: senza stop l'ORF finisce all'ultimo codone completo; la
  ricerca riprende dopo la fine dell'ORF (un ATG interno non apre un altro ORF); il pareggio va al più a monte
  anche se in un frame successivo; senza ATG nessun ORF (CPC2 scrive poi integrity −1). Nuovo comando
  `cpc2orf` del legacy-runner (FindCDS + traduzione Biopython del modulo originale), fixture
  `cpc2_orf_cases.fasta` (19 casi) e golden `<set>/cpc2_orf.tsv`, `cpc2_cases/{cpc2_orf.tsv,cpc2.tsv,cpc2_scaled.svm}`;
  rigenerando tutti i golden quelli esistenti sono rimasti identici. Parità su trial, example e casi limite.
- [x] **3.2 Lunghezza del peptide** come la traduzione Biopython (incluso il `*` finale).
  `Cpc2Translator` porta `Seq.translate()` di Biopython 1.76 (tabella ambigua del codice 1): un carattere per
  codone completo; espansione IUPAC (X = qualunque base): solo stop → `*`, stop e amminoacidi → `X`, un solo
  amminoacido → quello, altrimenti B (D/N), Z (E/Q), J (I/L) o X. peptide_length = numero di codoni dell'ORF.
  Golden `cpc2_cases/codons.tsv` (comando `cpc2codons`: 4913 codoni su `ACGTRYMKWSBDHVNX-`) e peptidi di
  `cpc2_orf.tsv`: identici. **Correzione** (§A.12.20): dove Biopython solleva TranslationError (codoni con `-`,
  o con X quando potrebbero essere uno stop; 1170 codoni) CPC2 si interrompe senza output per l'intero file;
  il port scrive `X`. Le lettere servono al punto isoelettrico (3.4).
- [x] **3.3 `FickettScore`**: tabelle di posizione/contenuto copiate da `CPC2.py` come costanti citate.
  Stesse operazioni in virgola mobile e stesso ordine di somma di CPC2; le lettere diverse da ACGT contano solo
  nella lunghezza; sotto le 2 basi 0 (CPC2 stampa `0`, intero). Golden identico, confrontato con le 12 cifre
  significative di Python 2, su trial, example e casi limite (aggiunti a `cpc2_orf_cases.fasta`: una base,
  molte N, omopolimero, codoni GC; ora 23 casi).
- [x] **3.4 `IsoelectricPoint`**: algoritmo di `Bio.SeqUtils.IsoelectricPoint` di Biopython 1.76 (pK,
  pK terminali dipendenti dal residuo, bisezione con la stessa tolleranza); peptide privato di
  `X|B|Z|J|U` e `*`; 0.0 e integrity −1 se vuoto. Somme delle cariche nell'ordine dei dict di Python 2 (H, K,
  R, N-terminale; Y, C-terminale, C, E, D). Golden esatto (repr) su 80 peptidi (`peptides/isoelectric_cases.tsv`,
  comando `isoelectric`) e sui peptidi degli ORF di CPC2. Nel contesto di CPC2 il peptide inizia sempre con M,
  quindi il pK N-terminale è sempre 7.0.
- [x] **3.5 libsvm in PHP**: `LibsvmModel` (parser di `cpc2.model`: `svm_type`, `gamma`, `rho`, `label`,
  `probA`/`probB`, vettori di supporto), `LibsvmScaler` (`cpc2.range`, come `svm-scale -r`),
  `LibsvmPredictor` (kernel RBF, decision value, `sigmoid_predict` di Platt, ordine delle label).
  Anche con 2 classi libsvm passa per `multiclass_probability` (iterativo, si ferma a errore < 0.0025):
  portato tale e quale. Golden: modello minimo `libsvm/tiny.model` con `svm-predict` reale (comando
  `svmpredict`), scaling e probabilità sui vettori di `cpc2_scaled.svm`.
- [x] **3.6 Composizione `Cpc2Predictor`** + **golden**: `peptide_length`, `ORF_integrity`, `label`
  identici; Fickett, pI, `coding_probability` con tolleranza 1e-6. Riprodotti gli arrotondamenti del passaggio
  su file (12 cifre prima di svm-scale, `%g` dopo), il Fickett intero `0` sotto le 2 basi, `single_base`
  coding, `Transcript_135` coding. `Cpc2OutputFormatter` scrive l'output di CPC2 (`%g` di C e `str()` di
  Python in `LegacyNumberFormat`). Porta `CodingPotentialPredictor` + `NativeCpc2Predictor` (modello da
  `resources/cpc2`, caricato una volta), caso d'uso `PredictCodingPotential`, comando `annosynf:cpc2:predict`.
  Output identico byte per byte su trial, example e casi limite; i test confrontano byte per byte tutte le
  colonne tranne `coding_probability` (tolleranza 1e-6, vedi 3.7).
- [x] **3.7 Benchmark** su `example` (250 sequenze) e su un FASTA grande sintetico: tempo e memoria
  annotati qui (decide la dimensione dei chunk della fase 7). PHP 8.5 senza Xdebug, 1 core:

  | Input | Sequenze | Basi | Tempo | ms/seq | Memoria di picco |
  |---|---|---|---|---|---|
  | example | 250 | 0.6 Mb | 0.12 s | 0.46 | 8 MB |
  | sintetico (200–3000 nt) | 20 000 | 32 Mb | 8.7 s | 0.44 | 6 MB |
  | 3 sequenze da 0.1, 1 e 5 Mb | 3 | 6.1 Mb | 0.22 s | — | 27 MB |

  Caricamento del modello 5 ms. CPC2 originale nel container sullo stesso sintetico: 8.7 s; il tempo del port
  è dominato dal kernel (3111 vettori di supporto per sequenza), costante per sequenza: ~0.45 ms anche su
  milioni di trascritti (≈ 7.5 min per 1 M sequenze su un core). Memoria costante in streaming; per sequenza
  ~5× la sua lunghezza (il Fickett conta le fasi a blocchi di 48 kb, prima ~23×). Conseguenza per la fase 7:
  il passo CPC2 non richiede chunk propri; si possono usare gli stessi chunk di trascritti di dna2pep
  (anche 10 000–50 000 sequenze per messaggio restano sotto i 30 s).
  Sul sintetico l'output coincide con CPC2 salvo 1 riga su 20 000: ultima cifra di `coding_probability`
  (0.0730131 contro 0.0730132). Causa verificata nel container: exp() della libm di glibc 2.28 e 2.41
  differisce nell'ultimo bit e, con 3111 termini e coefficienti fino a 16284, sposta l'ottava cifra; non è
  replicabile né rilevante (tolleranza 1e-6 di §B.8.2).

## Fase 4 — Parser dei dati di riferimento (M2) · elimina `fgrep`/`sed`/`cut`/`sort`/`uniq`

Fixture minime in `tests/Fixtures/reference/`, estratte dai file reali (con i casi patologici).

- [x] **4.1 `ReleaseNoteParser`** (`reldate.txt`, `uniref*.release_note`): `src/Domain/Reference`
  (`ReleaseNoteParser`, `ReleaseNote` con versione, data, variante UniRef e numero di cluster, enum
  `ReleaseType`/`UnirefVariant`). Golden di `getDBVersion` (comando `dbversion` del legacy-runner) sui file reali
  di UniProt 2026_03 e su casi limite; correzioni dichiarate in §A.12.21 (versione validata `YYYY_MM`, riga
  cercata in tutto il file, data UniRef facoltativa, due versioni = errore).
- [x] **4.2 `UniProtHeaderParser`** (organismo completo, `OX` salvato: corregge §A.12.1) e
  **`UniRefHeaderParser`** (`Tax=` senza `TaxID=`: §A.12.2): `UniProtHeader`/`UniRefHeader`, `ProteinSource`,
  `HeaderTags` (tag comuni), metodo `headers()` in streaming che salta le righe di sequenza (sostituisce `fgrep`).
  Golden del parsing originale di `createUniprotKBDB`/`createUnirefDB` (comando `headers` del legacy-runner) su
  header reali 2026_03 e nel formato pre-2017/2018. Correzioni in più rispetto alla spec: gene name intero
  anche con spazi (`GN=MGF 110-1L`), nessun ID/descrizione ripetuti dall'header precedente.
- [x] **4.3 `OboParser`**: term + `alt_id`, obsoleti marcati, ultima stanza non persa, `[Typedef]` ignorato
  (§A.12.3). `GoTerm`, `GoNamespace`, `dataVersion()` per `source_versions.go`. Golden della goTable di
  `createGOTable` (comando `goterms`; il legacy-runner ha ora `DbCreatorHarness.pm` per eseguire le sub di
  `db_creator.pm`). Sul `go-basic.obo` completo (2026-07-26, 48 340 term, 0,75 s, 5 MB) le sole differenze dal
  legacy sono quelle dichiarate: 7 nomi con `": "` troncati dal legacy e l'ultimo term perso.
- [x] **4.4 `EnzymeDatParser`** (EC, descrizione ripulita, accession dai `DR`, versione dal `CC`):
  `EnzymeEntry` con `description()` (testo di ENZYME) e `legacyDescription()` (ripulita come la enzymeTable),
  `release()`. Golden di `createEnzymeTable` (comando `enzymes`, con il mapping accession → enzimi). Sul file
  completo del 02-Sep-2026 (8472 voci, 239 440 coppie accession-enzima, 0,7 s) identico al legacy; unica
  correzione, l'ultima voce senza DR (§A.12.22). Corretto §A.7.6: la enzymeTable contiene anche le voci senza DR.
- [ ] **4.5 `PathwayTxtParser`** (livelli con trim, niente piè di pagina come pathway: §A.12.5).
- [ ] **4.6 `CddIdParser`** e **`Pfam2GoParser`** (nessun GO sul dominio precedente: §A.12.4).
- [ ] **4.7 `IdMappingParser`** (colonne GO/UniRef trovate dall'header o dai valori) e **`SpeclistParser`**.

## Fase 5 — Download e decompressione (M2) · elimina LWP, Net::FTP, File::Fetch, curl, gunzip, tar

- [ ] **5.1 Porta `SourceDownloader` + `HttpSourceDownloader`**: HttpClient in streaming su file, ripresa
  con `Range`, retry, mirror UniProt/EBI/Expasy, sha256, file già presente non riscaricato.
- [ ] **5.2 Verifica degli URL HTTPS** di §B.10 (HEAD reale) e correzione di CLAUDE.md dove sono cambiati.
- [ ] **5.3 Decompressione**: gz in streaming (`compress.zlib://`); per `Cdd_LE.tar.gz` un estrattore tar
  in streaming (header da 512 byte) invece di `PharData`, che su archivi da GB non è adatto. Da
  verificare con un archivio reale.

## Fase 6 — Persistenza e import delle release (M3) · elimina DBI, client mysql, mysqlimport

- [ ] **6.1 Migration** di `reference_release`, `reference_source_file`, `reference_task`,
  `reference_blast_db`, `organism_set*` (ORM) e delle tabelle `ref_*` partizionate (SQL esplicito).
- [ ] **6.2 `MysqlLoadDataBulkLoader`**: `LOAD DATA LOCAL INFILE` via `Pdo\Mysql` limitato a `var/tmp/bulk`.
  Prerequisito: `local_infile=ON` sul server (oggi è `0`).
- [?] **Descrizione in `ref_enzyme`** (da decidere a 6.3): testo di ENZYME (`EnzymeEntry::description()`) o
  forma ripulita del legacy; l'export compatibile ha bisogno della seconda (le righe `DE` unite da due spazi).
- [ ] **6.3 Task di import set-based** (staging + `INSERT … SELECT … JOIN`), idempotenti, con workflow
  Release e Messenger; lock per release.
- [ ] **6.4 Indicizzazione** con `makeblastdb`/`blastdbcmd` (unica dipendenza esterna rimasta) dietro la
  porta `BlastDbBuilder`, sottoinsiemi per organismo con `FastaSubsetWriter`.
- [ ] **6.5 Misure** su una release UniRef reale (tempo, spazio) e decisione sull'"annotazione pigra"
  (§B.4.2), annotate in CLAUDE.md.

## Fase 7 — Esecuzione e parsing BLAST (M4) · elimina `Bio::SearchIO`, `Parallel::ForkManager`, `nohup`

- [ ] **7.1 `BlastCommandBuilder`**: outfmt 7 con le colonne di §B.7, mappatura dei parametri X/N/RPS
  (`num_alignments` → `max_target_seqs`), argv array.
- [ ] **7.2 `TabularBlastParser`** + `SubjectIdExtractor` + `CoverageCalculator` + `HspQualityFilter`, con
  fixture prodotte da BLAST 2.17 reale su una mini-release (DB con e senza `-parse_seqids`).
- [ ] **7.3 Coperture**: confronto con `length('query')` di BioPerl sui golden; correggere la formula e
  documentarla in §B.7 se differisce.
- [ ] **7.4 Esecuzione a chunk** via Messenger (`RunBlastChunk`/`IngestBlastChunk`), Semaphore sui job
  concorrenti, ripresa dopo kill del worker, controllo di completezza sulle righe `# Query:`.

## Fase 8 — Annotazione (M5) · elimina `Bio::DB::SeqFeature::Store`, DB `gffDB_*`, `outHash`

- [ ] **8.1 `BestHitSelector`, `LncRnaClassifier` (bitmask delle 7 condizioni), `CodingClassifier`,
  `PathwayLevels`** in `src/Domain/Annotation`, con test di tutte le combinazioni. Attenzione: Annocript
  legge la riga "NO ORF FOUND" di dna2pep come ORF di lunghezza 0 (`+`, frame 0), quindi la condizione 6
  ("ORF presente e ≤ NCORFLength") è vera anche senza ORF; `OrfFinder::find()` restituisce null in quel
  caso: decidere se replicare (parità del golden `ann_out`) o correggere e dichiararlo.
- [ ] **8.2 `BuildAnnotations`** a batch → `transcript_annotation` + junction GO/enzimi/pathway.
- [ ] **8.3 Golden `ann_out`**: legacy eseguito nel container su una mini-release ridotta; confronto cella
  per cella, con le colonne toccate dalle correzioni §A.12 elencate esplicitamente.

## Fase 9 — Statistiche e grafici (M6) · elimina R e `CGI.pm`

- [ ] **9.1 Calcolatori** di §A.10/§B.9 (GO, domini, pathway, organismi, composizione, accordo ORF/BLAST,
  mediana, N50, bucket degli istogrammi) → `analysis_summary`, `analysis_stat`.
- [ ] **9.2 Golden** dei file `*_percentages` e `closer_os_table*`.
- [ ] **9.3 Pagine Twig + Chart.js** al posto dei JPEG di R e delle 3 pagine HTML.

## Fase 10 — Export compatibili (M6)

- [ ] **10.1** TSV `ann_out` / `filt_ann_out` (separatore `]---[`), FASTA ORF/lncRNA/coding, file
  statistiche legacy, GFF3 standard; golden sui FASTA.

## Fase 11 — Ripulizia degli asset legacy (ultimo step del porting)

Da fare solo quando tutti i golden (2.5, 3.6, 8.3, 9.2, 10.1) sono verdi: da qui in poi il repository non
contiene più nulla del legacy che non serva a eseguire o testare annosynf.

- [ ] **11.1 Codice di riferimento**: eliminare `docs/legacy/ports/` (`CPC2.py`, `dna2pep.py`,
  `mod_translate.py`, `ncbi_genetic_codes.py`, README) e `docs/legacy/config/` (DDL e file di
  configurazione legacy).
- [ ] **11.2 Container**: eliminare `tools/legacy-runner/` (Dockerfile e script) e ogni riferimento in
  `composer.json`, CI e documentazione.
- [ ] **11.3 Adapter esterni**: eliminare `ExternalCpc2Predictor` (§B.8.2) e qualunque codice o test che
  invochi Python, Perl o R.
- [ ] **11.4 Golden congelati**: i FASTA di input (`tests/Fixtures/fasta/`) e gli output golden
  (`tests/Fixtures/golden/`) restano come **dati di test di regressione**, con un README che ne indica
  l'origine; nessun test deve più richiedere il container o la directory del legacy.
- [ ] **11.5 Documentazione**: in CLAUDE.md la Parte A diventa un riassunto storico, senza percorsi verso
  `/home/shady/public_html/Annocript` né regole che ne richiedano la lettura; il resto della spec
  descrive solo annosynf.
- [ ] **11.6 Verifica**: `grep -ri` su `annocript.pl`, `LIB/`, `python`, `perl`, `Rscript`, `BioPerl`,
  `legacy-runner` senza risultati fuori dai file di licenza e dal README dei golden; `composer qa` verde.
- Restano per obbligo di licenza o perché servono a runtime: `LICENSE`, `NOTICE.md` (copyright di Annocript,
  dna2pep e CPC2) e `resources/cpc2/` (modello SVM usato dal port nativo). `docs/legacy/ANNOCRIPT-LICENSE-GPL3.txt`
  è un doppione di `LICENSE` e si elimina con il resto di `docs/legacy/`.

## Dopo il porting

UI completa (M7–M8), confronti (M9), import delle analisi legacy e DIAMOND (M10), hardening (M11): vedi
§B.13 di CLAUDE.md.

---

## Prossimo passo consigliato

Fasi 0–3 completate (salvo la decisione 0.6): M1 chiusa, port nativi di dna2pep e CPC2 identici al legacy.
Fase 4 (parser dei dati di riferimento, M2) in corso: fatti 4.1–4.4, prossimo 4.5 (`PathwayTxtParser`). Le
fasi 1–3 non toccano il database e producono da sole un risultato verificabile: i port nativi identici al
legacy.
