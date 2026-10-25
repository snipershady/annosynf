<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Esito della validazione di un FASTA: errori bloccanti e avvisi, con le prime posizioni di ciascun problema
 * (come il legacy, che ne riportava una ventina per tipo).
 */
final readonly class FastaValidationReport
{
    public const int MAX_LOCATIONS = 20;

    /**
     * @param int                              $sequences         sequenze lette (fino al primo errore di struttura)
     * @param array<string, FastaIssueSummary> $summaries         per valore di {@see FastaIssue}, nell'ordine dell'enum
     * @param list<string>                     $invalidCharacters caratteri non ammessi trovati, maiuscoli e ordinati
     */
    public function __construct(
        public int $sequences,
        private array $summaries,
        public array $invalidCharacters,
    ) {
    }

    /**
     * Nessun errore bloccante (gli avvisi sono ammessi).
     */
    public function isValid(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<FastaIssueSummary>
     */
    public function errors(): array
    {
        return $this->withSeverity(IssueSeverity::Error);
    }

    /**
     * @return list<FastaIssueSummary>
     */
    public function warnings(): array
    {
        return $this->withSeverity(IssueSeverity::Warning);
    }

    public function summary(FastaIssue $issue): ?FastaIssueSummary
    {
        return $this->summaries[$issue->value] ?? null;
    }

    /**
     * @return list<FastaIssueSummary>
     */
    private function withSeverity(IssueSeverity $severity): array
    {
        return array_values(array_filter(
            $this->summaries,
            static fn (FastaIssueSummary $summary): bool => $summary->issue->severity() === $severity,
        ));
    }
}
