<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Un tipo di problema trovato in un FASTA: quante volte e dove (solo le prime posizioni).
 */
final readonly class FastaIssueSummary
{
    /**
     * @param list<FastaLocation> $locations le prime {@see FastaValidationReport::MAX_LOCATIONS}, in ordine di file
     */
    public function __construct(
        public FastaIssue $issue,
        public int $count,
        public array $locations,
    ) {
    }
}
