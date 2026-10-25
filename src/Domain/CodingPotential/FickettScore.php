<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Punteggio TESTCODE di Fickett (NAR 10(17):5303) sull'intera sequenza, come la classe Fickett di CPC2-beta
 * (derivata da CPAT 1.2.1): seconda feature del modello SVM. Tabelle copiate da CPC2.py.
 *
 * Per ogni base A, C, G, T: contenuto = occorrenze / lunghezza della sequenza (le altre lettere contano nella
 * lunghezza), posizione = massimo / (minimo + 1) delle occorrenze nelle tre fasi dei codoni. Ciascun valore
 * sceglie la probabilità della prima soglia che raggiunge, pesata per la base; il punteggio è la somma degli
 * otto termini, nello stesso ordine di CPC2 (contenuti A, C, G, T, poi posizioni A, C, G, T).
 */
final readonly class FickettScore
{
    private const array BASES = ['A', 'C', 'G', 'T'];

    private const array POSITION_THRESHOLDS = [1.9, 1.8, 1.7, 1.6, 1.5, 1.4, 1.3, 1.2, 1.1, 0.0];

    private const array CONTENT_THRESHOLDS = [0.33, 0.31, 0.29, 0.27, 0.25, 0.23, 0.21, 0.19, 0.17, 0.0];

    private const array POSITION_PROBABILITY = [
        'A' => [0.51, 0.55, 0.57, 0.52, 0.48, 0.58, 0.57, 0.54, 0.50, 0.36],
        'C' => [0.29, 0.44, 0.55, 0.49, 0.52, 0.60, 0.60, 0.56, 0.51, 0.38],
        'G' => [0.62, 0.67, 0.74, 0.65, 0.61, 0.62, 0.52, 0.41, 0.31, 0.17],
        'T' => [0.51, 0.60, 0.69, 0.64, 0.62, 0.67, 0.58, 0.48, 0.39, 0.24],
    ];

    private const array POSITION_WEIGHT = ['A' => 0.062, 'C' => 0.093, 'G' => 0.205, 'T' => 0.154];

    private const array CONTENT_PROBABILITY = [
        'A' => [0.40, 0.55, 0.58, 0.58, 0.52, 0.48, 0.45, 0.45, 0.38, 0.19],
        'C' => [0.50, 0.63, 0.59, 0.50, 0.46, 0.45, 0.47, 0.56, 0.59, 0.33],
        'G' => [0.21, 0.40, 0.47, 0.50, 0.52, 0.56, 0.57, 0.52, 0.44, 0.23],
        'T' => [0.30, 0.49, 0.56, 0.53, 0.48, 0.48, 0.52, 0.57, 0.60, 0.51],
    ];

    private const array CONTENT_WEIGHT = ['A' => 0.084, 'C' => 0.076, 'G' => 0.081, 'T' => 0.055];

    /**
     * Punteggio della sequenza (normalizzata come in CPC2: maiuscole, U → T); 0 sotto le 2 basi.
     */
    public function of(string $sequence): float
    {
        $dna = CpcOrfFinder::normalize($sequence);
        $length = \strlen($dna);

        if ($length < 2) {
            return 0.0;
        }

        $phases = $this->phaseCounts($dna);
        $score = 0.0;

        foreach (self::BASES as $base) {
            $content = ($phases[0][$base] + $phases[1][$base] + $phases[2][$base]) / $length;
            $score += $this->lookUp($content, self::CONTENT_THRESHOLDS, self::CONTENT_PROBABILITY[$base]) * self::CONTENT_WEIGHT[$base];
        }

        foreach (self::BASES as $base) {
            $counts = [$phases[0][$base], $phases[1][$base], $phases[2][$base]];
            $position = max($counts) / (min($counts) + 1.0);
            $score += $this->lookUp($position, self::POSITION_THRESHOLDS, self::POSITION_PROBABILITY[$base]) * self::POSITION_WEIGHT[$base];
        }

        return $score;
    }

    /**
     * Occorrenze di A, C, G, T in ciascuna fase (posizioni 0, 1, 2 dei codoni, come dna[k::3]).
     *
     * @return array{array<string, int>, array<string, int>, array<string, int>}
     */
    private function phaseCounts(string $dna): array
    {
        $phases = ['', '', ''];

        foreach (str_split($dna, 3) as $codon) {
            $phases[0] .= $codon[0];
            $phases[1] .= $codon[1] ?? '';
            $phases[2] .= $codon[2] ?? '';
        }

        return [$this->baseCounts($phases[0]), $this->baseCounts($phases[1]), $this->baseCounts($phases[2])];
    }

    /**
     * @return array<string, int>
     */
    private function baseCounts(string $phase): array
    {
        $bytes = count_chars($phase, 1);
        $counts = [];

        foreach (self::BASES as $base) {
            $counts[$base] = $bytes[\ord($base)] ?? 0;
        }

        return $counts;
    }

    /**
     * Probabilità della prima soglia raggiunta. L'ultima soglia è 0 e i valori non sono mai negativi: se nessuna
     * delle precedenti è raggiunta vale l'ultima.
     *
     * @param non-empty-list<float> $thresholds
     * @param non-empty-list<float> $probabilities stessa lunghezza delle soglie
     */
    private function lookUp(float $value, array $thresholds, array $probabilities): float
    {
        $index = 0;

        while ($index < \count($thresholds) - 1 && $value < $thresholds[$index]) {
            ++$index;
        }

        return $probabilities[$index];
    }
}
