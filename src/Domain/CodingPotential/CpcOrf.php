<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * L'ORF che CPC2 sceglie come probabile CDS: da un ATG allo stop incluso, o all'ultimo codone completo.
 */
final readonly class CpcOrf
{
    /**
     * @param string $dna   nucleotidi dell'ORF normalizzati (maiuscole, U → T), stop compreso se c'è
     * @param int    $start prima base sulla sequenza, da 1
     */
    public function __construct(
        public string $dna,
        public int $start,
        public OrfIntegrity $integrity,
    ) {
    }

    /**
     * Ultima base sulla sequenza, da 1.
     */
    public function end(): int
    {
        return $this->start + \strlen($this->dna) - 1;
    }

    /**
     * Numero di codoni, stop compreso.
     */
    public function codons(): int
    {
        return intdiv(\strlen($this->dna), 3);
    }
}
