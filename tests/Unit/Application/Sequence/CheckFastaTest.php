<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Sequence;

use App\Application\Port\TextLines;
use App\Application\Sequence\CheckFasta;
use App\Application\Sequence\FastaCheck;
use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\AlphabetDetector;
use App\Domain\Sequence\CompositionSummarizer;
use App\Domain\Sequence\CompositionSummary;
use App\Domain\Sequence\FastaIssue;
use App\Domain\Sequence\FastaIssueSummary;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaValidationReport;
use App\Domain\Sequence\FastaValidator;
use App\Domain\Sequence\InvalidFastaException;
use App\Domain\Sequence\IssueSeverity;
use App\Domain\Sequence\SequenceComposition;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckFasta::class)]
#[CoversClass(FastaCheck::class)]
#[UsesClass(Alphabet::class)]
#[UsesClass(AlphabetDetection::class)]
#[UsesClass(AlphabetDetector::class)]
#[UsesClass(CompositionSummarizer::class)]
#[UsesClass(CompositionSummary::class)]
#[UsesClass(FastaIssue::class)]
#[UsesClass(FastaIssueSummary::class)]
#[UsesClass(FastaLocation::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FastaValidationReport::class)]
#[UsesClass(FastaValidator::class)]
#[UsesClass(InvalidFastaException::class)]
#[UsesClass(IssueSeverity::class)]
#[UsesClass(SequenceComposition::class)]
final class CheckFastaTest extends TestCase
{
    public function testANucleotideFileIsValidatedWithItsAlphabetAndGetsItsComposition(): void
    {
        $check = $this->check(['>s1', 'ACGTACGT', '>s2', 'GGCCNN']);

        self::assertSame('in-memory.fasta', $check->path);
        self::assertSame(Alphabet::Dna, $check->alphabet);
        self::assertSame(Alphabet::Dna, $check->detection?->alphabet);
        self::assertTrue($check->isValid());
        self::assertSame(2, $check->validation->sequences);
        self::assertSame(14, $check->composition?->bases);
        self::assertEqualsWithDelta(100 * 8 / 14, $check->composition->overallGc, 1e-12);
    }

    public function testProteinsAreValidatedAsProteinsAndHaveNoComposition(): void
    {
        $check = $this->check(['>p1', 'MKVLAAGIVG', '>p2', 'QWERTYIPLK']);

        self::assertSame(Alphabet::Protein, $check->alphabet);
        self::assertTrue($check->isValid(), 'E, Q, I, L, P are valid amino acids');
        self::assertNull($check->composition);
    }

    public function testAFileThatIsNotFastaIsReportedNotThrown(): void
    {
        $check = $this->check(['ACGT', '>s1', 'ACGT']);

        self::assertNull($check->detection);
        self::assertSame(Alphabet::Dna, $check->alphabet, 'the default of the pipeline when nothing can be detected');
        self::assertFalse($check->isValid());
        self::assertSame([FastaIssue::NotFasta], array_map(static fn (FastaIssueSummary $summary): FastaIssue => $summary->issue, $check->validation->errors()));
        self::assertNull($check->composition, 'no statistics on a file that cannot be read');
    }

    public function testSequencesWithoutLettersFallBackToDna(): void
    {
        $check = $this->check(['>s1', '---']);

        self::assertNull($check->detection?->alphabet);
        self::assertSame(Alphabet::Dna, $check->alphabet);
        self::assertSame(3, $check->composition?->bases);
    }

    /**
     * @param list<string> $lines
     */
    private function check(array $lines): FastaCheck
    {
        $source = new readonly class($lines) implements TextLines {
            /**
             * @param list<string> $lines
             */
            public function __construct(private array $lines)
            {
            }

            #[Override]
            public function lines(string $path): iterable
            {
                return $this->lines;
            }
        };

        return new CheckFasta($source, new FastaReader(), new AlphabetDetector(), new FastaValidator(), new CompositionSummarizer())->check('in-memory.fasta');
    }
}
