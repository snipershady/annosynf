<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Process;

use App\Domain\Blast\BlastProgram;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastBinaryNotFoundException;
use App\Infrastructure\Process\BlastTool;
use App\Tests\Support\FakeBlastInstallation;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(BlastBinaryLocator::class)]
#[CoversClass(BlastBinaryNotFoundException::class)]
final class BlastBinaryLocatorTest extends TestCase
{
    private FakeBlastInstallation $blast;

    #[Override]
    protected function setUp(): void
    {
        $this->blast = new FakeBlastInstallation();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->blast->remove();
    }

    public function testProgramsAndToolsAreResolvedInsideTheConfiguredDirectory(): void
    {
        $locator = $this->blast->locator();

        self::assertSame($this->blast->directory->path, $locator->directory(), 'the trailing slash is dropped');
        self::assertSame($this->blast->binary('rpstblastn'), $locator->path(BlastProgram::Rpstblastn));
        self::assertSame($this->blast->binary('makeblastdb'), $locator->path(BlastTool::MakeBlastDb));
    }

    public function testMissingBinaryPointsToTheConfiguration(): void
    {
        new Filesystem()->remove($this->blast->binary('blastdbcmd'));

        $this->expectException(BlastBinaryNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Check BLAST_BIN_DIR');

        $this->blast->locator()->path(BlastTool::BlastDbCmd);
    }

    public function testNonExecutableBinaryIsRejected(): void
    {
        new Filesystem()->chmod($this->blast->binary('blastx'), 0o644);

        $this->expectException(BlastBinaryNotFoundException::class);

        new BlastBinaryLocator($this->blast->directory->path)->path(BlastProgram::Blastx);
    }
}
