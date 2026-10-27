<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\GeneticCode;
use App\Domain\Sequence\Translator;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Traduzioni e annotazioni identiche a mod_translate di dna2pep: tutti i 3375 codoni IUPAC e le sequenze di
 * trial ed example in frame 1, con e senza lettura attraverso gli stop.
 */
#[CoversClass(Translator::class)]
#[UsesClass(CodonMark::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(GzipReader::class)]
final class TranslatorParityTest extends TestCase
{
    public function testEveryIupacCodonMatchesModTranslate(): void
    {
        $translator = new Translator();
        $comparison = new GoldenComparison();
        $lines = iterator_to_array(GoldenFixtures::lines('golden/translation/codons.tsv'), false);

        self::assertSame("codon\tfirst\tinternal\tfirst_stop\tannotation", array_shift($lines));
        self::assertCount(15 ** 3, $lines);

        foreach ($lines as $line) {
            [$codon, $first, $internal, $firstStop, $annotation] = explode("\t", $line);
            $comparison->same($codon, 'first', $first, $translator->translate($codon));
            $comparison->same($codon, 'internal', $internal, $translator->translate($codon, firstIsStart: false));
            $comparison->same($codon, 'first stop', $firstStop, $translator->translate($codon, readThroughStop: false));
            $comparison->same($codon, 'annotation', $annotation, $translator->annotate($codon));
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

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
    public function testEverySequenceTranslatesLikeModTranslate(string $set): void
    {
        $translator = new Translator();
        $comparison = new GoldenComparison();
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/translation/sequences.tsv', $set)), false);
        self::assertSame("id\tstop_at_first_stop\tread_through\tinternal_only\tannotation", array_shift($lines));

        $records = iterator_to_array(new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path(GoldenFixtures::fasta($set)))), false);
        self::assertCount(\count($lines), $records);

        foreach ($lines as $position => $line) {
            [$id, $stopAtFirstStop, $readThrough, $internalOnly, $annotation] = explode("\t", $line);
            $sequence = $records[$position]->sequence;
            $comparison->same($id, 'id', $id, $records[$position]->id());
            $comparison->same($id, 'stop at first stop', $stopAtFirstStop, $translator->translate($sequence, readThroughStop: false));
            $comparison->same($id, 'read through', $readThrough, $translator->translate($sequence));
            $comparison->same($id, 'internal only', $internalOnly, $translator->translate($sequence, firstIsStart: false));
            $comparison->same($id, 'annotation', $annotation, $translator->annotate($sequence));
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
