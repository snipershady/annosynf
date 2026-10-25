<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Golden;

use App\Tests\Support\Golden\GoldenComparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GoldenComparisonTest extends TestCase
{
    public function testEveryDifferenceIsCollected(): void
    {
        $comparison = new GoldenComparison();
        $comparison->same('seq1', 'length', 100, 100);
        $comparison->same('seq1', 'strand', '+', '-');
        $comparison->same('seq2', 'frame', 2, 1);

        self::assertSame(3, $comparison->comparisons());
        self::assertSame(["seq1 strand: expected '+', got '-'", 'seq2 frame: expected 2, got 1'], $comparison->failures());
        self::assertSame("2 differences out of 3 comparisons:\nseq1 strand: expected '+', got '-'", $comparison->report(1));
    }

    public function testNumbersAreComparedWithinTheTolerance(): void
    {
        $comparison = new GoldenComparison(1e-6);
        $comparison->close('seq1', 'pI', 5.95758056641, 5.957580);
        $comparison->close('seq1', 'fickett', 0.34424, 0.344242);

        self::assertSame(['seq1 fickett: expected 0.34424, got 0.344242'], $comparison->failures());
    }

    public function testAllowedDifferencesMustBeUsedExactly(): void
    {
        $comparison = new GoldenComparison()
            ->allow('seq1', 'tie between frames 1 and -2')
            ->allow('seq9', 'never differs');
        $comparison->same('seq1', 'frame', 1, 1);
        self::assertFalse($comparison->usedAllowances());

        $comparison->same('seq1', 'frame', 1, -2);
        self::assertTrue($comparison->usedAllowances());

        self::assertSame(['seq9: allowed difference never happened, remove it'], $comparison->failures());
    }
}
