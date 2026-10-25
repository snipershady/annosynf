<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\AlphabetDetector;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Il legacy ha trattato trial ed example come DNA (ha eseguito CPC2 e dna2pep, riservati ai nucleotidi):
 * annosynf arriva allo stesso alfabeto guardando ogni sequenza, non solo la prima.
 */
#[CoversClass(AlphabetDetector::class)]
#[UsesClass(Alphabet::class)]
#[UsesClass(AlphabetDetection::class)]
#[UsesClass(FastaLocation::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(TextFileReader::class)]
final class AlphabetDetectionTest extends TestCase
{
    public function testTheReferenceTranscriptomesAreDnaThroughout(): void
    {
        foreach (GoldenFixtures::SETS as $set => $fasta) {
            $detection = new AlphabetDetector()->detect(new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($fasta))));
            $sequences = \count(GoldenFixtures::sequenceIds($set));

            self::assertSame(Alphabet::Dna, $detection->alphabet, $set);
            self::assertSame(Alphabet::Dna, $detection->firstSequenceAlphabet, $set);
            self::assertSame(['dna' => $sequences, 'rna' => 0, 'protein' => 0, 'unknown' => 0], $detection->counts, $set);
            self::assertFalse($detection->isMixed(), $set);
        }
    }
}
