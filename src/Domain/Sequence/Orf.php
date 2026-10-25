<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Il più lungo open reading frame trovato in una sequenza.
 */
final readonly class Orf
{
    /**
     * @param string $peptide    traduzione dell'ORF con il primo codone letto come start (come dna2pep)
     * @param string $annotation annotazione dei codoni dell'ORF (`*`, `M`, `m`, `.`)
     * @param string $dna        nucleotidi dell'ORF nel verso del frame, stop escluso, maiuscole come nell'input
     * @param int    $start      prima base sul filamento diretto, da 1
     * @param int    $end        ultima base sul filamento diretto (start − 1 se l'ORF è vuoto)
     */
    public function __construct(
        public ReadingFrame $frame,
        public OrfMode $mode,
        public string $peptide,
        public string $annotation,
        public string $dna,
        public int $start,
        public int $end,
    ) {
    }

    /**
     * Lunghezza in amminoacidi (colonna LongOrfLength di Annocript).
     */
    public function length(): int
    {
        return \strlen($this->peptide);
    }

    public function strand(): string
    {
        return $this->frame->strand();
    }

    /**
     * Frame 0..2 come lo scrive Annocript (colonna LongOrfFrame, allineata a BLAST): |frame di dna2pep| − 1.
     */
    public function frameOffset(): int
    {
        return $this->frame->offset();
    }
}
