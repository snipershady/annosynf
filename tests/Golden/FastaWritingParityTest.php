<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaHeaderStyle;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaSlicer;
use App\Domain\Sequence\FastaSubset;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Scrittura, slice e sottoinsiemi di FASTA identici al legacy: riscrittura di Bio::SeqIO, create_slices e
 * faSomeRecords (tests/Fixtures/golden/README.md).
 */
#[CoversClass(FastaFormatter::class)]
#[CoversClass(FastaSlicer::class)]
#[CoversClass(FastaSubset::class)]
#[UsesClass(FastaHeaderStyle::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(GzipReader::class)]
final class FastaWritingParityTest extends TestCase
{
    public function testRecordsAreWrittenByteForByteLikeBioPerl(): void
    {
        self::assertSame(
            file_get_contents(GoldenFixtures::path('golden/format/bioperl_rewrite.fasta')),
            $this->format($this->records('fasta/format_cases.fasta')),
        );
    }

    /**
     * @return Generator<string, array{string, int}>
     */
    public static function slicings(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            foreach ([3, 10] as $count) {
                yield \sprintf('%s in %d', $set, $count) => [$set, $count];
            }
        }
    }

    #[DataProvider('slicings')]
    public function testEverySequenceGoesToTheSameSliceAsInTheLegacy(string $set, int $count): void
    {
        $records = $this->records(GoldenFixtures::fasta($set));
        $totalBases = array_sum(array_map(static fn (FastaRecord $record): int => $record->length(), $records));
        $assigned = [];

        foreach (new FastaSlicer()->slices($records, $totalBases, $count) as $slice => $record) {
            $assigned[] = $record->id() . "\t" . $slice;
        }

        self::assertSame(iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/slices_%d.tsv', $set, $count)), false), $assigned);
    }

    public function testASubsetIsIdenticalToFaSomeRecords(): void
    {
        $ids = iterator_to_array(GoldenFixtures::lines('fasta/example_subset_ids.txt'), false);
        $subset = new FastaSubset($ids)->select($this->records(GoldenFixtures::fasta('example')));

        self::assertSame(file_get_contents(GoldenFixtures::path('golden/subset/example_subset.fasta')), $this->format($subset));
    }

    /**
     * @return list<FastaRecord>
     */
    private function records(string $relative): array
    {
        return iterator_to_array(new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($relative))), false);
    }

    /**
     * @param iterable<FastaRecord> $records
     */
    private function format(iterable $records): string
    {
        $formatter = new FastaFormatter();
        $output = '';

        foreach ($records as $record) {
            $output .= $formatter->format($record, FastaHeaderStyle::Full);
        }

        return $output;
    }
}
