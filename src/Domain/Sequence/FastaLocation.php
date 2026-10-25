<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Dove si trova un problema: riga dell'header della sequenza (o della riga sbagliata) e nome della sequenza.
 */
final readonly class FastaLocation
{
    public function __construct(
        public int $lineNumber,
        public string $id,
    ) {
    }
}
