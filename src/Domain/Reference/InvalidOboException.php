<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Un file OBO della Gene Ontology non ha il formato atteso.
 */
final class InvalidOboException extends DomainException implements AnnosynfException
{
    public static function atLine(int $lineNumber, string $problem): self
    {
        return new self(\sprintf('OBO line %d: %s.', $lineNumber, $problem));
    }
}
