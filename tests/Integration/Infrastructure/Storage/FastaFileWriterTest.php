<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Storage;

use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaHeaderStyle;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaSlicer;
use App\Infrastructure\Storage\FastaFileWriter;
use App\Infrastructure\Storage\FileNotWritableException;
use App\Infrastructure\Storage\PendingFile;
use App\Tests\Support\TemporaryDirectory;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(FastaFileWriter::class)]
#[CoversClass(PendingFile::class)]
#[CoversClass(FileNotWritableException::class)]
#[UsesClass(FastaFormatter::class)]
#[UsesClass(FastaHeaderStyle::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FastaSlicer::class)]
final class FastaFileWriterTest extends TestCase
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
        new Filesystem()->chmod($this->directory->path, 0o755, 0o000, true);
        $this->directory->remove();
    }

    public function testRecordsAreWrittenInFastaFormatCreatingTheDirectory(): void
    {
        $path = $this->directory->path . '/nested/dir/out.fasta';

        self::assertSame(2, new FastaFileWriter()->write($path, $this->records(), FastaHeaderStyle::IdOnly));
        self::assertSame(">s1\nACGT\n>s2\nGG\n", file_get_contents($path));
        self::assertSame(['out.fasta'], $this->files(\dirname($path)), 'no temporary file is left behind');
    }

    public function testSlicesGoToOneFilePerSlice(): void
    {
        $records = [...$this->records(), new FastaRecord('s3 third', 'TTTT', 5, 4)];
        $paths = new FastaFileWriter()->writeSlices($this->directory->path . '/', 'slice', new FastaSlicer()->slices($records, 10, 2));

        self::assertSame([1 => $this->directory->path . '/slice_1.fasta', 2 => $this->directory->path . '/slice_2.fasta'], $paths);
        self::assertSame(">s1 first\nACGT\n>s2\nGG\n", file_get_contents($paths[1]));
        self::assertSame(">s3 third\nTTTT\n", file_get_contents($paths[2]));
        self::assertSame([], new FastaFileWriter()->writeSlices($this->directory->path, 'none', []), 'no records, no files');
    }

    public function testAnInterruptedWriteLeavesNoFile(): void
    {
        $path = $this->directory->path . '/out.fasta';
        $failing = (function (): Generator {
            yield from $this->records();

            throw new RuntimeException('worker stopped');
        })();

        try {
            new FastaFileWriter()->write($path, $failing);
            self::fail('The exception must reach the caller.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('worker stopped', $runtimeException->getMessage());
        }

        self::assertSame([], $this->files($this->directory->path), 'neither the final file nor the temporary one');
    }

    public function testAnUnwritableDirectoryIsReported(): void
    {
        $locked = $this->directory->path . '/locked';
        new Filesystem()->mkdir($locked, 0o555);

        if (is_writable($locked)) {
            self::markTestSkipped('Running as root: file permissions do not prevent writing.');
        }

        foreach ([$locked . '/out.fasta', $locked . '/sub/out.fasta'] as $path) {
            try {
                new FastaFileWriter()->write($path, $this->records());
                self::fail('Writing must fail.');
            } catch (FileNotWritableException $exception) {
                self::assertSame(\sprintf('File "%s" cannot be written.', $path), $exception->getMessage());
            }
        }
    }

    public function testACommitThatCannotRenameIsReported(): void
    {
        $target = $this->directory->path . '/target.fasta';
        new Filesystem()->mkdir($target . '/occupied');
        $file = PendingFile::create($target);
        $file->write('>s1');

        try {
            $file->commit();
            self::fail('Renaming over a non-empty directory must fail.');
        } catch (FileNotWritableException) {
            self::assertSame(['target.fasta'], $this->files($this->directory->path), 'the temporary file is removed');
        }

        $this->expectException(FileNotWritableException::class);

        $file->write('again');
    }

    public function testAFileIsCommittedOnlyOnce(): void
    {
        $file = PendingFile::create($this->directory->path . '/once.fasta');
        $file->write('>s1');
        $file->commit();
        $file->discard();

        self::assertSame('>s1', file_get_contents($this->directory->path . '/once.fasta'), 'discarding after the commit changes nothing');

        $this->expectException(FileNotWritableException::class);

        $file->commit();
    }

    /**
     * @return list<FastaRecord>
     */
    private function records(): array
    {
        return [new FastaRecord('s1 first', 'ACGT', 1, 4), new FastaRecord('s2', 'GG', 3, 2)];
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $entries = scandir($directory);

        return $entries === false ? [] : array_values(array_diff($entries, ['.', '..']));
    }
}
