<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Storage;

use App\Infrastructure\Storage\ByteStream;
use App\Infrastructure\Storage\CorruptArchiveException;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\FileNotWritableException;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\PendingFile;
use App\Infrastructure\Storage\StreamingArchiveExtractor;
use App\Infrastructure\Storage\TarHeader;
use App\Tests\Support\TemporaryDirectory;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Archivi reali creati con GNU tar 1.35 (tests/Fixtures/archives: formati gnu, pax e ustar dello stesso
 * contenuto, con un percorso oltre i 100 caratteri) e archivi costruiti qui per i casi che tar non produce.
 */
#[CoversClass(StreamingArchiveExtractor::class)]
#[CoversClass(TarHeader::class)]
#[CoversClass(ByteStream::class)]
#[CoversClass(GzipReader::class)]
#[CoversClass(PendingFile::class)]
#[CoversClass(CorruptArchiveException::class)]
#[CoversClass(FileNotWritableException::class)]
#[CoversClass(FileNotReadableException::class)]
final class StreamingArchiveExtractorTest extends TestCase
{
    private const string LONG_DIRECTORY = 'long_directory_long_directory_long_directory_long_directory_';

    private const array FIXTURE_FILES = [
        'Cdd.pal' => '49aedb306ee11c484605560ca88fa74a920ea8bf0586286b153df86ffb99bf00',
        'empty.txt' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        'exact512.bin' => '32beecb58a128af8248504600bd203dcc676adf41045300485655e6b8780a01d',
        self::LONG_DIRECTORY . '/' . self::LONG_DIRECTORY . '/volume.00.rps' => 'a9d86fd824b32d3a5f2add9299b59f75a5c0c67627617cd78ac78d88cf391292',
    ];

    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->chmod($this->directory->path, 0o755, 0o000, true);
        $this->directory->remove();
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function realArchives(): Generator
    {
        yield 'GNU, gzip, long name in an L entry' => ['gnu.tar.gz'];
        yield 'pax, long name in a path record' => ['pax.tar'];
        yield 'ustar, long name split in prefix and name' => ['ustar.tar'];
    }

    #[DataProvider('realArchives')]
    public function testArchivesWrittenByGnuTarAreExtracted(string $archive): void
    {
        $target = $this->directory->path . '/out';

        $files = new StreamingArchiveExtractor()->untar(\dirname(__DIR__, 3) . '/Fixtures/archives/' . $archive, $target);

        self::assertSame(array_keys(self::FIXTURE_FILES), $files, 'only files, in archive order, relative to the target');

        foreach (self::FIXTURE_FILES as $file => $sha256) {
            self::assertSame($sha256, hash_file('sha256', $target . '/' . $file), $file);
        }

        self::assertSame([], glob($target . '/*.tmp'), 'no temporary file is left behind');
    }

    public function testARepeatedExtractionOverwritesTheFiles(): void
    {
        $archive = $this->archive($this->entry('data.txt', 'new'));
        new Filesystem()->dumpFile($this->directory->path . '/out/data.txt', 'old and longer');

        new StreamingArchiveExtractor()->untar($archive, $this->directory->path . '/out');

        self::assertSame('new', file_get_contents($this->directory->path . '/out/data.txt'));
    }

