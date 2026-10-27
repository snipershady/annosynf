<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Versione di una release UniProt dal suo file di versione, come `getDBVersion` di Annocript
 * (LIB/annocript_management.pm):
 *
 * - UniProtKB, `reldate.txt`: `UniProt Knowledgebase Release 2026_03 consists of:`; la data viene dalla riga
 *   `UniProtKB/Swiss-Prot Release 2026_03 of 02-Sep-2026`;
 * - UniRef, `uniref<NN>.release_note`: `Release: 2026_03, 02-Sep-2026`, più variante
 *   (`UniProt Reference Clusters - UniRef90`) e numero di cluster.
 *
 * Correzioni rispetto al legacy (§A.12.21), che accetta come versione qualunque testo catturato e la usa nel
 * nome del database: la versione deve essere `YYYY_MM`; la riga della versione si cerca in tutto il file (il
 * legacy legge solo la prima riga di `reldate.txt`); in UniRef la data è facoltativa e i campi dopo la data si
 * ignorano; due versioni diverse nello stesso file sono un errore (il legacy tiene l'ultima).
 */
final readonly class ReleaseNoteParser
{
    private const string VERSION = '/^\d{4}_(?:0[1-9]|1[0-2])$/';

    private const string DATE_FORMAT = 'd-M-Y';

    /**
     * @param iterable<string> $lines righe del file, con o senza terminatore
     *
     * @throws InvalidReleaseNoteException
     */
    public function parse(ReleaseType $type, iterable $lines): ReleaseNote
    {
        return match ($type) {
            ReleaseType::UniProtKb => $this->uniProtKb($lines),
            ReleaseType::UniRef => $this->uniRef($lines),
        };
    }

    /**
     * @param iterable<string> $lines
     */
    private function uniProtKb(iterable $lines): ReleaseNote
    {
        $type = ReleaseType::UniProtKb;
        $version = null;
        $dates = [];

        foreach ($lines as $line) {
            if (preg_match('/Knowledgebase\sRelease\s(.*)\sconsists/', $line, $match) === 1) {
                $version = $this->version($type, $version, $match[1]);
            } elseif (preg_match('#^UniProtKB/Swiss-Prot\s+Release\s+(\S+)\s+of\s+(\S+)#', $line, $match) === 1) {
                $dates[$match[1]] ??= $match[2];
            }
        }

        if ($version === null) {
            throw InvalidReleaseNoteException::missingVersion($type);
        }

        $date = $dates[$version] ?? null;

        return new ReleaseNote($type, $version, $date === null ? null : $this->date($type, $date));
    }

    /**
     * @param iterable<string> $lines
     */
    private function uniRef(iterable $lines): ReleaseNote
    {
        $type = ReleaseType::UniRef;
        $version = null;
        $date = null;
        $variant = null;
        $clusters = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s*Release:\s*([^,\s]+)(?:\s*,\s*([^,\s]+))?/', $line, $match) === 1) {
                $version = $this->version($type, $version, $match[1]);
                $date ??= $match[2] ?? null;
            } elseif (preg_match('/UniProt Reference Clusters - (\S+)/', $line, $match) === 1) {
                $variant = UnirefVariant::tryFrom($match[1]) ?? throw InvalidReleaseNoteException::malformed($type, 'UniRef variant', $match[1]);
            } elseif (preg_match('/Number of clusters:\s*(\S+)/', $line, $match) === 1) {
                $clusters = $this->count($type, $match[1]);
            }
        }

        if ($version === null) {
            throw InvalidReleaseNoteException::missingVersion($type);
        }

        return new ReleaseNote($type, $version, $date === null ? null : $this->date($type, $date), $variant, $clusters);
    }

    private function version(ReleaseType $type, ?string $current, string $found): string
    {
        if (preg_match(self::VERSION, $found) !== 1) {
            throw InvalidReleaseNoteException::malformed($type, 'version', $found);
        }

        if ($current !== null && $current !== $found) {
            throw InvalidReleaseNoteException::conflictingVersions($type, $current, $found);
        }

        return $found;
    }

    private function date(ReleaseType $type, string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value, new DateTimeZone('UTC'));

        // createFromFormat accetta anche date fuori calendario (31-Feb) spostandole: il confronto le scarta.
        if ($date === false || $date->format(self::DATE_FORMAT) !== $value) {
            throw InvalidReleaseNoteException::malformed($type, 'date', $value);
        }

        return $date;
    }

    private function count(ReleaseType $type, string $value): int
    {
        if (preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)$/', $value) !== 1) {
            throw InvalidReleaseNoteException::malformed($type, 'cluster count', $value);
        }

        return (int) str_replace(',', '', $value);
    }
}
