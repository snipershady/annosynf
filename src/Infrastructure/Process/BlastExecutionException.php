<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

/**
 * Un binario BLAST+ è uscito con errore: il messaggio è lo stderr del processo.
 */
final class BlastExecutionException extends RuntimeException implements AnnosynfException
{
    public static function fromResult(string $tool, ProcessResult $result): self
    {
        $stderr = trim($result->errorOutput);

        return new self(\sprintf(
            '%s exited with code %d%s',
            $tool,
            $result->exitCode,
            $stderr === '' ? ' and no error output.' : ': ' . $stderr,
        ));
    }

    public static function timedOut(string $tool, float $seconds): self
    {
        return new self(\sprintf('%s did not finish within %d seconds.', $tool, (int) $seconds));
    }
}
