<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class BlastBinaryNotFoundException extends RuntimeException implements AnnosynfException
{
    public static function at(string $path): self
    {
        return new self(\sprintf('BLAST+ binary "%s" does not exist or is not executable. Check BLAST_BIN_DIR.', $path));
    }
}
