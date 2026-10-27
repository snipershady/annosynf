<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class CorruptArchiveException extends RuntimeException implements AnnosynfException
{
    public static function truncated(string $path): self
    {
        return new self(\sprintf('"%s" is truncated: the compressed data ends before the end of the stream.', $path));
    }

    public static function corrupt(string $path, string $problem): self
    {
        return new self(\sprintf('"%s" is corrupt: %s.', $path, $problem));
    }
}
