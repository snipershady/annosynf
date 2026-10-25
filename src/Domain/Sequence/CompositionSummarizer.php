<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Medie di composizione di un FASTA in un solo passaggio, a memoria costante.
 *
 * Diversamente dal legacy, che divideva per il numero di header, ogni media conta solo le sequenze in cui la
 * percentuale è definita: una sequenza senza A, C, G, T né N non ha percentuali (nel legacy fermava il
 * programma con una divisione per zero).
 */
final class CompositionSummarizer
{
    /**
     * @param iterable<SequenceComposition> $compositions
     */
    public function summarize(iterable $compositions): CompositionSummary
    {
        $sequences = 0;
        $bases = 0;
        $gc = 0;
        /** @var array{a: float, c: float, g: float, t: float, n: float} $sums */
        $sums = ['a' => 0.0, 'c' => 0.0, 'g' => 0.0, 't' => 0.0, 'n' => 0.0];
        $withKnownBases = 0;
        $gcSum = 0.0;
        $nonEmpty = 0;

        foreach ($compositions as $composition) {
            ++$sequences;
            $bases += $composition->length;
            $gc += $composition->gc();

            if ($composition->knownBases() > 0) {
                ++$withKnownBases;
                $sums['a'] += (float) $composition->adeninePercentage();
                $sums['c'] += (float) $composition->cytosinePercentage();
                $sums['g'] += (float) $composition->guaninePercentage();
                $sums['t'] += (float) $composition->thyminePercentage();
                $sums['n'] += (float) $composition->nPercentage();
            }

            if ($composition->length > 0) {
                ++$nonEmpty;
                $gcSum += (float) $composition->gcPercentage();
            }
        }

        return new CompositionSummary(
            $sequences,
            $bases,
            $this->average($sums['a'], $withKnownBases),
            $this->average($sums['c'], $withKnownBases),
            $this->average($sums['g'], $withKnownBases),
            $this->average($sums['t'], $withKnownBases),
            $this->average($sums['n'], $withKnownBases),
            $this->average($gcSum, $nonEmpty),
            $bases === 0 ? null : $gc / $bases * 100,
        );
    }

    private function average(float $sum, int $count): ?float
    {
        return $count === 0 ? null : $sum / $count;
    }
}
