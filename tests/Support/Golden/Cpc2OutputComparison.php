<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

/**
 * Confronto tra una riga dell'output di CPC2 e quella del port: tutte le colonne byte per byte tranne
 * coding_probability, confrontata con la tolleranza del {@see GoldenComparison} (1e-6). La probabilità passa
 * per migliaia di exp() con coefficienti fino a 16284, e exp() di versioni diverse della libm differisce
 * nell'ultimo bit: abbastanza per spostare l'ultima cifra stampata con %g quando il valore è vicino a un
 * arrotondamento (1 sequenza su 20000 nel benchmark dello step 3.7).
 */
final class Cpc2OutputComparison
{
    public static function compare(GoldenComparison $comparison, string $expected, string $actual): void
    {
        $expectedRow = Cpc2Row::fromLine($expected);
        $actualRow = Cpc2Row::fromLine($actual);

        $comparison->same($expectedRow->id, 'columns', self::withoutProbability($expected), self::withoutProbability($actual));
        $comparison->close($expectedRow->id, 'coding_probability', $expectedRow->codingProbability, $actualRow->codingProbability);
    }

    /**
     * La riga senza la colonna coding_probability.
     */
    public static function withoutProbability(string $line): string
    {
        $fields = explode("\t", $line);
        unset($fields[6]);

        return implode("\t", $fields);
    }
}
