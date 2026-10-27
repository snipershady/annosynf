<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Un file dei domini (`cddid_all.tbl`, `pfam2go`) non ha il formato atteso.
 */
final class InvalidDomainFileException extends DomainException implements AnnosynfException
{
    public static function atLine(string $file, int $lineNumber, string $problem): self
    {
        return new self(\sprintf('%s line %d: %s.', $file, $lineNumber, $problem));
    }
}
