<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Composizione in basi di una sequenza nucleotidica, con le definizioni di extractPercentages
 * (LIB/output_and_stats.pm): A, C, G, T e N contate senza distinguere maiuscole e minuscole, loro percentuali
 * sul totale A+C+G+T+N, GC sulla lunghezza, "errori" = tutti gli altri caratteri (U compresa: il legacy
 * calcolava la composizione solo per il DNA).
 */
final readonly class SequenceComposition
{
    public function __construct(
        public int $length,
        public int $adenine,
        public int $cytosine,
        public int $guanine,
        public int $thymine,
        public int $n,
    ) {
    }

    public static function of(string $sequence): self
    {
        $counts = count_chars(strtoupper($sequence), 1);

        return new self(\strlen($sequence), $counts[\ord('A')] ?? 0, $counts[\ord('C')] ?? 0, $counts[\ord('G')] ?? 0, $counts[\ord('T')] ?? 0, $counts[\ord('N')] ?? 0);
    }

    public function knownBases(): int
    {
        return $this->adenine + $this->cytosine + $this->guanine + $this->thymine + $this->n;
    }

    public function errors(): int
    {
        return $this->length - $this->knownBases();
    }

    public function gc(): int
    {
        return $this->guanine + $this->cytosine;
    }

    /**
     * Percentuali sul totale A+C+G+T+N: null se la sequenza non ne contiene (il legacy si fermava con una
     * divisione per zero).
     */
    public function adeninePercentage(): ?float
    {
        return $this->ofKnownBases($this->adenine);
    }

    public function cytosinePercentage(): ?float
    {
        return $this->ofKnownBases($this->cytosine);
    }

    public function guaninePercentage(): ?float
    {
        return $this->ofKnownBases($this->guanine);
    }

    public function thyminePercentage(): ?float
    {
        return $this->ofKnownBases($this->thymine);
    }

    public function nPercentage(): ?float
    {
        return $this->ofKnownBases($this->n);
    }

    /**
     * G+C in percentuale della lunghezza; null per una sequenza vuota.
     */
    public function gcPercentage(): ?float
    {
        return $this->length === 0 ? null : $this->gc() / $this->length * 100;
    }

    private function ofKnownBases(int $count): ?float
    {
        $known = $this->knownBases();

        return $known === 0 ? null : $count / $known * 100;
    }
}
