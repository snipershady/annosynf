<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\BlastCheckCommand;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastBinaryNotFoundException;
use App\Infrastructure\Process\BlastExecutionException;
use App\Infrastructure\Process\BlastProcessExecutor;
use App\Infrastructure\Process\BlastVersionReader;
use App\Infrastructure\Process\ProcessResult;
use App\Tests\Support\FakeBlastInstallation;
use App\Tests\Support\RecordingProcessRunner;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(BlastCheckCommand::class)]
#[UsesClass(BlastBinaryLocator::class)]
#[UsesClass(BlastBinaryNotFoundException::class)]
#[UsesClass(BlastExecutionException::class)]
#[UsesClass(BlastProcessExecutor::class)]
#[UsesClass(BlastVersionReader::class)]
#[UsesClass(ProcessResult::class)]
final class BlastCheckCommandTest extends TestCase
{
    private FakeBlastInstallation $blast;

    private BufferedOutput $output;

    #[Override]
    protected function setUp(): void
    {
        $this->blast = new FakeBlastInstallation();
        $this->output = new BufferedOutput();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->blast->remove();
    }

    public function testEveryBinaryIsListedWithItsVersion(): void
    {
        $versions = array_map(static fn (string $name): ProcessResult => new ProcessResult(0, $name . ": 2.17.0+\n", ''), ['blastx', 'blastp', 'blastn', 'tblastn', 'rpstblastn', 'rpsblast', 'makeblastdb', 'blastdbcmd']);
        $runner = new RecordingProcessRunner(...$versions);

        self::assertSame(Command::SUCCESS, $this->check($runner));
        self::assertCount(8, $runner->calls);
        self::assertStringContainsString($this->blast->directory->path, $this->output->fetch());
    }

    public function testMissingOrBrokenBinariesMakeTheCheckFail(): void
    {
        new Filesystem()->remove($this->blast->binary('rpsblast'));
        $runner = new RecordingProcessRunner(new ProcessResult(127, '', 'cannot execute'));

        self::assertSame(Command::FAILURE, $this->check($runner));

        $display = $this->output->fetch();
        self::assertStringContainsString('blastx exited with code 127: cannot execute', $display);
        self::assertStringContainsString('rpsblast" does not exist', $display);
        self::assertStringContainsString('unknown', $display, 'a banner without version is reported, not fatal');
    }

    private function check(RecordingProcessRunner $runner): int
    {
        $locator = $this->blast->locator();
        $command = new BlastCheckCommand($locator, new BlastVersionReader($locator, new BlastProcessExecutor($runner, 10.0)));

        return $command(new SymfonyStyle(new ArrayInput([]), $this->output));
    }
}
