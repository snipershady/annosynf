<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

use InvalidArgumentException;

/**
 * Punto isoelettrico con il metodo di Bjellqvist, come `ProtParam.ProteinAnalysis.isoelectric_point()` di
 * Biopython 1.76 (`Bio.SeqUtils.IsoelectricPoint`): terza feature del modello di CPC2.
 *
 * La carica a un dato pH somma i contributi dei residui carichi e dei due terminali, con i pK del terminale
 * N e C che dipendono dal primo e dall'ultimo residuo. Il pI si cerca a partire da pH 7, a passi di 1 fino a
 * racchiudere lo zero della carica, poi per bisezione finché l'intervallo supera 0.0001. Le somme seguono
 * l'ordine dei dict di Python 2 (H, K, R, N-terminale; Y, C-terminale, C, E, D): cambiarlo sposterebbe
 * l'ultima cifra.
 */
final readonly class IsoelectricPoint
{
    private const array POSITIVE_PKS = ['H' => 5.98, 'K' => 10.0, 'R' => 12.0, 'Nterm' => 7.5];

    private const array NEGATIVE_PKS = ['Y' => 10.0, 'Cterm' => 3.55, 'C' => 9.0, 'E' => 4.45, 'D' => 4.05];

    private const array N_TERMINAL_PKS = ['A' => 7.59, 'M' => 7.0, 'S' => 6.93, 'P' => 8.36, 'T' => 6.82, 'V' => 7.44, 'E' => 7.7];

    private const array C_TERMINAL_PKS = ['D' => 4.55, 'E' => 4.75];

    /**
     * @param string $protein sequenza proteica in maiuscolo; le lettere che non sono residui carichi non
     *                        contano (salvo come terminali)
     *
     * @throws InvalidArgumentException se la sequenza è vuota (Biopython fallisce cercando i terminali)
     */
    public function of(string $protein): float
    {
        if ($protein === '') {
            throw new InvalidArgumentException('The isoelectric point of an empty protein is undefined.');
        }

        $counts = count_chars($protein, 1);
        $positive = self::POSITIVE_PKS;
        $negative = self::NEGATIVE_PKS;
        $positive['Nterm'] = self::N_TERMINAL_PKS[$protein[0]] ?? $positive['Nterm'];
        $negative['Cterm'] = self::C_TERMINAL_PKS[$protein[-1]] ?? $negative['Cterm'];
        $content = static fn (string $group): float => \strlen($group) > 1 ? 1.0 : (float) ($counts[\ord($group)] ?? 0);

        $charge = static function (float $pH) use ($positive, $negative, $content): float {
            $positiveCharge = 0.0;

            foreach ($positive as $group => $pK) {
                $ratio = 10 ** ($pK - $pH);
                $positiveCharge += $content($group) * ($ratio / ($ratio + 1.0));
            }

            $negativeCharge = 0.0;

            foreach ($negative as $group => $pK) {
                $ratio = 10 ** ($pH - $pK);
                $negativeCharge += $content($group) * ($ratio / ($ratio + 1.0));
            }

            return $positiveCharge - $negativeCharge;
        };

        return $this->bisect($charge);
    }

    /**
     * pi() di Biopython: intervallo [pH1, pH2] con carica positiva a sinistra e non positiva a destra, poi
     * bisezione.
     *
     * @param callable(float): float $charge
     */
    private function bisect(callable $charge): float
    {
        $pH = 7.0;
        $value = $charge($pH);
        $low = $pH;
        $high = $pH;

        if ($value > 0.0) {
            while ($value > 0.0) {
                $low = $pH;
                $pH = $low + 1.0;
                $value = $charge($pH);
            }

            $high = $pH;
        } else {
            while ($value < 0.0) {
                $high = $pH;
                $pH = $high - 1.0;
                $value = $charge($pH);
            }

            $low = $pH;
        }

        while ($high - $low > 0.0001 && $value !== 0.0) {
            $pH = ($low + $high) / 2.0;
            $value = $charge($pH);

            if ($value > 0.0) {
                $low = $pH;
            } else {
                $high = $pH;
            }
        }

        return $pH;
    }
}
