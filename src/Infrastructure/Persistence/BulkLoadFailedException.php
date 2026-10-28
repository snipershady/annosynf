<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;
use Throwable;

final class BulkLoadFailedException extends RuntimeException implements AnnosynfException
{
    public static function rejected(string $table, Throwable $previous): self
    {
        return new self(\sprintf('Loading rows into "%s" failed: %s', $table, $previous->getMessage()), 0, $previous);
    }

    /**
     * @param list<string> $messages i primi avvisi di MySQL
     */
    public static function warnings(string $table, int $count, array $messages): self
    {
        return new self(\sprintf('Loading rows into "%s" raised %d warning(s): %s', $table, $count, implode(' | ', $messages)));
    }
}
