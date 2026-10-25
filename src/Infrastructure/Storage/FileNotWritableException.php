<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class FileNotWritableException extends RuntimeException implements AnnosynfException
{
    public static function at(string $path): self
    {
        return new self(\sprintf('File "%s" cannot be written.', $path));
    }
}
