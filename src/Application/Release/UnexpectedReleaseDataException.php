<?php

declare(strict_types=1);

namespace App\Application\Release;

use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class UnexpectedReleaseDataException extends RuntimeException implements AnnosynfException
{
    public static function unirefVariant(string $clusterId, UnirefVariant $expected): self
    {
        return new self(\sprintf('Cluster "%s" does not belong to %s: the file is not the one of this release.', $clusterId, $expected->value));
    }

    public static function proteinSource(string $entryId, ProteinSource $expected): self
    {
        return new self(\sprintf('Entry "%s" is not a %s entry: the file is not the one of this task.', $entryId, $expected->value));
    }

    public static function missingVariant(): self
    {
        return new self('UniRef proteins can only be imported into a uniref release.');
    }
}
