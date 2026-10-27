<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use App\Domain\Shared\AnnosynfException;
use DomainException;

/**
 * Un header FASTA di UniProtKB o UniRef non ha il formato atteso.
 */
final class InvalidProteinHeaderException extends DomainException implements AnnosynfException
{
    public static function notAHeader(string $line): self
    {
        return new self(\sprintf('Not a FASTA header: "%s".', self::excerpt($line)));
    }

    public static function malformedIdentifier(string $identifier, string $expected): self
    {
        return new self(\sprintf('Malformed identifier "%s": expected %s.', self::excerpt($identifier), $expected));
    }

    public static function malformedTag(string $identifier, string $tag, string $value): self
    {
        return new self(\sprintf('%s: malformed %s value "%s".', $identifier, $tag, self::excerpt($value)));
    }

    public static function atLine(int $lineNumber, self $previous): self
    {
        return new self(\sprintf('Line %d: %s', $lineNumber, $previous->getMessage()), 0, $previous);
    }

    private static function excerpt(string $text): string
    {
        return mb_strimwidth($text, 0, 120, '…');
    }
}
