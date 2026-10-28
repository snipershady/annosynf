<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;
use Throwable;

final class InvalidBlastDatabaseException extends RuntimeException implements AnnosynfException
{
    public static function at(string $path, ?Throwable $previous = null): self
    {
        return new self(\sprintf('"%s" is not a readable BLAST database.', $path), 0, $previous);
    }
}
