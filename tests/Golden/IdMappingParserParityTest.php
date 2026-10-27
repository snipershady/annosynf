<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\IdMappingParser;
use App\Domain\Reference\UnirefVariant;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GO per proteina (uniprotkb) e per cluster UniRef (unione dei GO dei membri, nell'ordine di prima comparsa)
 * identici a extractFilteredMappingFile + createUniprotGO (golden/reference/idmapping_*.tsv) sulle righe reali
 * di tests/Fixtures/reference/idmapping/idmapping_selected.tab.
 */
#[CoversClass(IdMappingParser::class)]
#[CoversClass(IdMappingEntry::class)]
final class IdMappingParserParityTest extends TestCase
{
    /**
     * @return Generator<string, array{string, ?UnirefVariant}>
     */
    public static function targets(): Generator
    {
        yield 'uniprotkb' => ['uniprotkb', null];

        foreach (UnirefVariant::cases() as $variant) {
            yield $variant->value => [$variant->value, $variant];
        }
    }

    #[DataProvider('targets')]
    public function testTheGoMappingMatchesAnnocript(string $target, ?UnirefVariant $variant): void
    {
        $mapping = [];

        foreach (new IdMappingParser()->entries(GoldenFixtures::lines('reference/idmapping/idmapping_selected.tab')) as $entry) {
            $key = $variant instanceof UnirefVariant ? $entry->cluster($variant) : $entry->accession;

            if ($key === null) {
                continue;
            }

            foreach ($entry->goIds as $goId) {
                if (!\in_array($goId, $mapping[$key] ?? [], true)) {
                    $mapping[$key][] = $goId;
                }
            }
        }

        ksort($mapping, \SORT_STRING);
        $actual = array_map(static fn (string $key, array $goIds): string => $key . "\t" . implode(',', $goIds) . ',', array_keys($mapping), $mapping);
        $expected = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/idmapping_%s.tsv', $target)), false);
        $comparison = new GoldenComparison();

        foreach ($expected as $index => $row) {
            $comparison->same((string) ($index + 1), 'row', $row, $actual[$index] ?? '(missing)');
        }

        self::assertCount(\count($expected), $actual);
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
