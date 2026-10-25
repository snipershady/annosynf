<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Feature e potenziale codificante di una sequenza secondo CPC2 (una riga dell'output di CPC2.py).
 */
final readonly class Cpc2Prediction
{
    /**
     * @param int   $peptideLength     amminoacidi della traduzione dell'ORF, stop compreso (0 senza ORF)
     * @param float $fickettScore      punteggio di Fickett dell'intera sequenza
     * @param float $isoelectricPoint  pI del peptide (0 senza ORF)
     * @param float $codingProbability probabilità dell'etichetta coding
     */
    public function __construct(
        public int $transcriptLength,
        public ?CpcOrf $orf,
        public int $peptideLength,
        public float $fickettScore,
        public float $isoelectricPoint,
        public OrfIntegrity $integrity,
        public float $codingProbability,
        public CodingLabel $label,
    ) {
    }
}
