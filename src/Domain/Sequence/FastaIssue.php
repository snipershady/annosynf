<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Problemi che la validazione di un FASTA riconosce (CLAUDE.md §B.6), nell'ordine in cui si riportano.
 */
enum FastaIssue: string
{
    case EmptyFile = 'empty_file';
    case NotFasta = 'not_fasta';
    case EmptyHeader = 'empty_header';
    case SpaceAfterGreaterThan = 'space_after_greater_than';
    case DuplicateId = 'duplicate_id';
    case EmptySequence = 'empty_sequence';
    case InvalidCharacters = 'invalid_characters';
    case LongHeader = 'long_header';
    case LongSequenceLine = 'long_sequence_line';
    case HighNContent = 'high_n_content';

    public function severity(): IssueSeverity
    {
        return match ($this) {
            self::LongHeader, self::LongSequenceLine, self::HighNContent => IssueSeverity::Warning,
            default => IssueSeverity::Error,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EmptyFile => 'The file contains no sequences.',
            self::NotFasta => 'There is text before the first ">" header: this is not a FASTA file.',
            self::EmptyHeader => 'A header has no name after ">".',
            self::SpaceAfterGreaterThan => 'A header has a space right after ">".',
            self::DuplicateId => 'A sequence name (the first word of the header) is used more than once.',
            self::EmptySequence => 'A header is followed by no sequence.',
            self::InvalidCharacters => 'A sequence contains characters that are not IUPAC codes for its alphabet.',
            self::LongHeader => \sprintf('A header is longer than %d characters.', FastaValidator::MAX_LINE_LENGTH),
            self::LongSequenceLine => \sprintf('A sequence line is longer than %d characters.', FastaValidator::MAX_LINE_LENGTH),
            self::HighNContent => 'A sequence has a high fraction of unknown bases (N).',
        };
    }
}
