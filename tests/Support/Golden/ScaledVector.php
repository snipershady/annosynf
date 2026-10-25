<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Un vettore nel formato di libsvm (`<label> 1:<v1> 2:<v2> …`), come lo scrive svm-scale.
 */
final readonly class ScaledVector
{
    /**
     * @param array<int, float> $values indice della feature → valore
     */
    public function __construct(
        public string $label,
        public array $values,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato di libsvm
     */
    public static function fromLine(string $line): self
    {
        $tokens = preg_split('/\s+/', trim($line));
        $label = $tokens === false ? null : array_shift($tokens);
        $values = [];

        foreach ($tokens === false ? [] : $tokens as $token) {
            if (preg_match('/^(\d+):(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?)$/i', $token, $match) !== 1) {
                throw new UnexpectedValueException(\sprintf('Not a libsvm vector: "%s".', $line));
            }

            $values[(int) $match[1]] = (float) $match[2];
        }

        if ($label === null || $label === '') {
            throw new UnexpectedValueException('Empty libsvm vector.');
        }

        return new self($label, $values);
    }
}
