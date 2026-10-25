<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput,
        public bool $timedOut = false,
    ) {
    }
}
