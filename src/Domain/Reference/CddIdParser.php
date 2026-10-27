<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Domini di `cddid_all.tbl` (TSV senza intestazione: PSSM-Id, accession, nome breve, descrizione, lunghezza del
 * PSSM), come li legge `createCDTable` di Annocript (LIB/db_creator.pm).
 */
final readonly class CddIdParser
{
    private const string FILE = 'cddid_all.tbl';

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidDomainFileException
     *
     * @return Generator<int, CddDomain> numero di riga → dominio
     */
    public function domains(iterable $lines): Generator
    {
        $number = 0;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            $fields = explode("\t", $line);

            if (\count($fields) !== 5) {
                throw InvalidDomainFileException::atLine(self::FILE, $number, \sprintf('expected 5 tab-separated fields, found %d', \count($fields)));
            }

            [$pssmId, $accession, $shortName, $description, $pssmLength] = $fields;

            if (preg_match('/^\d+$/', $pssmId) !== 1 || preg_match('/^\d+$/', $pssmLength) !== 1 || $accession === '') {
                throw InvalidDomainFileException::atLine(self::FILE, $number, \sprintf('malformed domain "%s"', $line));
            }

            yield $number => new CddDomain((int) $pssmId, $accession, $shortName, $description, (int) $pssmLength);
        }
    }
}
