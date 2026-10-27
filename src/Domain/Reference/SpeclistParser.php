<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use Generator;

/**
 * Codici di specie di `speclist.txt` (UniProt): righe `CODICE K  TAXON: N=nome ufficiale`, seguite da
 * `C=nome comune` e `S=sinonimo` indentati; i codici della seconda sezione sono "virtuali".
 *
 * Il legacy non lo analizza: lo scarica e lo consulta con `grep 'N=' | grep -i` (menu `--utils`, "Search
 * organism"); qui diventa il catalogo degli organismi.
 */
final readonly class SpeclistParser
{
    private const string FILE = 'speclist.txt';

    /**
     * @param iterable<string> $lines
     */
    public function release(iterable $lines): ?string
    {
        return UniProtDocumentHeader::release($lines);
    }

    /**
     * @param iterable<string> $lines
     *
     * @throws InvalidDomainFileException
     *
     * @return Generator<int, SpeciesCode> riga del codice → codice
     */
    public function codes(iterable $lines): Generator
    {
        $number = 0;
        $current = null;
        $virtual = false;

        foreach ($lines as $line) {
            ++$number;
            $line = rtrim($line, "\r\n");

            if (preg_match('/^([A-Z0-9]{1,5})\s+([A-Z])\s+(\d+):\s+N=(.+)$/', $line, $match) === 1) {
                if ($current !== null) {
                    yield $current['line'] => $this->code($current);
                }

                $current = ['line' => $number, 'code' => $match[1], 'kingdom' => $match[2], 'taxon' => (int) $match[3], 'N' => $match[4], 'C' => null, 'S' => null, 'virtual' => $virtual];

                continue;
            }

            if ($current !== null && preg_match('/^\s{2,}([CS])=(.+)$/', $line, $match) === 1) {
                $tag = $match[1] === 'C' ? 'C' : 'S';

                if ($current[$tag] !== null) {
                    throw InvalidDomainFileException::atLine(self::FILE, $number, \sprintf('repeated "%s=" for %s', $tag, $current['code']));
                }

                $current[$tag] = $match[2];

                continue;
            }

            if ($current !== null) {
                yield $current['line'] => $this->code($current);
                $current = null;
            }

            if (str_starts_with($line, '(2)')) {
                $virtual = true;
            }
        }

        if ($current !== null) {
            yield $current['line'] => $this->code($current);
        }
    }

    /**
     * @param array{line: int, code: string, kingdom: string, taxon: int, N: string, C: ?string, S: ?string, virtual: bool} $entry
     */
    private function code(array $entry): SpeciesCode
    {
        return new SpeciesCode($entry['code'], $entry['kingdom'], $entry['taxon'], trim($entry['N']), $entry['C'] === null ? null : trim($entry['C']), $entry['S'] === null ? null : trim($entry['S']), $entry['virtual']);
    }
}
