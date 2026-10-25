<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Una riga di `cpc2_cases/codons.tsv` (comando cpc2codons del legacy-runner): `codone  amminoacido`, con `!`
 * quando Biopython solleva TranslationError.
 */
final readonly class CodonRow
{
    public const string ERROR = '!';

    public function __construct(
        public string $codon,
        public string $aminoAcid,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato di cpc2codons
     */
    public static function fromLine(string $line): self
    {
        if (preg_match('/^([A-Z-]{3})\t([A-Z*!])$/', $line, $match) !== 1) {
            throw new UnexpectedValueException(\sprintf('Not a cpc2codons line: "%s".', $line));
        }

        return new self($match[1], $match[2]);
    }
}
