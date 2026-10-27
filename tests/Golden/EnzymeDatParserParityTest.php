<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\EnzymeDatParser;
use App\Domain\Reference\EnzymeEntry;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Enzimi identici a createEnzymeTable (golden/reference/enzymes_*.tsv): la enzymeTable (id progressivo, EC,
 * descrizione ripulita), il mapping accession → enzimi e la versione del file, sugli estratti di enzyme.dat in
 * tests/Fixtures/reference/enzyme, salvo la correzione dichiarata di §A.12.22.
 */
#[CoversClass(EnzymeDatParser::class)]
#[CoversClass(EnzymeEntry::class)]
final class EnzymeDatParserParityTest extends TestCase
{
    /**
     * File → righe della enzymeTable che il legacy non scrive (id progressivo → riga).
     */
    private const array CORRECTIONS = [
        'enzyme' => [],
        // l'ultima voce non ha DR: il legacy la scarta
        'enzyme_last_without_dr' => [2 => "2\t1.1.1.74\tDeleted entry."],
    ];

    /**
     * @return Generator<string, array{string}>
     */
    public static function files(): Generator
    {
        foreach (array_keys(self::CORRECTIONS) as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('files')]
    public function testTheEnzymeTableMatchesAnnocript(string $file): void
    {
        $expected = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/enzymes_%s.tsv', $file)), false);

        foreach (self::CORRECTIONS[$file] as $id => $row) {
            self::assertArrayNotHasKey($id - 1, $expected, 'a declared correction must be a row the legacy does not write');
            $expected[$id - 1] = $row;
        }

        $actual = [];

        foreach ($this->entries($file) as $index => $entry) {
            $actual[] = implode("\t", [$index + 1, $entry->ecNumber, $entry->legacyDescription()]);
        }

        $this->assertSameRows($expected, $actual);
    }

    #[DataProvider('files')]
    public function testTheAccessionMappingMatchesAnnocript(string $file): void
    {
        $mapping = [];

        foreach ($this->entries($file) as $index => $entry) {
            foreach ($entry->accessions as $accession) {
                $mapping[$accession] ??= [];

                if (!\in_array($index + 1, $mapping[$accession], true)) {
                    $mapping[$accession][] = $index + 1;
                }
            }
        }

        ksort($mapping, \SORT_STRING);
        $actual = [];

        foreach ($mapping as $accession => $enzymes) {
            $actual[] = $accession . "\t" . implode(',', $enzymes) . ',';
        }

        $this->assertSameRows(iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/enzymes_%s.tsv.mapping', $file)), false), $actual);
    }

    #[DataProvider('files')]
    public function testTheReleaseMatchesAnnocript(string $file): void
    {
        $legacy = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/enzymes_%s.tsv.version', $file)), false)[0] ?? '';

        self::assertSame("enzyme.dat\t Release of 02-Sep-2026", $legacy);
        self::assertSame('02-Sep-2026', new EnzymeDatParser()->release(GoldenFixtures::lines(\sprintf('reference/enzyme/%s.dat', $file))));
    }

    /**
     * @return list<EnzymeEntry>
     */
    private function entries(string $file): array
    {
        return iterator_to_array(new EnzymeDatParser()->entries(GoldenFixtures::lines(\sprintf('reference/enzyme/%s.dat', $file))), false);
    }

    /**
     * @param array<int, string> $expected
     * @param list<string>       $actual
     */
    private function assertSameRows(array $expected, array $actual): void
    {
        $comparison = new GoldenComparison();

        foreach ($expected as $index => $row) {
            $comparison->same((string) ($index + 1), 'row', $row, $actual[$index] ?? '(missing)');
        }

        self::assertCount(\count($expected), $actual);
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
