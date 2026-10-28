# Mini-release di test

File serviti da `tests/Support/FakeSourceDownloader` al posto delle sorgenti reali, con lo stesso nome che hanno sui
server (`annosynf.sources`): il test end-to-end dell'import (`tests/Integration/Release`) crea una release, esegue tutti
i task e controlla le tabelle `ref_*`.

Sono copie dei fixture di `tests/Fixtures/reference/`, non compresse anche quando il nome finisce in `.gz` (GzipReader
legge i file non compressi così come sono). `uniprot_sprot`, `uniprot_trembl` e `uniref90` contengono gli header di
`reference/headers/` della sola sorgente, senza accession ripetute, ciascuno con una sequenza fittizia;
`Cdd_LE.tar.gz` e `rnacentral_active.fasta.gz` sono segnaposto finché l'indicizzazione (step 6.4) non li userà.

Righe aggiunte rispetto ai fixture di `reference/`, perché le associazioni abbiano qualcosa da legare: in
`idmapping_selected.tab.gz` la riga di P31946 (due GO della release più `GO:9999999`, che non c'è, e il cluster
`UniRef90_Q6GZX4`), in `enzyme.dat` la voce `9.9.9.9` (P31946, Q6GZX4 e `P00000`, assente), in `pathway.txt` il pathway
`Test pathway; mini-release.` (P31946 e Q6GZX4), in `pfam2go` l'associazione `pfam00001` → `GO:0003674`.
