<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\CompositionSummarizer;
use App\Domain\Sequence\CompositionSummary;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\SequenceComposition;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\AtgcRow;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Composizione in basi identica a extractPercentages del legacy (ATGC_Percentages.txt e medie del file
 * di statistiche), sequenza per sequenza.
 */
#[CoversClass(SequenceComposition::class)]
#[CoversClass(CompositionSummarizer::class)]
#[UsesClass(CompositionSummary::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(TextFileReader::class)]
final class SequenceCompositionParityTest extends TestCase
{
    /** Il legacy stampa i numeri con 15 cifre significative. */
    private const float TOLERANCE = 1e-9;

    /**
     * @return Generator<string, array{string}>
     */
    public static function sets(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            yield $set => [$set];
        }
    }

    #[DataProvider('sets')]
    public function testEverySequenceHasTheLegacyComposition(string $set): void
    {
        $compositions = $this->compositions($set);
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/atgc_percentages.tsv', $set)), false);
        self::assertSame(AtgcRow::HEADER, array_shift($lines));
        self::assertCount(\count($lines), $compositions);

        $comparison = new GoldenComparison(self::TOLERANCE);

        foreach (array_map(AtgcRow::fromLine(...), $lines) as $position => $row) {
            [$id, $composition] = $compositions[$position];
            $comparison->same($row->id, 'id', $row->id, $id);
            $comparison->same($row->id, 'length', $row->length, $composition->length);
            $comparison->close($row->id, 'adenine', $row->adenine, $composition->adeninePercentage() ?? \NAN);
            $comparison->close($row->id, 'cytosine', $row->cytosine, $composition->cytosinePercentage() ?? \NAN);
            $comparison->close($row->id, 'guanine', $row->guanine, $composition->guaninePercentage() ?? \NAN);
            $comparison->close($row->id, 'thymine', $row->thymine, $composition->thyminePercentage() ?? \NAN);
            $comparison->close($row->id, 'n', $row->n, $composition->nPercentage() ?? \NAN);
            $comparison->close($row->id, 'gc', $row->gcContent, $composition->gcPercentage() ?? \NAN);
            $comparison->same($row->id, 'errors', $row->errors, $composition->errors());
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    #[DataProvider('sets')]
    public function testTheFileAveragesMatchTheLegacyStatistics(string $set): void
    {
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/atgc_percentages.tsv.averages', $set)), false);
        self::assertSame("Adenine\tGuanine\tThymine\tCytosine\tN\tGCcontent", $lines[0]);
        $legacy = array_map(floatval(...), explode("\t", $lines[1]));

        $summary = new CompositionSummarizer()->summarize(array_column($this->compositions($set), 1));
        $comparison = new GoldenComparison(self::TOLERANCE);

        foreach (['adenine' => $summary->averageAdenine, 'guanine' => $summary->averageGuanine, 'thymine' => $summary->averageThymine, 'cytosine' => $summary->averageCytosine, 'n' => $summary->averageN, 'overall gc' => $summary->overallGc] as $field => $value) {
            $comparison->close($set, $field, array_shift($legacy) ?? \NAN, $value ?? \NAN);
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    /**
     * @return list<array{string, SequenceComposition}>
     */
    private function compositions(string $set): array
    {
        $compositions = [];

        foreach (new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path(GoldenFixtures::fasta($set)))) as $record) {
            $compositions[] = [$record->id(), SequenceComposition::of($record->sequence)];
        }

        return $compositions;
    }
}
