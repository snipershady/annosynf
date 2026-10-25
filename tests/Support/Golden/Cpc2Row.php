<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Una riga dell'output di CPC2-beta:
 * `#ID transcript_length peptide_length Fickett_score pI ORF_integrity coding_probability label`.
 */
final readonly class Cpc2Row
{
    public const string HEADER = "#ID\ttranscript_length\tpeptide_length\tFickett_score\tpI\tORF_integrity\tcoding_probability\tlabel";

    public function __construct(
        public string $id,
        public int $transcriptLength,
        public int $peptideLength,
        public float $fickettScore,
        public float $isoelectricPoint,
        public int $orfIntegrity,
        public float $codingProbability,
        public string $label,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato di CPC2
     */
    public static function fromLine(string $line): self
    {
        $fields = explode("\t", $line);
        $number = '-?\d+(?:\.\d+)?(?:e[-+]?\d+)?';

        if (\count($fields) !== 8
            || preg_match('/^\d+$/', $fields[1]) !== 1
            || preg_match('/^\d+$/', $fields[2]) !== 1
            || preg_match('/^' . $number . '$/i', $fields[3]) !== 1
            || preg_match('/^' . $number . '$/i', $fields[4]) !== 1
            || !\in_array($fields[5], ['1', '-1'], true)
            || preg_match('/^' . $number . '$/i', $fields[6]) !== 1
            || !\in_array($fields[7], ['coding', 'noncoding'], true)) {
            throw new UnexpectedValueException(\sprintf('Not a CPC2 line: "%s".', $line));
        }

        return new self($fields[0], (int) $fields[1], (int) $fields[2], (float) $fields[3], (float) $fields[4], (int) $fields[5], (float) $fields[6], $fields[7]);
    }
}
