<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Una riga di ATGC_Percentages.txt (extractPercentages di LIB/output_and_stats.pm):
 * `ID Length Adenine Cytosine Guanine Thymine N GCcontent Errors`.
 */
final readonly class AtgcRow
{
    public const string HEADER = "ID\tLength\tAdenine\tCytosine\tGuanine\tThymine\tN\tGCcontent\tErrors";

    public function __construct(
        public string $id,
        public int $length,
        public float $adenine,
        public float $cytosine,
        public float $guanine,
        public float $thymine,
        public float $n,
        public float $gcContent,
        public int $errors,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato del legacy
     */
    public static function fromLine(string $line): self
    {
        $fields = explode("\t", $line);
        $number = '/^-?\d+(?:\.\d+)?(?:e[-+]?\d+)?$/i';

        if (\count($fields) !== 9 || preg_match('/^\d+$/', $fields[1]) !== 1 || preg_match('/^-?\d+$/', $fields[8]) !== 1) {
            throw new UnexpectedValueException(\sprintf('Not an ATGC_Percentages line: "%s".', $line));
        }

        foreach (\array_slice($fields, 2, 6) as $value) {
            if (preg_match($number, $value) !== 1) {
                throw new UnexpectedValueException(\sprintf('Not an ATGC_Percentages line: "%s".', $line));
            }
        }

        return new self($fields[0], (int) $fields[1], (float) $fields[2], (float) $fields[3], (float) $fields[4], (float) $fields[5], (float) $fields[6], (float) $fields[7], (int) $fields[8]);
    }
}
