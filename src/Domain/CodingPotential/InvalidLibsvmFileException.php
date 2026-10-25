<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Un file di modello o di range di libsvm non ha il formato atteso (o descrive un modello non supportato).
 */
final class InvalidLibsvmFileException extends DomainException implements AnnosynfException
{
    public static function at(string $file, int $lineNumber, string $problem): self
    {
        return new self(\sprintf('%s, line %d: %s.', $file, $lineNumber, $problem));
    }

    public static function incomplete(string $file, string $problem): self
    {
        return new self(\sprintf('%s: %s.', $file, $problem));
    }
}
