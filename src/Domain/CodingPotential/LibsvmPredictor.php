<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Predizione con probabilità di libsvm 3.18 (`svm_predict_probability`, come `svm-predict -b 1`) per un
 * modello C-SVC a 2 classi con kernel RBF.
 *
 * Valore di decisione = Σ coefficiente·exp(−γ·‖x − sv‖²) − ρ, sommando i vettori di supporto nell'ordine del
 * file; la probabilità della prima etichetta è la sigmoide di Platt (limitata a [1e-7, 1 − 1e-7]), poi
 * passata, come in libsvm, al metodo iterativo di Wu, Lin e Weng (`multiclass_probability`) anche con 2 sole
 * classi: si ferma quando l'errore scende sotto 0.0025, quindi il risultato non coincide esattamente con la
 * sigmoide. Vince l'etichetta più probabile, a parità la prima.
 */
final readonly class LibsvmPredictor
{
    private const float MIN_PROBABILITY = 1e-7;

    private const int MAX_ITERATIONS = 100;

    /** @var list<list<float>> vettori di supporto densi (le feature assenti valgono 0) */
    private array $vectors;

    private int $dimensions;

    public function __construct(
        private LibsvmModel $model,
    ) {
        $dimensions = max([0, ...array_map(static fn (array $vector): int => $vector === [] ? 0 : max(array_keys($vector)), $model->supportVectors)]);
        $this->dimensions = $dimensions;

        $this->vectors = array_map(static fn (array $vector): array => self::dense($vector, $dimensions), $model->supportVectors);
    }

    /**
     * @param array<int, float> $features indice → valore (scalate come in addestramento)
     */
    public function predict(array $features): LibsvmPrediction
    {
        $x = self::dense($features, max($this->dimensions, $features === [] ? 0 : max(array_keys($features))));
        $decision = 0.0;

        foreach ($this->vectors as $index => $vector) {
            $decision += $this->model->coefficients[$index] * $this->kernel($x, $vector);
        }

        $decision -= $this->model->rho;

        $pairwise = min(max($this->sigmoid($decision, $this->model->probA, $this->model->probB), self::MIN_PROBABILITY), 1 - self::MIN_PROBABILITY);
        [$first, $second] = $this->multiclassProbability($pairwise);
        [$firstLabel, $secondLabel] = $this->model->labels;

        return new LibsvmPrediction($second > $first ? $secondLabel : $firstLabel, $decision, [$firstLabel => $first, $secondLabel => $second]);
    }

    /**
     * Kernel RBF. Con i vettori densi le feature assenti contribuiscono come in libsvm: (x − 0)² = x².
     *
     * @param list<float> $x
     * @param list<float> $vector
     */
    private function kernel(array $x, array $vector): float
    {
        $sum = 0.0;

        foreach ($x as $index => $value) {
            $difference = $value - ($vector[$index] ?? 0.0);
            $sum += $difference * $difference;
        }

        return exp(-$this->model->gamma * $sum);
    }

    private function sigmoid(float $decision, float $a, float $b): float
    {
        $fApB = $decision * $a + $b;

        return $fApB >= 0 ? exp(-$fApB) / (1.0 + exp(-$fApB)) : 1.0 / (1 + exp($fApB));
    }

    /**
     * multiclass_probability di libsvm con k = 2: r01 = probabilità della prima classe contro la seconda.
     *
     * @return array{float, float}
     */
    private function multiclassProbability(float $r01): array
    {
        $k = 2;
        $r = [[0.0, $r01], [1 - $r01, 0.0]];
        $q = [[$r[1][0] * $r[1][0], -$r[1][0] * $r[0][1]], [0.0, $r[0][1] * $r[0][1]]];
        $q[1][0] = $q[0][1];
        $p = [1.0 / $k, 1.0 / $k];
        $epsilon = 0.005 / $k;

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; ++$iteration) {
            $qp = [0.0, 0.0];
            $pQp = 0.0;

            for ($t = 0; $t < $k; ++$t) {
                for ($j = 0; $j < $k; ++$j) {
                    $qp[$t] += $q[$t][$j] * $p[$j];
                }

                $pQp += $p[$t] * $qp[$t];
            }

            if (max(abs($qp[0] - $pQp), abs($qp[1] - $pQp)) < $epsilon) {
                break;
            }

            for ($t = 0; $t < $k; ++$t) {
                $difference = (-$qp[$t] + $pQp) / $q[$t][$t];
                $p[$t] += $difference;
                $pQp = ($pQp + $difference * ($difference * $q[$t][$t] + 2 * $qp[$t])) / (1 + $difference) / (1 + $difference);

                for ($j = 0; $j < $k; ++$j) {
                    $qp[$j] = ($qp[$j] + $difference * $q[$t][$j]) / (1 + $difference);
                    $p[$j] /= (1 + $difference);
                }
            }
        }

        return [$p[0], $p[1]];
    }

    /**
     * @param array<int, float> $sparse indice (da 1) → valore
     *
     * @return list<float> posizione 0 = feature 1
     */
    private static function dense(array $sparse, int $dimensions): array
    {
        $dense = [];

        for ($index = 1; $index <= $dimensions; ++$index) {
            $dense[] = $sparse[$index] ?? 0.0;
        }

        return $dense;
    }
}
