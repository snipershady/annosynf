<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Process;

use App\Domain\Blast\BlastDbInfo;
use App\Domain\Blast\BlastDbInfoParser;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastPlusDbBuilder;
use App\Infrastructure\Process\BlastProcessExecutor;
use App\Infrastructure\Process\BlastVersionReader;
use App\Infrastructure\Process\InvalidBlastDatabaseException;
use App\Infrastructure\Process\ProcessResult;
use App\Infrastructure\Storage\GzipReader;
use App\Tests\Support\FakeBlastInstallation;
use App\Tests\Support\RecordingProcessRunner;
use App\Tests\Support\TemporaryDirectory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlastPlusDbBuilder::class)]
#[CoversClass(InvalidBlastDatabaseException::class)]
#[UsesClass(BlastBinaryLocator::class)]
#[UsesClass(BlastProcessExecutor::class)]
#[UsesClass(BlastVersionReader::class)]
#[UsesClass(ProcessResult::class)]
#[UsesClass(GzipReader::class)]
#[UsesClass(BlastDbInfo::class)]
#[UsesClass(BlastDbInfoParser::class)]
final class BlastPlusDbBuilderTest extends TestCase
{
    private const string INFO = "Database: t\n\t2 sequences; 42 total residues\n";

    private FakeBlastInstallation $blast;

    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->blast = new FakeBlastInstallation();
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->blast->remove();
        $this->directory->remove();
    }

    public function testACompressedFastaIsStreamedToMakeblastdbAndVerified(): void
    {
        $fasta = $this->directory->path . '/in.fasta.gz';
        file_put_contents($fasta, gzencode(">a\nMK\n>b\nMS\n"));
        $database = $this->directory->path . '/db/sp';
        $runner = new RecordingProcessRunner(
            new ProcessResult(0, '', ''),
            new ProcessResult(0, self::INFO, ''),
            new ProcessResult(0, "blastdbcmd: 2.17.0+\n", ''),
        );

        $info = $this->builder($runner)->build($fasta, $database, 'Swiss-Prot', 'prot');

        self::assertSame([$this->blast->binary('makeblastdb'), '-in', '-', '-out', $database, '-title', 'Swiss-Prot', '-dbtype', 'prot'], $runner->calls[0]['command']);
        self::assertSame(">a\nMK\n>b\nMS\n", $runner->calls[0]['input']);
        self::assertDirectoryExists($this->directory->path . '/db');
        self::assertSame([$this->blast->binary('blastdbcmd'), '-db', $database, '-dbtype', 'prot', '-info'], $runner->calls[1]['command']);
        self::assertSame([2, 42, '2.17.0+'], [$info->sequences, $info->letters, $info->version]);
    }

    public function testAnUnreadableDatabaseIsReportedWithItsPath(): void
    {
        $runner = new RecordingProcessRunner(new ProcessResult(0, "BLAST Database error: No alias or index file found\n", ''));

        $this->expectException(InvalidBlastDatabaseException::class);
        $this->expectExceptionMessageIsOrContains('"/blastdb/missing" is not a readable BLAST database.');

        $this->builder($runner)->inspect('/blastdb/missing', 'prot');
    }

    private function builder(RecordingProcessRunner $runner): BlastPlusDbBuilder
    {
        $executor = new BlastProcessExecutor($runner, 10.0);

        return new BlastPlusDbBuilder($this->blast->locator(), $executor, new BlastVersionReader($this->blast->locator(), $executor), new GzipReader());
    }
}
