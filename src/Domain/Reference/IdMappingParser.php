<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Righe di `idmapping_selected.tab` (TSV di 22 colonne senza intestazione), come le legge Annocript
 * (`extractFilteredMappingFile` + `createUniprotGO`, LIB/db_creator.pm): accession dalla prima colonna, GO
 * (`GO:0005737; GO:0005829`) e cluster UniRef.
 *
 * Il legacy prende i GO dalla colonna 6 e trova la colonna della variante UniRef cercandone il nome nei valori
 * della prima riga che lo contiene. Qui ogni campo si riconosce dal valore, riga per riga: i GO sono il campo
 * fatto solo di identificativi GO, i cluster i campi `UniRef50_…`, `UniRef90_…`, `UniRef100_…`. Le righe senza
 * GO o senza un cluster ci sono comunque (il legacy le salta quando la chiave o i GO mancano).
 */
final readonly class IdMappingParser
{
    private const string FILE = 'idmapping_selected.tab';

    private const string GO_LIST = '/^GO:\d{7}(?:;\s*GO:\d{7})*$/';

    private const string CLUSTER = '/^(UniRef(?:50|90|100))_\S+$/';

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidDomainFileException se una riga non ha l'accession
     *
     * @return Generator<int, IdMappingEntry> numero di riga → riga
     */
    public function entries(iterable $lines): Generator
    {
        $number = 0;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            $fields = explode("\t", $line);
            $accession = array_shift($fields);

            if (preg_match('/^\S+$/', $accession) !== 1) {
                throw InvalidDomainFileException::atLine(self::FILE, $number, 'the first column must be a UniProtKB accession');
            }

            $goIds = [];
            $clusters = [];

            foreach ($fields as $field) {
                if ($goIds === [] && preg_match(self::GO_LIST, $field) === 1) {
                    $goIds = array_map(trim(...), explode(';', $field));
                } elseif (preg_match(self::CLUSTER, $field, $match) === 1) {
                    $clusters[$match[1]] ??= $field;
                }
            }

            yield $number => new IdMappingEntry($accession, $goIds, $clusters);
        }
    }
}
