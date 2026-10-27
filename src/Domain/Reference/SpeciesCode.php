<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Codice di specie di `speclist.txt` (il suffisso dei nomi di entry UniProt, es. `1433B_HUMAN`), per il
 * catalogo degli organismi (`ref_organism_catalog`).
 */
final readonly class SpeciesCode
{
    /**
     * @param string $code    `HUMAN`, `ECOLI`, `9VIRU`
     * @param string $kingdom `A` archaea, `B` batteri, `E` eucarioti, `V` virus, `O` altri, `X` radice
     * @param bool   $virtual codice "virtuale" che raggruppa più organismi (solo TrEMBL)
     */
    public function __construct(
        public string $code,
        public string $kingdom,
        public int $taxonId,
        public string $officialName,
        public ?string $commonName = null,
        public ?string $synonym = null,
        public bool $virtual = false,
    ) {
    }
}
