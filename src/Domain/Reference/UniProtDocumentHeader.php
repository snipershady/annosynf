<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Intestazione dei documenti di UniProt (`pathway.txt`, `speclist.txt`): blocchi separati da righe di trattini,
 * con la riga `Release:     2026_03 of 02-Sep-2026` nel secondo.
 */
final class UniProtDocumentHeader
{
    private const string SEPARATOR = '/^-{10,}\s*$/';

    public static function isSeparator(string $line): bool
    {
        return preg_match(self::SEPARATOR, $line) === 1;
    }

    /**
     * Versione della release (`2026_03`) cercata prima del terzo separatore, null se assente.
     *
     * @param iterable<string> $lines
     */
    public static function release(iterable $lines): ?string
    {
        $separators = 0;

        foreach ($lines as $line) {
            if (self::isSeparator($line) && ++$separators === 3) {
                break;
            }

            if (preg_match('/^Release:\s+(\S+)/', $line, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }
}
