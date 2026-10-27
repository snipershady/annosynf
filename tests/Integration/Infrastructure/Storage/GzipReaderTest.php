<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Storage;

use App\Infrastructure\Storage\CorruptArchiveException;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\GzipReader;
use App\Tests\Support\TemporaryDirectory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GzipReader::class)]
#[CoversClass(CorruptArchiveException::class)]
#[CoversClass(FileNotReadableException::class)]
final class GzipReaderTest extends TestCase
{
    private TemporaryDirectory $directory;

    private string $content;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->content = str_repeat(">sp|P12345|ABC_HUMAN Protein\nMKTAYIAKQRQISFVKSHFSRQ\n", 500);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testAGzipFileIsDecompressed(): void
    {
        self::assertSame($this->content, $this->read($this->file('a.gz', (string) gzencode($this->content))));
    }

    public function testAPlainFileIsReadAsItIs(): void
    {
        self::assertSame($this->content, $this->read($this->file('a.txt', $this->content), 1000));
        self::assertSame([], iterator_to_array(new GzipReader()->chunks($this->file('empty', ''))));
    }

    public function testConcatenatedMembersAreReadOneAfterTheOther(): void
    {
        $path = $this->file('multi.gz', gzencode('first member, ') . gzencode('second member'));

        self::assertSame('first member, second member', $this->read($path));
        self::assertSame('first member, second member', $this->read($path, 1), 'one byte at a time: the next header may start in the following block');
    }

    public function testATruncatedFileIsAnError(): void
    {
        $compressed = (string) gzencode($this->content);

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains('is truncated');

        $this->read($this->file('cut.gz', substr($compressed, 0, intdiv(\strlen($compressed), 2))));
    }

    public function testAMissingTrailerIsAnError(): void
    {
        $compressed = (string) gzencode($this->content);

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains('is truncated');

        // senza CRC e lunghezza finali: il wrapper compress.zlib:// lo leggerebbe come completo
        $this->read($this->file('notrailer.gz', substr($compressed, 0, -4)));
    }

    public function testDamagedDataIsAnError(): void
    {
        $compressed = (string) gzencode($this->content);
        $compressed = substr_replace($compressed, 'XXXX', intdiv(\strlen($compressed), 2), 4);

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains('is corrupt: invalid deflate data.');

        $this->read($this->file('damaged.gz', $compressed));
    }

    public function testDataAfterTheStreamIsAnError(): void
    {
        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains('is corrupt: unexpected data after the end of the gzip stream.');

        $this->read($this->file('garbage.gz', gzencode('data') . 'junk'));
    }

    public function testAMissingFileOrADirectoryIsReported(): void
    {
        try {
            $this->read($this->directory->path . '/nope.gz');
            self::fail('missing file');
        } catch (FileNotReadableException $fileNotReadableException) {
            self::assertStringContainsString('/nope.gz" does not exist or cannot be read.', $fileNotReadableException->getMessage());
        }

        $this->expectException(FileNotReadableException::class);

        $this->read($this->directory->path);
    }

    /**
     * @param positive-int $blockSize
     */
    private function read(string $path, int $blockSize = 65_536): string
    {
        return implode('', iterator_to_array(new GzipReader($blockSize)->chunks($path), false));
    }

    private function file(string $name, string $content): string
    {
        $path = $this->directory->path . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
