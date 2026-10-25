<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaHeaderStyle;
use App\Domain\Sequence\FastaRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastaFormatter::class)]
#[CoversClass(FastaHeaderStyle::class)]
#[CoversClass(FastaRecord::class)]
final class FastaFormatterTest extends TestCase
{
    public function testTheSequenceIsWrappedAtSixtyColumns(): void
    {
        $formatted = new FastaFormatter()->format(new FastaRecord('seq1', str_repeat('A', 60) . str_repeat('C', 61), 1, 121));

        self::assertSame(">seq1\n" . str_repeat('A', 60) . "\n" . str_repeat('C', 60) . "\nC\n", $formatted);
    }

    public function testTheHeaderIsRebuiltLikeBioPerl(): void
    {
        $formatter = new FastaFormatter();
        $record = new FastaRecord("\tseq1   a transcript  with   spaces  ", 'ACGT', 1, 4);

        self::assertSame(">seq1 a transcript  with   spaces\nACGT\n", $formatter->format($record), 'one space after the id, the description as it is');
        self::assertSame(">seq1\nACGT\n", $formatter->format($record, FastaHeaderStyle::IdOnly), 'the normalised FASTA given to the tools');
    }

    public function testAnEmptySequenceKeepsAnEmptyLineLikeBioPerl(): void
    {
        self::assertSame(">empty\n\n", new FastaFormatter()->format(new FastaRecord('empty', '', 1, 0)));
    }

    public function testTheWidthCanChange(): void
    {
        self::assertSame(">s\nACG\nT\n", new FastaFormatter(3)->format(new FastaRecord('s', 'ACGT', 1, 4)));
    }
}
