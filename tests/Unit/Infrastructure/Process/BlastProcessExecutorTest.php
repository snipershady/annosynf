<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Process;

use App\Infrastructure\Process\BlastExecutionException;
use App\Infrastructure\Process\BlastProcessExecutor;
use App\Infrastructure\Process\ProcessResult;
use App\Tests\Support\RecordingProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlastProcessExecutor::class)]
#[CoversClass(BlastExecutionException::class)]
#[CoversClass(ProcessResult::class)]
final class BlastProcessExecutorTest extends TestCase
{
    public function testTheCommandRunsWithTheTimeoutAndWithoutUsageReport(): void
    {
        $runner = new RecordingProcessRunner(new ProcessResult(0, 'out', ''));

        $result = new BlastProcessExecutor($runner, 30.0)->execute('blastx', ['/opt/blast/blastx', '-version'], '/opt/blast');

        self::assertSame('out', $result->output);
        self::assertSame([['command' => ['/opt/blast/blastx', '-version'], 'workingDirectory' => '/opt/blast', 'timeout' => 30.0, 'environment' => ['BLAST_USAGE_REPORT' => 'false']]], $runner->calls);
    }

    public function testAcceptedExitCodesAreNotErrors(): void
    {
        $result = new BlastProcessExecutor(new RecordingProcessRunner(new ProcessResult(1, '', 'skipped')), 30.0)->execute('blastdbcmd', ['/opt/blast/blastdbcmd'], '/tmp', [0, 1]);

        self::assertSame(1, $result->exitCode);
    }

    public function testAFailureCarriesTheErrorOutput(): void
    {
        $this->expectException(BlastExecutionException::class);
        $this->expectExceptionMessageIsOrContains('makeblastdb exited with code 2: BLAST Database error');

        new BlastProcessExecutor(new RecordingProcessRunner(new ProcessResult(2, '', "BLAST Database error\n")), 30.0)->execute('makeblastdb', ['/opt/blast/makeblastdb'], '/tmp');
    }

    public function testAFailureWithoutErrorOutputSaysSo(): void
    {
        $this->expectExceptionMessageIsOrContains('blastn exited with code 3 and no error output.');

        new BlastProcessExecutor(new RecordingProcessRunner(new ProcessResult(3, '', '')), 30.0)->execute('blastn', ['/opt/blast/blastn'], '/tmp');
    }

    public function testATimeoutIsReported(): void
    {
        $this->expectException(BlastExecutionException::class);
        $this->expectExceptionMessageIsOrContains('rpstblastn did not finish within 30 seconds.');

        new BlastProcessExecutor(new RecordingProcessRunner(new ProcessResult(-1, '', '', true)), 30.0)->execute('rpstblastn', ['/opt/blast/rpstblastn'], '/tmp');
    }
}
