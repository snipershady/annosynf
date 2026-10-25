<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\CompositionSummarizer;
use App\Domain\Sequence\CompositionSummary;
use App\Domain\Sequence\SequenceComposition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceComposition::class)]
#[CoversClass(CompositionSummarizer::class)]
#[CoversClass(CompositionSummary::class)]
final class SequenceCompositionTest extends TestCase
{
    public function testBasesAreCountedIgnoringCase(): void
    {
        $composition = SequenceComposition::of('AAcgTnRY');

        self::assertSame(8, $composition->length);
        self::assertSame([2, 1, 1, 1, 1], [$composition->adenine, $composition->cytosine, $composition->guanine, $composition->thymine, $composition->n]);
        self::assertSame(2, $composition->errors(), 'like the legacy: every character that is not A, C, G, T or N');
    }

    public function testBasePercentagesAreOverTheKnownBasesAndGcIsOverTheLength(): void
    {
        $composition = SequenceComposition::of('AACGTNRY');

        // Come extractPercentages: A, C, G, T, N in % di A+C+G+T+N (6), GC in % della lunghezza (8).
        self::assertEqualsWithDelta(100 * 2 / 6, $composition->adeninePercentage(), 1e-12);
        self::assertEqualsWithDelta(100 / 6, $composition->cytosinePercentage(), 1e-12);
        self::assertEqualsWithDelta(100 / 6, $composition->guaninePercentage(), 1e-12);
        self::assertEqualsWithDelta(100 / 6, $composition->thyminePercentage(), 1e-12);
        self::assertEqualsWithDelta(100 / 6, $composition->nPercentage(), 1e-12);
        self::assertSame(2, $composition->gc());
        self::assertSame(25.0, $composition->gcPercentage());
    }

    public function testUracilIsNotThymine(): void
    {
        $composition = SequenceComposition::of('ACGU');

        self::assertSame(0, $composition->thymine);
        self::assertSame(1, $composition->errors(), 'the legacy computed the composition only for DNA');
    }

    public function testWithoutKnownBasesThePercentagesAreUndefined(): void
    {
        $noBases = SequenceComposition::of('RYKM');

        self::assertNull($noBases->adeninePercentage(), 'the legacy died with a division by zero here');
        self::assertNull($noBases->nPercentage());
        self::assertSame(0.0, $noBases->gcPercentage());

        $empty = SequenceComposition::of('');
        self::assertNull($empty->gcPercentage());
        self::assertSame(0, $empty->errors());
    }

    public function testTheSummaryAveragesThePercentagesOfEachSequence(): void
    {
        $summary = new CompositionSummarizer()->summarize([
            SequenceComposition::of('AAAA'),
            SequenceComposition::of('ACGT'),
            SequenceComposition::of('GGNN'),
        ]);

        self::assertSame(3, $summary->sequences);
        self::assertSame(12, $summary->bases);
        self::assertEqualsWithDelta((100 + 25) / 3, $summary->averageAdenine, 1e-12);
        self::assertEqualsWithDelta(25 / 3, $summary->averageCytosine, 1e-12);
        self::assertEqualsWithDelta((25 + 50) / 3, $summary->averageGuanine, 1e-12);
        self::assertEqualsWithDelta(25 / 3, $summary->averageThymine, 1e-12);
        self::assertEqualsWithDelta(50 / 3, $summary->averageN, 1e-12);
        self::assertEqualsWithDelta((50 + 50) / 3, $summary->averageGc, 1e-12);
        self::assertEqualsWithDelta(100 * 4 / 12, $summary->overallGc, 1e-12, 'overall GC: total G+C over total length');
    }

    public function testSequencesWithoutAPercentageDoNotWeighOnItsAverage(): void
    {
        $summary = new CompositionSummarizer()->summarize([SequenceComposition::of('AAAA'), SequenceComposition::of('RRRR'), SequenceComposition::of('')]);

        self::assertSame(3, $summary->sequences);
        self::assertSame(100.0, $summary->averageAdenine, 'only the sequences with A, C, G, T or N count');
        self::assertSame(0.0, $summary->averageGc, 'GC is defined for every non-empty sequence');
        self::assertSame(0.0, $summary->overallGc);
    }

    public function testAnEmptyFileHasNoAverages(): void
    {
        $summary = new CompositionSummarizer()->summarize([]);

        self::assertSame(0, $summary->sequences);
        self::assertNull($summary->averageAdenine);
        self::assertNull($summary->averageGc);
        self::assertNull($summary->overallGc);
    }
}
