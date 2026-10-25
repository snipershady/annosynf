<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\AlphabetDetector;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlphabetDetector::class)]
#[CoversClass(AlphabetDetection::class)]
#[UsesClass(Alphabet::class)]
#[UsesClass(FastaLocation::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
final class AlphabetDetectorTest extends TestCase
{
    /**
     * Esiti calcolati da Bio::PrimarySeq di BioPerl 1.7.2 nel container legacy-runner.
     *
     * @return iterable<string, array{string, ?Alphabet}>
     */
    public static function bioPerlCases(): iterable
    {
        $cases = [
            'ACGTACGTAC' => Alphabet::Dna,
            'acgtacgtac' => Alphabet::Dna,
            'ACGUACGUAC' => Alphabet::Rna,
            'ACGTUACGTA' => Alphabet::Rna,
            'AAAAAAAAAU' => Alphabet::Rna,
            'MKVLAAGIVG' => Alphabet::Protein,
            'ACGTEACGTA' => Alphabet::Protein,
            'ACGTNNNNNN' => Alphabet::Dna,
            'NNNNNNNNNN' => Alphabet::Dna,
            'RYRYRYRYRY' => Alphabet::Protein,
            'WSKMWSKMWS' => Alphabet::Dna,
            'ACGTACGRYD' => Alphabet::Protein,
            'ACGTACGRYDH' => Alphabet::Protein,
            'ACGTACGHDB' => Alphabet::Protein,
            'ACGTAC-..?' => Alphabet::Dna,
            '---...???' => null,
            'HHHHHHHHHH' => Alphabet::Protein,
            'ACDGHKMNRSTVWY' => Alphabet::Protein,
            'GCGCGCGCGCGCGCRRRR' => Alphabet::Dna,
            'ACGTRRR' => Alphabet::Protein,
            'ACGTRRRR' => Alphabet::Protein,
            'B' => Alphabet::Protein,
            '*' => Alphabet::Protein,
            'ACGT*' => Alphabet::Dna,
            '' => null,
        ];

        foreach ($cases as $sequence => $alphabet) {
            yield \sprintf('"%s"', $sequence) => [$sequence, $alphabet];
        }
    }

    #[DataProvider('bioPerlCases')]
    public function testASequenceIsGuessedLikeBioPerl(string $sequence, ?Alphabet $expected): void
    {
        self::assertSame($expected, new AlphabetDetector()->guess($sequence));
    }

    public function testAFileTakesTheAlphabetOfMostSequences(): void
    {
        $detection = $this->detect(['>p1', 'MKVLAAGIVG', '>n1', 'ACGTACGTAC', '>n2', 'ACGTNNACGT', '>n3', 'ACGUACGUAC']);

        self::assertSame(Alphabet::Dna, $detection->alphabet, 'three nucleotide sequences out of four, mostly DNA');
        self::assertSame(Alphabet::Protein, $detection->firstSequenceAlphabet, 'what Annocript would have used');
        self::assertTrue($detection->isMixed());
        self::assertSame(['dna' => 2, 'rna' => 1, 'protein' => 1, 'unknown' => 0], $detection->counts);
        self::assertSame([[1, 'p1']], $this->locations($detection));
    }

    public function testRnaWinsWhenMostNucleotideSequencesHaveUracil(): void
    {
        $detection = $this->detect(['>r1', 'ACGUACGU', '>r2', 'UUUUACGA', '>d1', 'ACGTACGT']);

        self::assertSame(Alphabet::Rna, $detection->alphabet);
        self::assertFalse($detection->isMixed(), 'DNA and RNA are both nucleotides');
        self::assertSame([], $detection->mismatches);
    }

    public function testProteinFilesAndTies(): void
    {
        self::assertSame(Alphabet::Protein, $this->detect(['>p1', 'MKVLA', '>p2', 'QWERTY', '>n1', 'ACGTACGT'])->alphabet);
        self::assertSame(Alphabet::Dna, $this->detect(['>p1', 'MKVLA', '>n1', 'ACGTACGT'])->alphabet, 'a tie goes to nucleotides, the default of the pipeline');
    }

    public function testSequencesWithoutLettersAreUnknownAndIgnored(): void
    {
        $detection = $this->detect(['>e1', '>e2', '---', '>n1', 'ACGT']);

        self::assertSame(Alphabet::Dna, $detection->alphabet);
        self::assertNull($detection->firstSequenceAlphabet);
        self::assertSame(2, $detection->counts['unknown']);
        self::assertFalse($detection->isMixed());
    }

    public function testNoUsableSequenceGivesNoAlphabet(): void
    {
        $detection = $this->detect(['>e1', '-.?']);

        self::assertNull($detection->alphabet);
        self::assertSame([], $this->locations($this->detect([])));
        self::assertNull($this->detect([])->alphabet);
    }

    public function testOnlyTheFirstMismatchesAreListed(): void
    {
        $lines = ['>n0', 'ACGT', '>n00', 'ACGT'];

        for ($i = 1; $i <= 25; ++$i) {
            $lines[] = '>n' . $i;
            $lines[] = 'ACGT';
            $lines[] = '>p' . $i;
            $lines[] = 'MKVL';
        }

        $detection = $this->detect($lines);

        self::assertSame(Alphabet::Dna, $detection->alphabet);
        self::assertCount(AlphabetDetection::MAX_LOCATIONS, $detection->mismatches);
        self::assertSame('p1', $detection->mismatches[0]->id);
    }

    /**
     * @param list<string> $lines
     */
    private function detect(array $lines): AlphabetDetection
    {
        return new AlphabetDetector()->detect(new FastaReader()->read($lines));
    }

    /**
     * @return list<array{int, string}>
     */
    private function locations(AlphabetDetection $detection): array
    {
        return array_map(static fn (FastaLocation $location): array => [$location->lineNumber, $location->id], $detection->mismatches);
    }
}
