<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Una riga di `cpc2_orf.tsv` (comando cpc2orf del legacy-runner): l'ORF scelto da FindCDS di CPC2 e la sua
 * traduzione Biopython, `id  inizio 0-based  integrity  ORF  peptide`. Integrity grezza: 0 se non c'è ORF.
 */
final readonly class CpcOrfRow
{
    public function __construct(
        public string $id,
        public int $start,
        public int $integrity,
        public string $dna,
        public string $peptide,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato di cpc2orf
     */
    public static function fromLine(string $line): self
    {
        $fields = explode("\t", $line);

        if (\count($fields) !== 5
            || preg_match('/^\d+$/', $fields[1]) !== 1
            || !\in_array($fields[2], ['1', '0', '-1'], true)
            || preg_match('/^[A-Z]*$/', $fields[3]) !== 1
            || preg_match('/^[A-Z*]*$/', $fields[4]) !== 1) {
            throw new UnexpectedValueException(\sprintf('Not a cpc2orf line: "%s".', mb_strimwidth($line, 0, 80, '…')));
        }

        return new self($fields[0], (int) $fields[1], (int) $fields[2], $fields[3], $fields[4]);
    }
}
