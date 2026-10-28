<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Process;

use App\Domain\Blast\BlastDbInfo;
use App\Domain\Blast\BlastDbInfoParser;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastPlusDbBuilder;
use App\Infrastructure\Process\BlastProcessExecutor;
use App\Infrastructure\Process\BlastVersionReader;
use App\Infrastructure\Process\ProcessResult;
use App\Infrastructure\Process\SymfonyProcessRunner;
use App\Infrastructure\Storage\GzipReader;
use App\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Con i binari BLAST+ veri (BLAST_BIN_DIR): il formato di `blastdbcmd -info` dipende dalla versione.
 */
#[CoversClass(BlastPlusDbBuilder::class)]
#[UsesClass(BlastBinaryLocator::class)]
#[UsesClass(BlastProcessExecutor::class)]
#[UsesClass(BlastVersionReader::class)]
#[UsesClass(ProcessResult::class)]
#[UsesClass(SymfonyProcessRunner::class)]
#[UsesClass(GzipReader::class)]
#[UsesClass(BlastDbInfo::class)]
#[UsesClass(BlastDbInfoParser::class)]
final class BlastPlusDbBuilderTest extends TestCase
{
    public function testProteinAndNucleotideDatabasesAreBuiltFromGzippedFasta(): void
    {
        $binDir = $_SERVER['BLAST_BIN_DIR'] ?? null;

        if (!\is_string($binDir) || !is_executable($binDir . '/makeblastdb')) {
            self::markTestSkipped('BLAST+ is not installed (BLAST_BIN_DIR).');
        }

        $directory = new TemporaryDirectory();

        try {
            $locator = new BlastBinaryLocator($binDir);
            $executor = new BlastProcessExecutor(new SymfonyProcessRunner(), 60.0);
            $builder = new BlastPlusDbBuilder($locator, $executor, new BlastVersionReader($locator, $executor), new GzipReader());
            file_put_contents($directory->path . '/p.fasta.gz', gzencode(">sp|P1|X_H\nMKVLAAGIVGLLLAQ\n>sp|P2|Y_H\nMSTNPKPQRKTKRNTNRRPQDVKFPGG\n"));
            file_put_contents($directory->path . '/n.fasta', ">URS1\nACGTACGTAC\n>URS2\nGGGGCCCCAA\n");

            $protein = $builder->build($directory->path . '/p.fasta.gz', $directory->path . '/blast/p', 'proteins', 'prot');
            $nucleotide = $builder->build($directory->path . '/n.fasta', $directory->path . '/blast/n', 'rna', 'nucl');

            self::assertSame([2, 42], [$protein->sequences, $protein->letters]);
            self::assertSame([2, 20], [$nucleotide->sequences, $nucleotide->letters]);
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+\+$/', (string) $nucleotide->version);
        } finally {
            $directory->remove();
        }
    }
}