    public function testOldTypeFlagsBase256SizesAndPaxOverridesAreHonoured(): void
    {
        $archive = $this->archive(
            $this->entry('old.txt', 'v7 tar', type: "\0"),
            $this->entry('contiguous.txt', 'type 7', type: '7'),
            // GNU: dimensione in base 256 (bit alto del primo byte)
            $this->entry('big.txt', 'abc', sizeField: "\x80" . str_repeat("\0", 10) . "\x03"),
            // GNU: il campo prefix contiene altro (atime) e non fa parte del nome
            $this->entry('gnu.txt', 'gnu', magic: "ustar  \0", prefix: 'not-a-directory'),
            $this->entry('', 'global header', type: 'g'),
            $this->entry('', $this->paxRecord('path', 'pax/renamed.txt') . $this->paxRecord('size', '4'), type: 'x'),
            $this->entry('short-name', 'paxX', sizeField: \sprintf('%011o', 999) . "\0"),
        );

        $files = new StreamingArchiveExtractor()->untar($archive, $this->directory->path . '/out');

        self::assertSame(['old.txt', 'contiguous.txt', 'big.txt', 'gnu.txt', 'pax/renamed.txt'], $files);
        self::assertSame('abc', file_get_contents($this->directory->path . '/out/big.txt'));
        self::assertSame('paxX', file_get_contents($this->directory->path . '/out/pax/renamed.txt'), 'the pax size wins over the header');
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function unsafeEntries(): Generator
    {
        yield 'parent directory' => ['../evil.txt', '0'];
        yield 'absolute path' => ['/etc/evil.txt', '0'];
        yield 'empty segment' => ['a//b.txt', '0'];
        yield 'root as a file' => ['./', '0'];
        yield 'directory outside' => ['../outside/', '5'];
    }

    #[DataProvider('unsafeEntries')]
    public function testPathsLeavingTheTargetAreRefused(string $name, string $type): void
    {
        $archive = $this->archive($this->entry($name, $type === '5' ? '' : 'x', type: $type));

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('unsafe path "%s" in the tar archive', $name));

        new StreamingArchiveExtractor()->untar($archive, $this->directory->path . '/out');
    }

