<?php

declare(strict_types=1);

namespace App\Release;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class ReleaseNotCreatedException extends RuntimeException implements AnnosynfException
{
    public static function exists(string $name, int $id): self
    {
        return new self(\sprintf('Release %s already exists (id %d): retry or delete it.', $name, $id));
    }

    public static function otherVersion(string $requested, string $available): self
    {
        return new self(\sprintf('The sources hold release %s, not %s: only the current release can be downloaded.', $available, $requested));
    }

    public static function otherVariant(string $requested, string $declared): self
    {
        return new self(\sprintf('The release note describes %s, not %s.', $declared, $requested));
    }
}
