<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Termini della Gene Ontology da un file OBO (`go-basic.obo`), come li legge `createGOTable` di Annocript
 * (LIB/db_creator.pm): `id`, `name`, `namespace` e `alt_id` di ogni stanza `[Term]`, obsoleti inclusi. In più
 * si legge `is_obsolete`, che il legacy ignora.
 *
 * Correzioni rispetto al legacy (§A.12.3): l'ultima stanza `[Term]` non va persa; le stanze `[Typedef]` (e
 * ogni altra stanza non `[Term]`) si ignorano invece di sovrascrivere il termine aperto; il valore di un tag è
 * tutto il testo dopo `tag: ` (il legacy spezza su ogni `": "` e tiene il secondo pezzo, quindi tronca nomi come
 * `caffeoyl-CoA: alcohol caffeoyl transferase activity`).
 */
final readonly class OboParser
{
    private const string GO_ID = '/^GO:\d{7}$/';

    /**
     * Valore di `data-version:` nell'intestazione (prima della prima stanza), null se assente.
     *
     * @param iterable<string> $lines
     */
    public function dataVersion(iterable $lines): ?string
    {
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");

            if (str_starts_with($line, '[')) {
                break;
            }

            if (str_starts_with($line, 'data-version:')) {
                $version = trim(substr($line, \strlen('data-version:')));

                return $version === '' ? null : $version;
            }
        }

        return null;
    }

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidOboException
     *
     * @return Generator<int, GoTerm> riga della stanza `[Term]` → termine
     */
    public function terms(iterable $lines): Generator
    {
        $number = 0;
        $term = null;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");

            if (preg_match('/^\[([^\]]*)\]\s*$/', $line, $stanza) === 1) {
                if ($term !== null) {
                    yield $term['line'] => $this->term($term);
                }

                $term = $stanza[1] === 'Term' ? ['line' => $number, 'tags' => []] : null;

                continue;
            }

            if ($term === null || trim($line) === '' || str_starts_with($line, '!')) {
                continue;
            }

            if (preg_match('/^([^:\s]+):\s*(.*?)\s*$/', $line, $tag) !== 1) {
                throw InvalidOboException::atLine($number, \sprintf('expected "tag: value", found "%s"', $line));
            }

            $term['tags'][] = [$number, $tag[1], $tag[2]];
        }

        if ($term !== null) {
            yield $term['line'] => $this->term($term);
        }
    }

    /**
     * @param array{line: int, tags: list<array{int, string, string}>} $stanza
     */
    private function term(array $stanza): GoTerm
    {
        $single = [];
        $altIds = [];

        foreach ($stanza['tags'] as [$number, $tag, $value]) {
            if ($tag === 'alt_id') {
                $altIds[] = $this->goId($number, $tag, $value);
            } elseif (\in_array($tag, ['id', 'name', 'namespace', 'is_obsolete'], true)) {
                if (isset($single[$tag])) {
                    throw InvalidOboException::atLine($number, \sprintf('repeated "%s" in the term at line %d', $tag, $stanza['line']));
                }

                $single[$tag] = [$number, $value];
            }
        }

        foreach (['id', 'name', 'namespace'] as $required) {
            if (!isset($single[$required])) {
                throw InvalidOboException::atLine($stanza['line'], \sprintf('the term has no "%s"', $required));
            }
        }

        [$namespaceLine, $namespace] = $single['namespace'];
        [$obsoleteLine, $obsolete] = $single['is_obsolete'] ?? [0, 'false'];

        if (!\in_array($obsolete, ['true', 'false'], true)) {
            throw InvalidOboException::atLine($obsoleteLine, \sprintf('is_obsolete must be "true" or "false", found "%s"', $obsolete));
        }

        return new GoTerm(
            $this->goId($single['id'][0], 'id', $single['id'][1]),
            $single['name'][1],
            GoNamespace::fromOboName($namespace) ?? throw InvalidOboException::atLine($namespaceLine, \sprintf('unknown GO namespace "%s"', $namespace)),
            $obsolete === 'true',
            $altIds,
        );
    }

    private function goId(int $number, string $tag, string $value): string
    {
        if (preg_match(self::GO_ID, $value) !== 1) {
            throw InvalidOboException::atLine($number, \sprintf('malformed GO identifier in "%s": "%s"', $tag, $value));
        }

        return $value;
    }
}
