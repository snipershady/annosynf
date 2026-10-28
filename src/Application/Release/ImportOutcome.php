<?php

declare(strict_types=1);

namespace App\Application\Release;

/**
 * Esito di un {@see ImportReferenceData}.
 */
final readonly class ImportOutcome
{
    /**
     * @param int                   $rows           righe o associazioni caricate
     * @param ?int                  $unmatched      associazioni scartate (solo i task `map_*`)
     * @param array<string, string> $sourceVersions versione dichiarata dal file sorgente, per `source_versions`
     */
    public function __construct(
        public int $rows,
        public ?int $unmatched = null,
        public array $sourceVersions = [],
    ) {
    }

    /**
     * Riassunto per il log del task.
     */
    public function summary(): string
    {
        return $this->unmatched === null
            ? \sprintf('%d rows loaded', $this->rows)
            : \sprintf('%d associations loaded, %d skipped (protein, domain or term not in the release)', $this->rows, $this->unmatched);
    }
}
