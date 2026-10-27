<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Il file di versione di una release UniProt non ha il formato atteso.
 */
final class InvalidReleaseNoteException extends DomainException implements AnnosynfException
{
    public static function missingVersion(ReleaseType $type): self
    {
        return new self(\sprintf('No release version found in the %s release note.', $type->value));
    }

    public static function malformed(ReleaseType $type, string $what, string $value): self
    {
        return new self(\sprintf('Malformed %s in the %s release note: "%s".', $what, $type->value, $value));
    }

    public static function conflictingVersions(ReleaseType $type, string $first, string $second): self
    {
        return new self(\sprintf('The %s release note names two releases: %s and %s.', $type->value, $first, $second));
    }
}
