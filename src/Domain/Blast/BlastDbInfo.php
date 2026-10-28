<?php

declare(strict_types=1);

namespace App\Domain\Blast;

/**
 * Contenuto di un database BLAST come lo riporta `blastdbcmd -info`.
 */
final readonly class BlastDbInfo
{
    /**
     * @param non-negative-int $sequences
     * @param non-negative-int $letters   residui (proteine) o basi (nucleotidi)
     * @param ?string          $version   versione di BLAST+ che ha costruito o verificato il database
     */
    public function __construct(
        public int $sequences,
        public int $letters,
        public ?string $version = null,
    ) {
    }
}
