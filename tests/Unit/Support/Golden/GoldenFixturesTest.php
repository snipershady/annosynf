<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Golden;

use App\Tests\Support\Golden\GoldenFixtures;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class GoldenFixturesTest extends TestCase
{
    public function testLinesAreStreamedWithoutTerminatorsAndNumberedFromOne(): void
    {
        $lines = iterator_to_array(GoldenFixtures::lines('golden/trial/cpc2.tsv'));

        self::assertCount(111, $lines);
        self::assertSame(1, array_key_first($lines));
        self::assertStringStartsWith('#ID', $lines[1]);
        self::assertStringEndsNotWith("\n", $lines[111]);
    }

    public function testSequenceIdsAreTheFirstTokenOfEachHeader(): void
    {
        $ids = GoldenFixtures::sequenceIds('trial');

        self::assertCount(110, $ids);
        self::assertSame('comp53_c1_seq1', $ids[0]);
    }

    public function testMissingFilesPointToTheGenerator(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('golden-ports.sh');

        GoldenFixtures::path('golden/nope.tab');
    }

    public function testUnknownSetsAreRejected(): void
    {
        $this->expectException(LogicException::class);

        GoldenFixtures::fasta('nope');
    }
}
