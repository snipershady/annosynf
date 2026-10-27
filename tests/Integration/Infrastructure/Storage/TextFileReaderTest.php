<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Storage;

use App\Infrastructure\Storage\CorruptArchiveException;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\TemporaryDirectory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(TextFileReader::class)]
#[CoversClass(FileNotReadableException::class)]
#[CoversClass(CorruptArchiveException::class)]
#[UsesClass(GzipReader::class)]
final class TextFileReaderTest extends TestCase
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
    }

    public function testPlainAndGzippedFilesGiveTheSameLines(): void
    {
        $content = ">seq1\r\nACGT\n" . str_repeat('A', 100_000) . "\n\nlast line without newline";
        $plain = $this->directory->path . '/plain.fasta';
        $gzipped = $this->directory->path . '/compressed.fasta.gz';
        new Filesystem()->dumpFile($plain, $content);
        new Filesystem()->dumpFile($gzipped, (string) gzencode($content));

        $expected = [">seq1\r", 'ACGT', str_repeat('A', 100_000), '', 'last line without newline'];

        self::assertSame($expected, iterator_to_array(new TextFileReader()->lines($plain), false));
        self::assertSame($expected, iterator_to_array(new TextFileReader()->lines($gzipped), false), 'the format is recognised from the content');
    }

    public function testLinesAcrossBlocksKeepProgressiveKeys(): void
    {
        $content = "first\n" . str_repeat('A', 50) . "\nthird\n\nfifth";
        $plain = $this->directory->path . '/plain.txt';
        new Filesystem()->dumpFile($plain, $content);

        // blocchi di 7 byte: righe spezzate su più blocchi e righe più lunghe di un blocco
        $lines = iterator_to_array(new TextFileReader(new GzipReader(7))->lines($plain));

        self::assertSame(['first', str_repeat('A', 50), 'third', '', 'fifth'], $lines);
        self::assertSame([0, 1, 2, 3, 4], array_keys($lines));
    }

    public function testATruncatedGzipIsAnError(): void
    {
        $compressed = (string) gzencode(str_repeat("ACGT\n", 10_000));
        $truncated = $this->directory->path . '/truncated.fasta.gz';
        new Filesystem()->dumpFile($truncated, substr($compressed, 0, -100));

        $this->expectException(CorruptArchiveException::class);

        iterator_to_array(new TextFileReader()->lines($truncated));
    }

    public function testAnEmptyFileHasNoLines(): void
    {
        $empty = $this->directory->path . '/empty.txt';
        new Filesystem()->touch($empty);

        self::assertSame([], iterator_to_array(new TextFileReader()->lines($empty)));
    }

    public function testAMissingFileIsReported(): void
    {
        $this->expectException(FileNotReadableException::class);
        $this->expectExceptionMessageIsOrContains('/nope.fasta" does not exist or cannot be read.');

        new TextFileReader()->lines($this->directory->path . '/nope.fasta')->current();
    }

    public function testAFileWithoutReadPermissionIsReported(): void
    {
        $locked = $this->directory->path . '/locked.fasta';
        new Filesystem()->dumpFile($locked, ">seq1\nACGT\n");
        new Filesystem()->chmod($locked, 0o000);

        if (is_readable($locked)) {
            self::markTestSkipped('Running as root: file permissions do not prevent reading.');
        }

        $this->expectException(FileNotReadableException::class);

        new TextFileReader()->lines($locked)->current();
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectException(FileNotReadableException::class);

        new TextFileReader()->lines($this->directory->path)->current();
    }
}