    public function testLinksAreRefused(): void
    {
        $archive = $this->archive($this->entry('passwd', '', type: '2'));

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains('unsupported tar entry type "2" for "passwd"');

        new StreamingArchiveExtractor()->untar($archive, $this->directory->path . '/out');
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function damagedArchives(): Generator
    {
        $header = self::header('data.txt', 10);

        yield 'no end-of-archive block' => [$header . str_pad('0123456789', 512, "\0"), 'is truncated'];
        yield 'file data cut short' => [$header . '01234', 'is truncated'];
        yield 'padding cut short' => [$header . '0123456789' . str_repeat("\0", 100), 'is truncated'];
        yield 'partial header' => [substr($header, 0, 300), 'is truncated'];
        yield 'checksum mismatch' => [substr_replace($header, 'X', 0, 1) . str_repeat("\0", 1024), 'tar header checksum mismatch'];
        yield 'invalid size' => [self::header('data.txt', 0, sizeField: "12abc\0\0\0\0\0\0\0") . str_repeat("\0", 1024), 'invalid number "12abc'];
        yield 'long name cut short' => [self::header('././@LongLink', 300, type: 'L') . str_repeat('n', 100), 'is truncated'];
        yield 'long name padding cut short' => [self::header('././@LongLink', 300, type: 'L') . str_repeat('n', 300), 'is truncated'];
        yield 'metadata over 1 MiB' => [self::header('././@LongLink', 2_000_000, type: 'L') . str_repeat("\0", 1024), 'tar metadata entry of 2000000 bytes'];
        yield 'pax without key' => [self::header('pax', 8, type: 'x') . str_pad('8 broken', 512, "\0"), 'invalid pax header'];
        yield 'pax with a zero length' => [self::header('pax', 9, type: 'x') . str_pad('0 path=x', 512, "\0"), 'invalid pax header'];
        yield 'pax longer than its entry' => [self::header('pax', 9, type: 'x') . str_pad('90 path=x', 512, "\0"), 'invalid pax header'];
    }

    #[DataProvider('damagedArchives')]
    public function testDamagedArchivesAreErrors(string $content, string $problem): void
    {
        $path = $this->directory->path . '/damaged.tar';
        file_put_contents($path, $content);

        $this->expectException(CorruptArchiveException::class);
        $this->expectExceptionMessageIsOrContains($problem);

        new StreamingArchiveExtractor()->untar($path, $this->directory->path . '/out');
    }

    public function testAnUnwritableTargetIsReported(): void
    {
        $archive = $this->archive($this->entry('dir/', '', type: '5'));
        $target = $this->directory->path . '/locked';
        mkdir($target, 0o555);

        try {
            new StreamingArchiveExtractor()->untar($archive, $target);
            self::fail('the directory entry cannot be created');
        } catch (FileNotWritableException) {
        }

        $this->expectException(FileNotWritableException::class);

        new StreamingArchiveExtractor()->untar($this->archive($this->entry('sub/file.txt', 'x')), $target);
    }

    public function testGunzipWritesTheDecompressedContent(): void
    {
        $tar = $this->directory->path . '/out/gnu.tar';

        $written = new StreamingArchiveExtractor()->gunzip(\dirname(__DIR__, 3) . '/Fixtures/archives/gnu.tar.gz', $tar);

        self::assertSame(10_240, $written);
        self::assertSame((string) gzdecode((string) file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/archives/gnu.tar.gz')), file_get_contents($tar));

        $plain = $this->directory->path . '/plain.txt';
        file_put_contents($plain, 'not compressed');
        new StreamingArchiveExtractor()->gunzip($plain, $this->directory->path . '/copy.txt');
        self::assertSame('not compressed', file_get_contents($this->directory->path . '/copy.txt'));
    }

    public function testATruncatedGzipLeavesNoDestination(): void
    {
        $source = $this->directory->path . '/cut.gz';
        $compressed = (string) gzencode(str_repeat('ACGT', 10_000));
        file_put_contents($source, substr($compressed, 0, -10));

        try {
            new StreamingArchiveExtractor()->gunzip($source, $this->directory->path . '/out.txt');
            self::fail('the gzip is truncated');
        } catch (CorruptArchiveException) {
        }

        self::assertSame(['cut.gz'], array_values(array_diff((array) scandir($this->directory->path), ['.', '..'])));
    }

    public function testGunzipNeedsAWritableDirectory(): void
    {
        $source = $this->directory->path . '/a.gz';
        file_put_contents($source, (string) gzencode('x'));
        mkdir($this->directory->path . '/locked', 0o555);

        $this->expectException(FileNotWritableException::class);

        new StreamingArchiveExtractor()->gunzip($source, $this->directory->path . '/locked/sub/a.txt');
    }

    private function archive(string ...$entries): string
    {
        $path = $this->directory->path . '/built-' . bin2hex(random_bytes(4)) . '.tar';
        file_put_contents($path, implode('', $entries) . str_repeat("\0", 1024));

        return $path;
    }

    private function entry(string $name, string $content, string $type = '0', ?string $sizeField = null, string $magic = "ustar\x0000", string $prefix = ''): string
    {
        $padding = (512 - \strlen($content) % 512) % 512;

        return self::header($name, \strlen($content), $type, $sizeField, $magic, $prefix) . $content . str_repeat("\0", $padding);
    }

    private function paxRecord(string $key, string $value): string
    {
        $body = ' ' . $key . '=' . $value . "\n";
        $length = \strlen($body) + 2;

        return $length . $body;
    }

    /**
     * Blocco header ustar con checksum corretto (`$sizeField` sostituisce il campo dimensione, 12 byte).
     */
    private static function header(string $name, int $size, string $type = '0', ?string $sizeField = null, string $magic = "ustar\x0000", string $prefix = ''): string
    {
        $header = str_pad($name, 100, "\0")
            // campi separati con str_pad: php-cs-fixer unirebbe "…\0" . "0…" in un escape ottale più lungo
            . str_pad('0000644', 8, "\0") . str_pad('0000000', 8, "\0") . str_pad('0000000', 8, "\0")
            . ($sizeField ?? \sprintf('%011o', $size) . "\0")
            . "15077444000\0"
            . '        '
            . $type
            . str_repeat("\0", 100)
            . $magic
            . str_repeat("\0", 80)
            . str_pad($prefix, 155, "\0")
            . str_repeat("\0", 12);
        $sum = 0;

        foreach (count_chars($header, 1) as $byte => $occurrences) {
            $sum += $byte * $occurrences;
        }

        return substr_replace($header, \sprintf('%06o', $sum) . "\0 ", 148, 8);
    }
}
