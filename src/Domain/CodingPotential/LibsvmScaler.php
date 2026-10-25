<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Scaling delle feature come `svm-scale -r <range>` di libsvm 3.18, con il file di range scritto da
 * `svm-scale -s` (riga `x`, limiti `lower upper`, poi `indice min max` per ogni feature).
 *
 * Il valore uguale al minimo diventa `lower`, uguale al massimo `upper`, gli altri si interpolano linearmente
 * senza limitarli all'intervallo; le feature con minimo uguale al massimo e quelle assenti dal range si
 * scartano. svm-scale stampa il risultato con `%g`: l'arrotondamento a 6 cifre lo applica chi simula il
 * passaggio su file ({@see Cpc2Predictor}).
 */
final readonly class LibsvmScaler
{
    private const string FILE = 'libsvm range';

    /**
     * @param array<int, array{float, float}> $ranges indice della feature → [minimo, massimo]
     */
    public function __construct(
        public float $lower,
        public float $upper,
        public array $ranges,
    ) {
    }

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidLibsvmFileException
     */
    public static function parse(iterable $lines): self
    {
        $number = 0;
        $limits = null;
        $ranges = [];

        foreach ($lines as $line) {
            ++$number;
            $fields = self::fields($line);

            if ($number === 1) {
                if ($fields !== ['x']) {
                    throw InvalidLibsvmFileException::at(self::FILE, $number, 'only feature ranges ("x") are supported');
                }

                continue;
            }

            if ($fields === ['']) {
                continue;
            }

            $values = self::numbers($fields, $number);

            if ($limits === null) {
                if (\count($values) !== 2) {
                    throw InvalidLibsvmFileException::at(self::FILE, $number, 'expected "lower upper"');
                }

                $limits = [$values[0], $values[1]];

                continue;
            }

            if (\count($values) !== 3 || preg_match('/^[1-9]\d*$/', $fields[0]) !== 1) {
                throw InvalidLibsvmFileException::at(self::FILE, $number, 'expected "index min max"');
            }

            $ranges[(int) $fields[0]] = [$values[1], $values[2]];
        }

        if ($limits === null || $ranges === []) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, 'no feature ranges');
        }

        return new self($limits[0], $limits[1], $ranges);
    }

    /**
     * @param array<int, float> $features indice → valore
     *
     * @return array<int, float> le feature scalate (le scartate mancano)
     */
    public function scale(array $features): array
    {
        $scaled = [];

        foreach ($features as $index => $value) {
            if (!isset($this->ranges[$index])) {
                continue;
            }

            [$minimum, $maximum] = $this->ranges[$index];

            if ($minimum === $maximum) {
                continue;
            }

            $scaled[$index] = match ($value) {
                $minimum => $this->lower,
                $maximum => $this->upper,
                default => $this->lower + ($this->upper - $this->lower) * ($value - $minimum) / ($maximum - $minimum),
            };
        }

        return $scaled;
    }

    /**
     * @param list<string> $fields
     *
     * @throws InvalidLibsvmFileException
     *
     * @return list<float>
     */
    private static function numbers(array $fields, int $number): array
    {
        return array_map(static fn (string $field): float => is_numeric($field) ? (float) $field : throw InvalidLibsvmFileException::at(self::FILE, $number, \sprintf('"%s" is not a number', $field)), $fields);
    }

    /**
     * @return list<string>
     */
    private static function fields(string $line): array
    {
        $fields = preg_split('/\s+/', trim($line));

        return $fields === false ? [] : $fields;
    }
}
