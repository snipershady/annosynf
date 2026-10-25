# annosynf — note di copyright

annosynf — annotazione di trascrittomi *de novo*
Copyright (C) 2026 snipershady

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the License, or
(at your option) any later version. See `LICENSE`.

## Opere da cui deriva

annosynf è una riscrittura in PHP, modificata, delle opere seguenti.

- **Annocript 2.0.1** — Copyright (C) 2015 Francesco Musacchia. GNU GPL versione 3 o successiva.
  Algoritmi, euristiche e formati di output della pipeline.
- **dna2pep 1.1** — Copyright 2006 Rasmus Wernersson, Technical University of Denmark. GNU GPL versione 2
  o successiva. Port nativo della ricerca degli ORF (`src/Domain/Sequence`).
- **CPC2-beta** (Coding Potential Calculator 2), Center for Bioinformatics, Peking University. Port nativo
  del calcolo del potenziale codificante (`src/Domain/CodingPotential`) e modello SVM in `resources/cpc2/`.
  La licenza del modello è da verificare prima di distribuire il software.
- **LIBSVM 3.18** — Copyright (c) 2000-2014 Chih-Chung Chang e Chih-Jen Lin. Licenza BSD a 3 clausole
  (compatibile con GPL-3). Port della predizione con probabilità (`svm-scale -r`, kernel RBF, sigmoide di
  Platt, `multiclass_probability`) in `src/Domain/CodingPotential/Libsvm*`.
- **Biopython 1.76** — Copyright dei contributori di Biopython. Biopython License Agreement (permissiva,
  compatibile con GPL-3). Port del punto isoelettrico (`Bio.SeqUtils.IsoelectricPoint`, metodo di Bjellqvist)
  e della traduzione con la tabella dei codoni ambigui (`Seq.translate`) in `src/Domain/CodingPotential`.
- **CPAT 1.2.1** — Liguo Wang et al. GNU GPL versione 2 o successiva. Tabelle del punteggio di Fickett e
  ricerca dell'ORF, tramite CPC2.
