<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Scompone un header FASTA di UniProt nella forma `>ID descrizione TAG=valore TAG=valore…`, comune a UniProtKB
 * (`OS= OX= GN= PE= SV=`) e UniRef (`n= Tax= TaxID= RepID=`).
 *
 * La descrizione va dallo spazio dopo l'ID all'ultima occorrenza del tag che la chiude nel legacy, perché
 * Annocript la cattura con un `(.*)` greedy (`\sOS=` per UniProtKB, `\sn=` per UniRef); senza quel tag, fino al
 * primo tag noto. Ogni valore va dal suo tag al tag noto successivo, così i valori con spazi restano interi
 * (organismi, gene name come `MGF 110-1L`); se un tag compare due volte vale il primo.
 */
final readonly class HeaderTags
{
    /**
     * @param string                $identifier primo token dopo `>`
     * @param array<string, string> $values     tag → valore (senza spazi ai bordi), solo i tag presenti
     */
    private function __construct(
        public string $identifier,
        public string $description,
        public array $values,
    ) {
    }

    /**
     * @param string       $header         riga dell'header, con o senza terminatore
     * @param list<string> $tags           tag riconosciuti, già quotati per una regex
     * @param string       $descriptionEnd tag che chiude la descrizione (uno di $tags)
     *
     * @throws InvalidProteinHeaderException se la riga non è un header
     */
    public static function split(string $header, array $tags, string $descriptionEnd): self
    {
        $header = rtrim($header, "\r\n");

        if (preg_match('/^>(\S+)/', $header, $match) !== 1) {
            throw InvalidProteinHeaderException::notAHeader($header);
        }

        $identifier = $match[1];
        $start = \strlen($identifier) + 2;
        preg_match_all('/\s(' . implode('|', $tags) . ')=/', $header, $found, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        $first = 0;

        foreach ($found as $index => $tag) {
            if ($tag[1][0] === $descriptionEnd) {
                $first = $index;
            }
        }

        $descriptionLength = isset($found[$first]) ? $found[$first][0][1] - $start : null;
        $description = $descriptionLength === null ? substr($header, $start) : substr($header, $start, max(0, $descriptionLength));
        $values = [];

        for ($index = $first, $count = \count($found); $index < $count; ++$index) {
            $valueStart = $found[$index][0][1] + \strlen($found[$index][0][0]);
            $valueEnd = $found[$index + 1][0][1] ?? \strlen($header);
            $values[$found[$index][1][0]] ??= trim(substr($header, $valueStart, $valueEnd - $valueStart));
        }

        return new self($identifier, $description, $values);
    }

    public function value(string $tag): ?string
    {
        $value = $this->values[$tag] ?? '';

        return $value === '' ? null : $value;
    }

    /**
     * @throws InvalidProteinHeaderException se il valore non è un intero senza segno
     */
    public function integer(string $tag): ?int
    {
        $value = $this->value($tag);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{1,18}$/', $value) !== 1) {
            throw InvalidProteinHeaderException::malformedTag($this->identifier, $tag, $value);
        }

        return (int) $value;
    }
}
