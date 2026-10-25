<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Esito di svm_predict_probability: l'etichetta più probabile e la probabilità di ogni etichetta.
 */
final readonly class LibsvmPrediction
{
    /**
     * @param array<int, float> $probabilities etichetta → probabilità, nell'ordine delle etichette del modello
     */
    public function __construct(
        public int $label,
        public float $decisionValue,
        public array $probabilities,
    ) {
    }

    public function probabilityOf(int $label): float
    {
        return $this->probabilities[$label] ?? 0.0;
    }
}
