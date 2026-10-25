<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\FastaIssue;
use App\Domain\Sequence\FastaIssueSummary;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaValidationReport;
use App\Domain\Sequence\FastaValidator;
use App\Domain\Sequence\InvalidFastaException;
use App\Domain\Sequence\IssueSeverity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastaValidator::class)]
#[CoversClass(FastaValidationReport::class)]
#[CoversClass(FastaIssueSummary::class)]
#[CoversClass(FastaLocation::class)]
#[CoversClass(FastaIssue::class)]
#[CoversClass(IssueSeverity::class)]
#[UsesClass(Alphabet::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(InvalidFastaException::class)]
final class FastaValidatorTest extends TestCase
{
    public function testAWellFormedFileHasNoIssues(): void
    {
        $report = $this->validate(['>seq1 a transcript', 'ACGTacgtRYKMN-', '>seq2', 'ACGU']);

        self::assertTrue($report->isValid());
        self::assertSame(2, $report->sequences);
        self::assertSame([], $report->errors());
        self::assertSame([], $report->warnings());
    }

    public function testAnEmptyFileIsAnError(): void
    {
        $report = $this->validate(['', '  ']);

        self::assertFalse($report->isValid());
        self::assertSame(0, $report->sequences);
        self::assertSame([FastaIssue::EmptyFile], $this->issues($report->errors()));
    }

    public function testTextBeforeTheFirstHeaderStopsTheValidation(): void
    {
        $report = $this->validate(['ACGT', '>seq1', 'ACGT']);

        self::assertFalse($report->isValid());
        self::assertSame([FastaIssue::NotFasta], $this->issues($report->errors()));
        self::assertSame([1], $this->lines($report, FastaIssue::NotFasta));
    }

    public function testBlockingContentErrorsAreReportedWithTheirPosition(): void
    {
        $report = $this->validate([
            '>seq1',
            'ACGT',
            '>seq2',
            '>seq1 again',
            'ACGT',
            '> spaced',
            'ACGT',
            '>',
            'ACGT',
            '>seq3',
            'ACXT',
            'AC>T',
        ]);

        self::assertFalse($report->isValid());
        self::assertSame([FastaIssue::EmptyHeader, FastaIssue::SpaceAfterGreaterThan, FastaIssue::DuplicateId, FastaIssue::EmptySequence, FastaIssue::InvalidCharacters], $this->issues($report->errors()), 'in the fixed order of the issues');
        self::assertSame([3], $this->lines($report, FastaIssue::EmptySequence));
        self::assertSame([4], $this->lines($report, FastaIssue::DuplicateId));
        self::assertSame('seq1', $report->summary(FastaIssue::DuplicateId)?->locations[0]->id);
        self::assertSame([6], $this->lines($report, FastaIssue::SpaceAfterGreaterThan));
        self::assertSame([8], $this->lines($report, FastaIssue::EmptyHeader), 'an empty header is not also a "space after >"');
        self::assertSame([10], $this->lines($report, FastaIssue::InvalidCharacters));
        self::assertSame(['>', 'X'], $report->invalidCharacters);
    }

    public function testTheAllowedCharactersDependOnTheAlphabet(): void
    {
        $protein = $this->validate(['>p1', 'MKVLA*', '>p2', 'MKJO'], Alphabet::Protein);

        self::assertSame([FastaIssue::InvalidCharacters], $this->issues($protein->errors()));
        self::assertSame(['J', 'O'], $protein->invalidCharacters, 'the legacy protein set has no J and O');
        self::assertTrue($this->validate(['>s1', 'ACGT'], Alphabet::Rna)->isValid());
        self::assertFalse($this->validate(['>s1', 'ACGTE'], Alphabet::Dna)->isValid());
    }

    public function testWarningsDoNotBlockTheFile(): void
    {
        $report = $this->validate([
            '>' . str_repeat('h', 80),
            str_repeat('A', 81),
            '>short',
            'ACGTNNNNNN',
            '>fine',
            str_repeat('A', 80),
        ]);

        self::assertTrue($report->isValid());
        self::assertSame([FastaIssue::LongHeader, FastaIssue::LongSequenceLine, FastaIssue::HighNContent], $this->issues($report->warnings()));
        self::assertSame([1], $this->lines($report, FastaIssue::LongHeader), 'like the legacy, the header length includes ">"');
        self::assertSame([1], $this->lines($report, FastaIssue::LongSequenceLine));
        self::assertSame([3], $this->lines($report, FastaIssue::HighNContent));
    }

    public function testTheNContentWarningIsOnlyForNucleotides(): void
    {
        self::assertSame([], $this->validate(['>p1', 'MNNNNN'], Alphabet::Protein)->warnings());
        self::assertSame([FastaIssue::HighNContent], $this->issues($this->validate(['>s1', 'ACGTn'], Alphabet::Dna, 0.19)->warnings()));
        self::assertSame([], $this->validate(['>s1', 'ACGTn'], Alphabet::Dna, 0.2)->warnings(), 'exactly at the threshold is fine');
    }

    public function testOnlyTheFirstLocationsAreKeptButEveryOccurrenceIsCounted(): void
    {
        $lines = [];

        for ($i = 1; $i <= 25; ++$i) {
            $lines[] = '>seq' . $i;
        }

        $summary = $this->validate($lines)->summary(FastaIssue::EmptySequence);

        self::assertNotNull($summary);
        self::assertSame(25, $summary->count);
        self::assertCount(FastaValidationReport::MAX_LOCATIONS, $summary->locations);
        self::assertSame(1, $summary->locations[0]->lineNumber);
        self::assertSame('seq1', $summary->locations[0]->id);
    }

    public function testIssuesDescribeThemselves(): void
    {
        foreach (FastaIssue::cases() as $issue) {
            self::assertNotSame('', $issue->description());
        }

        self::assertSame(IssueSeverity::Error, FastaIssue::DuplicateId->severity());
        self::assertSame(IssueSeverity::Warning, FastaIssue::LongHeader->severity());
        self::assertNull($this->validate(['>s1', 'ACGT'])->summary(FastaIssue::EmptyFile));
    }

    /**
     * @param list<string> $lines
     */
    private function validate(array $lines, Alphabet $alphabet = Alphabet::Dna, float $highNFraction = FastaValidator::HIGH_N_FRACTION): FastaValidationReport
    {
        return new FastaValidator($highNFraction)->validate(new FastaReader()->read($lines), $alphabet);
    }

    /**
     * @param list<FastaIssueSummary> $summaries
     *
     * @return list<FastaIssue>
     */
    private function issues(array $summaries): array
    {
        return array_map(static fn (FastaIssueSummary $summary): FastaIssue => $summary->issue, $summaries);
    }

    /**
     * @return list<int>
     */
    private function lines(FastaValidationReport $report, FastaIssue $issue): array
    {
        return array_map(static fn (FastaLocation $location): int => $location->lineNumber, $report->summary($issue)->locations ?? []);
    }
}
