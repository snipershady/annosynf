<?php

declare(strict_types=1);

namespace App\Domain\Blast;

use InvalidArgumentException;

/**
 * Legge l'output di `blastdbcmd -info` ("1,234 sequences; 56,789 total residues", oppure "total bases" per i
 * nucleotidi), che con più volumi riporta i totali dell'intero database.
 */
final class BlastDbInfoParser
{
    /**
     * @throws InvalidArgumentException se l'output non contiene il riepilogo del database
     */
    public static function parse(string $output): BlastDbInfo
    {
        if (preg_match('/^\s*([\d,]+) sequences; ([\d,]+) total (?:residues|bases)\s*$/m', $output, $match) !== 1) {
            throw new InvalidArgumentException('The output of blastdbcmd -info has no sequence summary.');
        }

        return new BlastDbInfo(max(0, (int) str_replace(',', '', $match[1])), max(0, (int) str_replace(',', '', $match[2])));
    }
}
