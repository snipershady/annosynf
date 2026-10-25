<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

/**
 * Confronto cella per cella tra un golden e l'output di un port. Non si ferma alla prima differenza: le
 * raccoglie tutte, così il messaggio di un test fallito mostra il quadro completo. Le differenze attese
 * (pareggi, correzioni dei bug legacy di §A.12) si dichiarano con {@see self::allow()} e una motivazione;
 * un'eccezione dichiarata ma mai usata è anch'essa un errore, così l'elenco resta esatto.
 */
final class GoldenComparison
{
    /** @var list<string> */
    private array $mismatches = [];

    /** @var array<string, string> chiave → motivazione */
    private array $allowed = [];

    /** @var array<string, true> */
    private array $usedAllowances = [];

    private int $comparisons = 0;

    public function __construct(
        private readonly float $tolerance = 1e-6,
    ) {
    }

    /**
     * Ammette differenze sulla chiave indicata (es. l'id di una sequenza), per il motivo scritto.
     */
    public function allow(string $key, string $reason): self
    {
        $this->allowed[$key] = $reason;

        return $this;
    }

    public function same(string $key, string $field, int|string $expected, int|string $actual): void
    {
        $this->record($key, $field, $expected === $actual, var_export($expected, true), var_export($actual, true));
    }

    public function close(string $key, string $field, float $expected, float $actual): void
    {
        $this->record($key, $field, abs($expected - $actual) <= $this->tolerance, (string) $expected, (string) $actual);
    }

    /**
     * Differenze non ammesse, più le eccezioni dichiarate che non sono servite.
     *
     * @return list<string>
     */
    public function failures(): array
    {
        $unused = array_keys(array_diff_key($this->allowed, $this->usedAllowances));

        return [
            ...$this->mismatches,
            ...array_map(static fn (string $key): string => \sprintf('%s: allowed difference never happened, remove it', $key), $unused),
        ];
    }

    public function comparisons(): int
    {
        return $this->comparisons;
    }

    /**
     * Messaggio per l'asserzione: le prime differenze, una per riga.
     */
    public function report(int $limit = 50): string
    {
        $failures = $this->failures();

        return \sprintf("%d differences out of %d comparisons:\n%s", \count($failures), $this->comparisons, implode("\n", \array_slice($failures, 0, $limit)));
    }

    private function record(string $key, string $field, bool $equal, string $expected, string $actual): void
    {
        ++$this->comparisons;

        if ($equal) {
            return;
        }

        if (isset($this->allowed[$key])) {
            $this->usedAllowances[$key] = true;

            return;
        }

        $this->mismatches[] = \sprintf('%s %s: expected %s, got %s', $key, $field, $expected, $actual);
    }
}
