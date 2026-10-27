<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Pathway di `pathway.txt`, come li legge `createPathwayTableAndMapping` di Annocript (LIB/db_creator.pm):
 * una riga che inizia con una lettera o una cifra è un pathway (senza il punto finale), le righe indentate
 * che seguono elencano `NOME_SPECIE (ACCESSION)` separati da virgole.
 *
 * Il file è diviso in blocchi da righe di trattini (intestazione, elenco, piè di pagina): i pathway sono le
 * descrizioni dei blocchi che contengono accession. Il legacy invece salta 12 righe fisse
 * (`pwFileLinesToJump`) e prende per pathway anche le due righe del piè di pagina (`Copyrighted by …`,
 * `Distributed under …`): correzione §A.12.5.
 */
final readonly class PathwayTxtParser
{
    /**
     * Versione della release dall'intestazione (`Release:     2026_03 of 02-Sep-2026`), null se assente.
     *
     * @param iterable<string> $lines
     */
    public function release(iterable $lines): ?string
    {
        return UniProtDocumentHeader::release($lines);
    }

    /**
     * @param iterable<string> $lines
     *
     * @return Generator<int, Pathway> riga della descrizione → pathway
     */
    public function pathways(iterable $lines): Generator
    {
        $number = 0;
        // Pathway del blocco corrente in attesa di sapere se il blocco contiene accession.
        $waiting = [];
        $current = null;
        $blockHasAccessions = false;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");
            $separator = UniProtDocumentHeader::isSeparator($line);

            if ($separator || preg_match('/^[A-Za-z0-9_]/', $line) === 1) {
                if ($current !== null) {
                    $waiting[] = $current;
                }

                if ($blockHasAccessions) {
                    yield from $this->complete($waiting);
                    $waiting = [];
                }

                $current = $separator ? null : ['line' => $number, 'description' => $this->description($line), 'accessions' => []];

                if ($separator) {
                    $waiting = [];
                    $blockHasAccessions = false;
                }
            } elseif ($current !== null && str_contains($line, '(')) {
                array_push($current['accessions'], ...$this->accessions($line));
                $blockHasAccessions = true;
            }
        }

        if ($current !== null) {
            $waiting[] = $current;
        }

        if ($blockHasAccessions) {
            yield from $this->complete($waiting);
        }
    }

    /**
     * @param list<array{line: int, description: string, accessions: list<string>}> $pathways
     *
     * @return Generator<int, Pathway>
     */
    private function complete(array $pathways): Generator
    {
        foreach ($pathways as $pathway) {
            yield $pathway['line'] => new Pathway($pathway['description'], $pathway['accessions']);
        }
    }

    private function description(string $line): string
    {
        $description = rtrim($line);

        return str_ends_with($description, '.') ? substr($description, 0, -1) : $description;
    }

    /**
     * Accession tra parentesi, una per elemento separato da virgole (come il legacy).
     *
     * @return list<string>
     */
    private function accessions(string $line): array
    {
        $accessions = [];

        foreach (explode(',', $line) as $element) {
            if (preg_match('/\((.+)\)/', $element, $match) === 1) {
                $accessions[] = $match[1];
            }
        }

        return $accessions;
    }
}
