<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Riga di `cddid_all.tbl` (NCBI CDD): un modello di dominio (cdTable di Annocript).
 */
final readonly class CddDomain
{
    /**
     * @param string $accession   `cd00012`, `pfam00001`, `COG0001`, `smart00002`, `LOAD_ACT`, …
     * @param string $description può essere vuota (8732 righe nel file del 2026)
     */
    public function __construct(
        public int $pssmId,
        public string $accession,
        public string $shortName,
        public string $description,
        public int $pssmLength,
    ) {
    }

    /**
     * Banca dati d'origine, dal prefisso alfabetico dell'accession: `cd`, `pfam`, `COG`, `smart`, `LOAD`, ….
     */
    public function sourceDatabase(): string
    {
        preg_match('/^[A-Za-z]*/', $this->accession, $match);

        return $match[0] ?? '';
    }
}
