<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\InvalidReleaseNoteException;
use App\Domain\Reference\ReleaseNote;
use App\Domain\Reference\ReleaseNoteParser;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Versione della release identica a getDBVersion di Annocript (golden/reference/release_versions.tsv) sui file
 * di tests/Fixtures/reference/release, salvo le correzioni dichiarate di §A.12.21.
 */
#[CoversClass(ReleaseNoteParser::class)]
#[UsesClass(ReleaseNote::class)]
#[UsesClass(InvalidReleaseNoteException::class)]
final class ReleaseNoteParserParityTest extends TestCase
{
    private const string GOLDEN = 'golden/reference/release_versions.tsv';

    /**
     * Dove il port si discosta dal legacy: file → versione del port, "!" se il port rifiuta il file.
     */
    private const array CORRECTIONS = [
        // il legacy legge solo la prima riga di reldate.txt
        'reldate_blank_first_line.txt' => '2026_03',
        // il legacy accetta come versione tutto il testo tra "Release" e "consists"
        'reldate_malformed_version.txt' => '!',
        // il legacy esige la virgola dopo la versione
        'uniref90_no_date.release_note' => '2026_03',
        // il legacy cattura fino all'ultima virgola: "2026_03, 02-Sep-2026"
        'uniref90_extra_field.release_note' => '2026_03',
        // il legacy tiene l'ultima delle due versioni
        'uniref90_two_releases.release_note' => '!',
    ];

    /**
     * @return Generator<string, array{string, ReleaseType, string}>
     */
    public static function releaseNotes(): Generator
    {
        foreach (GoldenFixtures::lines(self::GOLDEN) as $line) {
            [$file, $type, $version] = explode("\t", $line);

            yield $file => [$file, ReleaseType::from($type), $version];
        }
    }

    #[DataProvider('releaseNotes')]
    public function testTheVersionMatchesAnnocript(string $file, ReleaseType $type, string $legacyVersion): void
    {
        $expected = self::CORRECTIONS[$file] ?? $legacyVersion;

        try {
            $version = new ReleaseNoteParser()->parse($type, GoldenFixtures::lines('reference/release/' . $file))->version;
        } catch (InvalidReleaseNoteException) {
            $version = '!';
        }

        self::assertSame($expected, $version);

        if (\array_key_exists($file, self::CORRECTIONS)) {
            self::assertNotSame($legacyVersion, $expected, 'a declared correction must differ from the legacy');
        }
    }

    public function testEveryCorrectionIsAGoldenCase(): void
    {
        $files = array_map(static fn (string $line): string => explode("\t", $line)[0], iterator_to_array(GoldenFixtures::lines(self::GOLDEN), false));

        self::assertSame([], array_diff(array_keys(self::CORRECTIONS), $files));
        self::assertCount(11, $files);
    }

    /**
     * @return Generator<string, array{string, UnirefVariant, int}>
     */
    public static function unirefNotes(): Generator
    {
        yield 'UniRef50' => ['uniref50.release_note', UnirefVariant::UniRef50, 38_840_027];
        yield 'UniRef90' => ['uniref90.release_note', UnirefVariant::UniRef90, 121_494_192];
        yield 'UniRef100' => ['uniref100.release_note', UnirefVariant::UniRef100, 221_143_266];
    }

    #[DataProvider('unirefNotes')]
    public function testRealUniRefNotes(string $file, UnirefVariant $variant, int $clusters): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniRef, GoldenFixtures::lines('reference/release/' . $file));

        self::assertSame($variant, $note->unirefVariant);
        self::assertSame($clusters, $note->clusterCount);
        self::assertSame('2026-09-02', $note->releasedOn?->format('Y-m-d'));
    }
}
