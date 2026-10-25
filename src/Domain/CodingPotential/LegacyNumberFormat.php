<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Numeri scritti come li scrivono i programmi della pipeline di CPC2, che si passano le feature attraverso
 * file di testo: `%g` di C (svm-scale, svm-predict) e `str()` di Python 2 (CPC2.py). Il port li usa sia per
 * l'output compatibile sia per riprodurre gli arrotondamenti intermedi, che cambiano le probabilità.
 */
final class LegacyNumberFormat
{
    /**
     * `%.<precision>g` di printf in C: notazione esponenziale se l'esponente è minore di −4 o almeno pari alla
     * precisione (esponente con segno e almeno due cifre), altrimenti decimale; zeri finali tolti.
     */
    public static function c(float $value, int $precision = 6): string
    {
        if (!is_finite($value)) {
            return is_nan($value) ? 'nan' : ($value > 0 ? 'inf' : '-inf');
        }

        if ($value === 0.0) {
            return '0';
        }

        $precision = max(1, $precision);
        [$mantissa, $exponent] = explode('e', \sprintf('%.' . ($precision - 1) . 'e', $value));
        $exponent = (int) $exponent;

        if ($exponent < -4 || $exponent >= $precision) {
            return \sprintf('%se%s%02d', self::withoutTrailingZeros($mantissa), $exponent < 0 ? '-' : '+', abs($exponent));
        }

        return self::withoutTrailingZeros(\sprintf('%.' . ($precision - 1 - $exponent) . 'f', $value));
    }

    /**
     * `str()` di un float in Python 2: `%.12g`, con `.0` se il risultato sembra un intero.
     */
    public static function python(float $value): string
    {
        $text = self::c($value, 12);

        return strpbrk($text, '.en') === false ? $text . '.0' : $text;
    }

    /**
     * Il valore che rilegge chi trova il numero scritto con `%g` (strtod di C o float() di Python).
     */
    public static function roundTrip(float $value, int $precision): float
    {
        return (float) self::c($value, $precision);
    }

    private static function withoutTrailingZeros(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
