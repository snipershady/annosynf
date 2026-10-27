<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Pathway di `pathway.txt` (UniProt, ex UniPathway) con le accession Swiss-Prot che vi partecipano.
 */
final readonly class Pathway
{
    /**
     * @param string       $description `Amino-acid biosynthesis; L-lysine biosynthesis via DAP pathway; step 1/4`,
     *                                  senza il punto finale
     * @param list<string> $accessions  nell'ordine del file, ripetizioni comprese
     */
    public function __construct(
        public string $description,
        public array $accessions = [],
    ) {
    }

    /**
     * Livelli della descrizione (separati da `;`), senza spazi ai bordi: Annocript ne esporta i primi tre
     * (PwLev1..3).
     *
     * @return non-empty-list<string>
     */
    public function levels(): array
    {
        return array_map(trim(...), explode(';', $this->description));
    }
}
