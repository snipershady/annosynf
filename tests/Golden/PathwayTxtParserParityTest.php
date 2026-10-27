<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pathway identici a createPathwayTableAndMapping (golden/reference/pathways_*.tsv): la pathwaysTable (id
 * progressivo, descrizione) e il mapping accession → pathway, sugli estratti di pathway.txt in
 * tests/Fixtures/reference/pathway, salvo le correzioni dichiarate di §A.12.5 e §A.12.23.
 */
#[CoversClass(PathwayTxtParser::class)]
#[CoversClass(Pathway::class)]
final class PathwayTxtParserParityTest extends TestCase
{
    /**
     * File → [righe della pathwaysTable che il port non scrive, righe del mapping che il port scrive diverse].
     */
    private const array CORRECTIONS = [
        // il piè di pagina, preso dal legacy per due pathway
        'pathway' => [[7, 8], [
            // accession ripetuta nello stesso pathway (Alkaloid biosynthesis, file reale): il legacy la mappa due volte
            'A0A2H5AIZ9' => '5,',
        ]],
        'pathway_without_proteins' => [[5, 6], [
            // accession ripetuta nello stesso pathway: il legacy la mappa due volte
            'P0A6L2' => '2,',
        ]],
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
    public function testThePathwaysTableMatchesAnnocript(string $file): void
    {
        $expected = [];

        foreach (GoldenFixtures::lines(\sprintf('golden/reference/pathways_%s.tsv', $file)) as $line) {
            [$id, $description] = explode("\t", $line, 2);
            $expected[(int) $id] = $description;
        }

        foreach (self::CORRECTIONS[$file][0] as $id) {
            self::assertMatchesRegularExpression('/^(Copyrighted|Distributed) /', $expected[$id] ?? '', 'only the footer is dropped');
            unset($expected[$id]);
        }

        $actual = array_map(static fn (Pathway $pathway): string => $pathway->description, $this->pathways($file));

        $this->assertSameRows(array_values($expected), $actual);
    }

    #[DataProvider('files')]
    public function testTheAccessionMappingMatchesAnnocript(string $file): void
    {
        $expected = [];

        foreach (GoldenFixtures::lines(\sprintf('golden/reference/pathways_%s.tsv.mapping', $file)) as $line) {
            [$accession, $pathways] = explode("\t", $line, 2);
            $expected[$accession] = $pathways;
        }

        foreach (self::CORRECTIONS[$file][1] as $accession => $pathways) {
            self::assertNotSame($expected[$accession] ?? null, $pathways, 'a declared correction must differ from the legacy');
            $expected[$accession] = $pathways;
        }

        $mapping = [];

        foreach ($this->pathways($file) as $index => $pathway) {
            foreach ($pathway->accessions as $accession) {
                $mapping[$accession] ??= [];

                if (!\in_array($index + 1, $mapping[$accession], true)) {
                    $mapping[$accession][] = $index + 1;
                }
            }
        }

        ksort($mapping, \SORT_STRING);
        $actual = array_map(static fn (string $accession, array $pathways): string => $accession . "\t" . implode(',', $pathways) . ',', array_keys($mapping), $mapping);
        $expectedRows = array_map(static fn (string $accession, string $pathways): string => $accession . "\t" . $pathways, array_keys($expected), $expected);

        $this->assertSameRows($expectedRows, $actual);
    }

    public function testReleaseAndLevels(): void
    {
        $parser = new PathwayTxtParser();
        $pathways = $this->pathways('pathway');

        self::assertSame('2026_03', $parser->release(GoldenFixtures::lines('reference/pathway/pathway.txt')));
        self::assertSame(['Polyol metabolism', '(R,R)-butane-2,3-diol biosynthesis', '(R,R)-butane-2,3-diol from pyruvate: step 1/3'], $pathways[2]->levels());
        self::assertSame(['Alkaloid biosynthesis'], $pathways[4]->levels());
    }

    /**
     * @return list<Pathway>
     */
    private function pathways(string $file): array
    {
        return iterator_to_array(new PathwayTxtParser()->pathways(GoldenFixtures::lines(\sprintf('reference/pathway/%s.txt', $file))), false);
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
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
