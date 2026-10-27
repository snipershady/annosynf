<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Associazioni Pfam → GO di `pfam2go` (`Pfam:PF00001 7tm_1 > GO:membrane ; GO:0016020`), come le legge
 * `createPfamGO` di Annocript (LIB/db_creator.pm), con l'accession Pfam scritta come in CDD (`pfam00001`).
 *
 * Il legacy risolve subito l'accession sulla cdTable e, se non la trova, attribuisce i GO al dominio della
 * riga precedente (§A.12.4): qui l'associazione resta legata alla sua accession, e la risoluzione sui domini
 * spetta all'import, che scarta le accession assenti.
 */
final readonly class Pfam2GoParser
{
    private const string FILE = 'pfam2go';

    /**
     * Data della versione (`!version date: 2026/07/06 18:13:03` → `2026/07/06`), null se assente.
     *
     * @param iterable<string> $lines
     */
    public function version(iterable $lines): ?string
    {
        foreach ($lines as $line) {
            if (!str_starts_with($line, '!')) {
                break;
            }

            if (preg_match('/^!version.*\s(\d+\/\d+\/\d+)\s/', $line, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidDomainFileException
     *
     * @return Generator<int, Pfam2GoMapping> numero di riga → associazione
     */
    public function mappings(iterable $lines): Generator
    {
        $number = 0;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");

            if ($line === '' || str_starts_with($line, '!')) {
                continue;
            }

            if (preg_match('/^Pfam:PF(\d+)\s+(\S+)\s+>\s+GO:(.*?)\s+;\s+(GO:\d{7})\s*$/', $line, $match) !== 1) {
                throw InvalidDomainFileException::atLine(self::FILE, $number, \sprintf('expected "Pfam:PF… name > GO:term ; GO:…", found "%s"', $line));
            }

            yield $number => new Pfam2GoMapping('pfam' . $match[1], $match[2], $match[4], $match[3]);
        }
    }
}
