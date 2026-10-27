<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Header FASTA dei cluster UniRef, come li legge `createUnirefDB` di Annocript (LIB/db_creator.pm): ID,
 * descrizione fino all'ultimo ` n=` (con l'eventuale prefisso `Cluster: ` dei file anteriori al 2017),
 * numero di membri, tassonomia (`Tax=` o `tax=`) e `RepID`.
 *
 * Correzioni rispetto al legacy (§A.12.2): la tassonomia si ferma prima di `TaxID=` (il legacy la include, e
 * senza `RepID` include tutto il resto della riga), `TaxID` è salvato, i campi assenti sono `null` e un header
 * senza `n=` non ripete ID e descrizione dell'header precedente.
 */
final readonly class UniRefHeaderParser
{
    private const array TAGS = ['n', 'Tax', 'tax', 'TaxID', 'RepID'];

    /**
     * Header di un FASTA (o dell'estratto delle sue righe `>`), in ordine: le righe di sequenza si saltano.
     *
     * @param iterable<string> $lines
     *
     * @throws InvalidProteinHeaderException con il numero di riga
     *
     * @return Generator<int, UniRefHeader> numero di riga → header
     */
    public function headers(iterable $lines): Generator
    {
        $number = 0;

        foreach ($lines as $line) {
            ++$number;

            if (!str_starts_with($line, '>')) {
                continue;
            }

            try {
                yield $number => $this->parse($line);
            } catch (InvalidProteinHeaderException $exception) {
                throw InvalidProteinHeaderException::atLine($number, $exception);
            }
        }
    }

    /**
     * @throws InvalidProteinHeaderException
     */
    public function parse(string $header): UniRefHeader
    {
        $tags = HeaderTags::split($header, self::TAGS, 'n');

        if (preg_match('/^(UniRef(?:50|90|100))_\S+$/', $tags->identifier, $id) !== 1) {
            throw InvalidProteinHeaderException::malformedIdentifier($tags->identifier, '"UniRef50_…", "UniRef90_…" or "UniRef100_…"');
        }

        return new UniRefHeader(
            $tags->identifier,
            UnirefVariant::from($id[1]),
            $tags->description,
            $tags->integer('n'),
            $tags->value('Tax') ?? $tags->value('tax'),
            $tags->integer('TaxID'),
            $tags->value('RepID'),
        );
    }
}
