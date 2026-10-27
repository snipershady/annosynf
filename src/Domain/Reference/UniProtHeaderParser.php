<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Header FASTA di Swiss-Prot e TrEMBL, come li legge `createUniprotKBDB` di Annocript (LIB/db_creator.pm):
 * descrizione fino all'ultimo ` OS=`, `db|accession|nome` dall'ID, poi organismo, gene, PE e SV.
 *
 * Correzioni rispetto al legacy (§A.12.1): l'organismo è intero anche quando nessun tag lo segue (il legacy ne
 * prende un carattere), `OX` è salvato, il gene name è intero anche con spazi (`GN=MGF 110-1L`, il legacy tiene
 * `MGF`), i campi assenti sono `null` e un header senza `OS=` dà un organismo `null` invece di ripetere ID e
 * descrizione dell'header precedente.
 */
final readonly class UniProtHeaderParser
{
    private const array TAGS = ['OS', 'OX', 'GN', 'PE', 'SV'];

    /**
     * Header di un FASTA (o dell'estratto delle sue righe `>`), in ordine: le righe di sequenza si saltano.
     *
     * @param iterable<string> $lines
     *
     * @throws InvalidProteinHeaderException con il numero di riga
     *
     * @return Generator<int, UniProtHeader> numero di riga → header
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
    public function parse(string $header): UniProtHeader
    {
        $tags = HeaderTags::split($header, self::TAGS, 'OS');

        if (preg_match('/^(sp|tr)\|([^|]+)\|([^|]+)$/', $tags->identifier, $id) !== 1) {
            throw InvalidProteinHeaderException::malformedIdentifier($tags->identifier, '"sp|ACCESSION|NAME" or "tr|ACCESSION|NAME"');
        }

        return new UniProtHeader(
            ProteinSource::from($id[1]),
            $tags->identifier,
            $id[2],
            $id[3],
            $tags->description,
            $tags->value('OS'),
            $tags->integer('OX'),
            $tags->value('GN'),
            $tags->integer('PE'),
            $tags->integer('SV'),
        );
    }
}
