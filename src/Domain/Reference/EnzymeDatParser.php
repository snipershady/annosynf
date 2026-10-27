<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Voci di `enzyme.dat`, come le legge `extractEnzimeIdsFile`/`createEnzymeTable` di Annocript
 * (LIB/db_creator.pm): numero EC dalla riga `ID`, descrizione dalle righe `DE`, accession dalle righe `DR`
 * (`P07327, ADH1A_HUMAN;  P28469, ADH1A_MACMU;` → `P07327`, `P28469`). Le voci trasferite, cancellate o senza
 * `DR` restano, come nel legacy.
 *
 * Correzione rispetto al legacy (§A.12.22): l'ultima voce del file c'è anche quando non ha righe `DR` (il legacy
 * la scarta). La descrizione ripulita della enzymeTable è {@see EnzymeEntry::legacyDescription()}.
 */
final readonly class EnzymeDatParser
{
    private const string EC_NUMBER = '/^\d+\.\d+\.\d+\.n?\d+$/';

    /**
     * Data della release dall'intestazione (`CC   Release of 02-Sep-2026`), null se assente.
     *
     * @param iterable<string> $lines
     */
    public function release(iterable $lines): ?string
    {
        foreach ($lines as $line) {
            if (str_starts_with($line, 'ID')) {
                break;
            }

            if (preg_match('/^CC\s+Release of\s+(\S+)/', $line, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidEnzymeDatException
     *
     * @return Generator<int, EnzymeEntry> riga dell'`ID` → voce
     */
    public function entries(iterable $lines): Generator
    {
        $number = 0;
        $entry = null;

        foreach ($lines as $line) {
            ++$number;

            if (preg_match('/^(ID|DE|DR|\/\/)(?:\s+(.*?))?\s*$/', $line, $match) !== 1) {
                continue;
            }

            [, $tag] = $match;
            $value = $match[2] ?? '';

            if ($tag === 'ID') {
                if ($entry !== null) {
                    yield $entry['line'] => $this->entry($entry);
                }

                if (preg_match(self::EC_NUMBER, $value) !== 1) {
                    throw InvalidEnzymeDatException::atLine($number, \sprintf('malformed EC number "%s"', $value));
                }

                $entry = ['line' => $number, 'ec' => $value, 'description' => [], 'accessions' => []];
            } elseif ($tag === '//') {
                if ($entry !== null) {
                    yield $entry['line'] => $this->entry($entry);
                }

                $entry = null;
            } elseif ($entry === null) {
                throw InvalidEnzymeDatException::atLine($number, \sprintf('"%s" line outside an entry', $tag));
            } elseif ($tag === 'DE') {
                $entry['description'][] = $value;
            } else {
                array_push($entry['accessions'], ...$this->accessions($value));
            }
        }

        if ($entry !== null) {
            yield $entry['line'] => $this->entry($entry);
        }
    }

    /**
     * @param array{line: int, ec: string, description: list<string>, accessions: list<string>} $entry
     */
    private function entry(array $entry): EnzymeEntry
    {
        if ($entry['description'] === []) {
            throw InvalidEnzymeDatException::atLine($entry['line'], \sprintf('entry %s has no "DE" line', $entry['ec']));
        }

        return new EnzymeEntry($entry['ec'], $entry['description'], $entry['accessions']);
    }

    /**
     * Accession di una riga `DR`: il primo campo di ogni coppia `accession, nome` chiusa da `;`.
     *
     * @return list<string>
     */
    private function accessions(string $value): array
    {
        $accessions = [];

        foreach (explode(';', $value) as $pair) {
            $accession = trim(explode(',', $pair, 2)[0]);

            if ($accession !== '') {
                $accessions[] = $accession;
            }
        }

        return $accessions;
    }
}
