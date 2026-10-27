<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Application\Sequence\CheckFasta;
use App\Application\Sequence\FastaCheck;
use App\Command\FastaCheckCommand;
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
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\GoldenFixtures;
use App\Tests\Support\TemporaryDirectory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ExecutionResult;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(FastaCheckCommand::class)]
#[UsesClass(CheckFasta::class)]
#[UsesClass(FastaCheck::class)]
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
#[UsesClass(FileNotReadableException::class)]
#[UsesClass(InvalidFastaException::class)]
#[UsesClass(IssueSeverity::class)]
#[UsesClass(SequenceComposition::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(GzipReader::class)]
final class FastaCheckCommandTest extends KernelTestCase
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->remove();
        parent::tearDown();
    }

    public function testACleanTranscriptomeCanBeAnalysed(): void
    {
        $result = self::runCommand('annosynf:fasta:check', ['file' => GoldenFixtures::path(GoldenFixtures::fasta('trial'))], interactive: false);
        $display = $this->display($result->getDisplay());

        self::assertSame(Command::SUCCESS, $result->statusCode, $display);
        self::assertStringContainsString('Sequences 110', $display);
        self::assertStringContainsString('Alphabet dna', $display);
        self::assertStringContainsString('Overall GC 40.84%', $display, 'the overall GC of the legacy statistics');
        self::assertStringContainsString('[OK] The file can be analysed.', $display);
    }

    public function testWarningsAreListedButDoNotBlock(): void
    {
        $result = self::runCommand('annosynf:fasta:check', ['file' => GoldenFixtures::path(GoldenFixtures::fasta('example'))], interactive: false);
        $display = $this->display($result->getDisplay());

        self::assertSame(Command::SUCCESS, $result->statusCode);
        self::assertStringContainsString('line 5790 (Transcript_143), line 8918 (Transcript_223)', $display);
        self::assertStringContainsString('check the warnings above', $display);
    }

    public function testBlockingErrorsFailTheCheckAndShowWhereTheyAre(): void
    {
        $lines = [];

        for ($i = 1; $i <= 7; ++$i) {
            $lines[] = '>dup';
            $lines[] = 'ACGT';
        }

        $result = $this->checkFile([...$lines, '>bad', 'ACXT', '>empty']);
        $display = $this->display($result->getDisplay());

        self::assertSame(Command::FAILURE, $result->statusCode);
        self::assertStringContainsString('line 3 (dup), line 5 (dup), line 7 (dup), line 9 (dup), line 11 (dup) and 1 more', $display);
        self::assertStringContainsString('Characters that are not IUPAC codes for dna: X', $display);
        self::assertStringContainsString('fix the 3 blocking problem(s)', $display);
    }

    public function testGzippedProteinFilesAreRecognised(): void
    {
        $path = $this->directory->path . '/proteins.fasta.gz';
        new Filesystem()->dumpFile($path, (string) gzencode(">p1\nMKVLAAGIVG\n>p2\nQWERTYIPLK\n"));

        $result = self::runCommand('annosynf:fasta:check', ['file' => $path], interactive: false);
        $display = $this->display($result->getDisplay());

        self::assertSame(Command::SUCCESS, $result->statusCode);
        self::assertStringContainsString('Alphabet protein', $display);
        self::assertStringNotContainsString('Overall GC', $display, 'no base composition for proteins');
    }

    public function testMixedFilesAreFlaggedWithTheAlphabetTheLegacyWouldHaveUsed(): void
    {
        $display = $this->display($this->checkFile(['>p1', 'MKVLAAGIVG', '>n1', 'ACGTACGT', '>n2', 'GGCCAATT'])->getDisplay());

        self::assertStringContainsString('Alphabet dna (Annocript, which looked only at the first sequence, would have used protein)', $display);
        self::assertStringContainsString('mixes nucleotide and protein sequences (2 DNA, 0 RNA, 1 protein)', $display);
        self::assertStringContainsString('line 1 (p1)', $display);
    }

    public function testAFileWithoutHeaderOrLettersIsReportedPlainly(): void
    {
        $notFasta = $this->display($this->checkFile(['just text'])->getDisplay());
        self::assertStringContainsString('dna (not detectable, assumed)', $notFasta);
        self::assertStringContainsString('line 1', $notFasta);

        $noLetters = $this->checkFile(['>s1', '---']);
        self::assertSame(Command::SUCCESS, $noLetters->statusCode);
        self::assertStringContainsString('dna (not detectable, assumed)', $this->display($noLetters->getDisplay()));
    }

    public function testAMissingFileIsAnError(): void
    {
        $result = self::runCommand('annosynf:fasta:check', ['file' => $this->directory->path . '/nope.fasta'], interactive: false);

        self::assertSame(Command::FAILURE, $result->statusCode);
        self::assertStringContainsString('does not exist or cannot be read', $this->display($result->getDisplay()));
    }

    /**
     * @param list<string> $lines
     */
    private function checkFile(array $lines): ExecutionResult
    {
        $path = $this->directory->path . '/input.fasta';
        new Filesystem()->dumpFile($path, implode("\n", $lines) . "\n");

        return self::runCommand('annosynf:fasta:check', ['file' => $path], interactive: false);
    }

    /**
     * Output su una riga sola con gli spazi compressi: i messaggi lunghi vanno a capo nel terminale.
     */
    private function display(string $display): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $display));
    }
}
