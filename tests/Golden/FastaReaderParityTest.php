<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Infrastructure\Storage\GzipReader;
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
 * I FASTA di prova letti da annosynf danno le stesse sequenze, nello stesso ordine e con le stesse lunghezze,
 * che il legacy ha passato a CPC2 (colonna transcript_length).
 */
#[CoversClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(GzipReader::class)]
final class FastaReaderParityTest extends TestCase
{
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
    public function testIdsOrderAndLengthsMatchTheLegacy(string $set): void
    {
        $records = iterator_to_array(new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path(GoldenFixtures::fasta($set)))), false);
        $legacy = array_map(Cpc2Row::fromLine(...), \array_slice(iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2.tsv', $set)), false), 1));

        self::assertCount(\count($legacy), $records);

        $comparison = new GoldenComparison();

        foreach ($legacy as $position => $row) {
            $comparison->same($row->id, 'id', $row->id, $records[$position]->id());
            $comparison->same($row->id, 'length', $row->transcriptLength, $records[$position]->length());
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
