<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\CpcOrfFinder;
use App\Domain\CodingPotential\FickettScore;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\Cpc2Row;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Fickett_score identico a CPC2-beta su trial, example e casi limite: confrontato come lo stampa Python 2
 * (12 cifre significative), cioè il valore che CPC2 passa anche a svm-scale.
 */
#[CoversClass(FickettScore::class)]
#[UsesClass(CpcOrfFinder::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(TextFileReader::class)]
final class FickettScoreParityTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function inputs(): Generator
    {
        foreach (GoldenFixtures::SETS as $set => $fasta) {
            yield $set => [$fasta, \sprintf('golden/%s/cpc2.tsv', $set)];
        }

        yield 'cpc2_cases' => [GoldenFixtures::CPC2_CASES, 'golden/cpc2_cases/cpc2.tsv'];
    }

    #[DataProvider('inputs')]
    public function testEveryScoreMatchesCpc2(string $fasta, string $golden): void
    {
        $fickett = new FickettScore();
        $lines = iterator_to_array(GoldenFixtures::lines($golden), false);
        array_shift($lines);
        $rows = array_map(Cpc2Row::fromLine(...), $lines);
        $comparison = new GoldenComparison();

        foreach (new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($fasta))) as $record) {
            $row = array_shift($rows);
            self::assertNotNull($row, \sprintf('CPC2 has no line for %s', $record->id()));
            $id = $record->id();

            $comparison->same($id, 'id', $row->id, $id);
            $comparison->same($id, 'Fickett_score', \sprintf('%.12g', $row->fickettScore), \sprintf('%.12g', $fickett->of($record->sequence)));
        }

        self::assertSame([], $rows, 'every line of CPC2 has its sequence');
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
