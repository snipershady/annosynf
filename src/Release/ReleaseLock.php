<?php

declare(strict_types=1);

namespace App\Release;

/**
 * Nome del lock che un worker tiene su una release mentre esegue un task.
 */
final class ReleaseLock
{
    public static function name(int $releaseId): string
    {
        return 'annosynf-release-' . $releaseId;
    }
}
