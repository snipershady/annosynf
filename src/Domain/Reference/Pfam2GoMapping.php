<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Riga di `pfam2go` (InterPro2GO): un termine GO associato a una famiglia Pfam.
 */
final readonly class Pfam2GoMapping
{
    /**
     * @param string $pfamAccession accession come in CDD (`pfam00001`), da `Pfam:PF00001`
     * @param string $pfamName      `7tm_1`
     * @param string $goId          `GO:0004930`
     * @param string $goName        `G protein-coupled receptor activity`
     */
    public function __construct(
        public string $pfamAccession,
        public string $pfamName,
        public string $goId,
        public string $goName,
    ) {
    }
}
