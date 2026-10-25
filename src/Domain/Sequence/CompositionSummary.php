<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Composizione media di un FASTA (le righe "Average percentage of …" delle statistiche del legacy).
 * Le medie sono aritmetiche sulle percentuali delle singole sequenze; null se nessuna sequenza le definisce.
 */
final readonly class CompositionSummary
{
    public function __construct(
        public int $sequences,
        public int $bases,
        public ?float $averageAdenine,
        public ?float $averageCytosine,
        public ?float $averageGuanine,
        public ?float $averageThymine,
        public ?float $averageN,
        public ?float $averageGc,
        /** G+C totali sulle basi totali. */
        public ?float $overallGc,
    ) {
    }
}
