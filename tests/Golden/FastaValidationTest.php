<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\FastaIssue;
use App\Domain\Sequence\FastaIssueSummary;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaValidationReport;
use App\Domain\Sequence\FastaValidator;
use App\Domain\Sequence\IssueSeverity;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * I FASTA da cui il legacy ha prodotto i golden passano la validazione senza errori, come per checkFastaFormat
 * di Annocript ("The fasta file … is good!"). L'unico avviso è nuovo (il legacy non controllava le N): in
 * example, Transcript_143 ha il 13% di N e Transcript_223 il 22%.
 */
#[CoversClass(FastaValidator::class)]
#[UsesClass(Alphabet::class)]
#[UsesClass(FastaIssue::class)]
#[UsesClass(FastaIssueSummary::class)]
#[UsesClass(FastaLocation::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FastaValidationReport::class)]
#[UsesClass(IssueSeverity::class)]
#[UsesClass(TextFileReader::class)]
final class FastaValidationTest extends TestCase
{
    public function testTheReferenceTranscriptomesHaveNoErrors(): void
    {
        $expectedWarnings = ['example' => ['high_n_content Transcript_143', 'high_n_content Transcript_223'], 'trial' => []];

        foreach (GoldenFixtures::SETS as $set => $fasta) {
            $report = new FastaValidator()->validate(new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($fasta))), Alphabet::Dna);
            $warnings = [];

            foreach ($report->warnings() as $summary) {
                foreach ($summary->locations as $location) {
                    $warnings[] = $summary->issue->value . ' ' . $location->id;
                }
            }

            self::assertTrue($report->isValid(), $set);
            self::assertSame($expectedWarnings[$set], $warnings, $set);
            self::assertCount($report->sequences, GoldenFixtures::sequenceIds($set), $set);
        }
    }
}
