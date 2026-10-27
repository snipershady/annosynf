<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Il file `enzyme.dat` non ha il formato atteso.
 */
final class InvalidEnzymeDatException extends DomainException implements AnnosynfException
{
    public static function atLine(int $lineNumber, string $problem): self
    {
        return new self(\sprintf('enzyme.dat line %d: %s.', $lineNumber, $problem));
    }
}
