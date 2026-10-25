<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\CpcOrf;
use App\Domain\CodingPotential\CpcOrfFinder;
use App\Domain\CodingPotential\OrfIntegrity;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\CpcOrfRow;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * ORF identico a quello di FindCDS di CPC2-beta (solo strand +) su trial, example e sui casi limite di
 * cpc2_orf_cases.fasta: inizio, integrity e nucleotidi.
 */
#[CoversClass(CpcOrfFinder::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(OrfIntegrity::class)]
#[UsesClass(TextFileReader::class)]
final class CpcOrfFinderParityTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function inputs(): Generator
    {
        foreach (GoldenFixtures::SETS as $set => $fasta) {
            yield $set => [$fasta, \sprintf('golden/%s/cpc2_orf.tsv', $set)];
        }

        yield 'cpc2_cases' => [GoldenFixtures::CPC2_CASES, 'golden/cpc2_cases/cpc2_orf.tsv'];
    }

    #[DataProvider('inputs')]
    public function testEveryOrfMatchesCpc2(string $fasta, string $golden): void
    {
        $finder = new CpcOrfFinder();
        $rows = array_map(CpcOrfRow::fromLine(...), iterator_to_array(GoldenFixtures::lines($golden), false));
        $comparison = new GoldenComparison();

        foreach (new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($fasta))) as $record) {
            $row = array_shift($rows);
            self::assertNotNull($row, \sprintf('cpc2orf has no line for %s', $record->id()));
            $orf = $finder->find($record->sequence);
            $id = $record->id();

            $comparison->same($id, 'id', $row->id, $id);
            $comparison->same($id, 'dna', $row->dna, $orf->dna ?? '');
            // FindCDS conta da 0 e, senza ORF, restituisce inizio 0 e integrity 0.
            $comparison->same($id, 'start', $row->start, $orf instanceof CpcOrf ? $orf->start - 1 : 0);
            $comparison->same($id, 'integrity', $row->integrity, $orf->integrity->value ?? 0);
        }

        self::assertSame([], $rows, 'every line of cpc2orf has its sequence');
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
