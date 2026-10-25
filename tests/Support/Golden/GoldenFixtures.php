<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use Generator;
use LogicException;
use SplFileObject;

/**
 * Accesso in sola lettura ai fixture golden (tests/Fixtures/golden, vedi il README lì dentro) e ai FASTA da
 * cui sono stati prodotti. I percorsi sono relativi a tests/Fixtures, come in golden/SHA256SUMS.
 */
final class GoldenFixtures
{
    public const string FIXTURES_DIRECTORY = __DIR__ . '/../../Fixtures';

    public const string CHECKSUMS = 'golden/SHA256SUMS';

    /** Insiemi di dati golden: nome → FASTA di input. */
    public const array SETS = [
        'example' => 'fasta/example_transcriptome.fasta',
        'trial' => 'fasta/trial_transcriptome.fasta',
    ];

    /** Casi limite dell'ORF di CPC2: golden in golden/cpc2_cases. */
    public const string CPC2_CASES = 'fasta/cpc2_orf_cases.fasta';

    /**
     * @throws LogicException se il file non esiste
     */
    public static function path(string $relative): string
    {
        $path = self::FIXTURES_DIRECTORY . '/' . $relative;

        if (!is_file($path)) {
            throw new LogicException(\sprintf('Golden fixture "%s" not found: regenerate it with tools/legacy-runner/golden-ports.sh.', $relative));
        }

        return $path;
    }

    /**
     * Righe del file senza il terminatore, in streaming.
     *
     * @return Generator<int, string>
     */
    public static function lines(string $relative): Generator
    {
        $file = new SplFileObject(self::path($relative));
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        foreach ($file as $number => $line) {
            if (\is_string($line) && ($line !== '' || !$file->eof())) {
                yield $number + 1 => $line;
            }
        }
    }

    /**
     * Checksum registrati in golden/SHA256SUMS.
     *
     * @throws LogicException se una riga non è nel formato di sha256sum
     *
     * @return array<string, string> percorso relativo → sha256
     */
    public static function checksums(): array
    {
        $checksums = [];

        foreach (self::lines(self::CHECKSUMS) as $number => $line) {
            if (preg_match('/^([0-9a-f]{64}) {2}(\S.*)$/', $line, $match) !== 1) {
                throw new LogicException(\sprintf('%s:%d is not a sha256sum line.', self::CHECKSUMS, $number));
            }

            $checksums[$match[2]] = $match[1];
        }

        return $checksums;
    }

    /**
     * Identificativi delle sequenze del FASTA di un insieme, nell'ordine del file: il primo token
     * dell'header, come il cleanHeader di Annocript.
     *
     * @return list<string>
     */
    public static function sequenceIds(string $set): array
    {
        return self::fastaIds(self::fasta($set));
    }

    /**
     * Come {@see self::sequenceIds()}, per un FASTA qualunque dei fixture.
     *
     * @return list<string>
     */
    public static function fastaIds(string $relative): array
    {
        $ids = [];

        foreach (self::lines($relative) as $line) {
            if (str_starts_with($line, '>')) {
                $ids[] = preg_split('/\s+/', substr($line, 1), 2)[0] ?? '';
            }
        }

        return $ids;
    }

    /**
     * @throws LogicException se l'insieme non esiste
     */
    public static function fasta(string $set): string
    {
        return self::SETS[$set] ?? throw new LogicException(\sprintf('Unknown golden set "%s".', $set));
    }
}
