<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Il testo non ha la struttura di un FASTA (i problemi di contenuto li riporta il validatore).
 */
final class InvalidFastaException extends DomainException implements AnnosynfException
{
    private function __construct(
        string $message,
        public readonly int $lineNumber,
    ) {
        parent::__construct($message);
    }

    public static function textBeforeFirstHeader(int $lineNumber): self
    {
        return new self(\sprintf('Line %d comes before the first ">" header: this is not a FASTA file.', $lineNumber), $lineNumber);
    }
}
