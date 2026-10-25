<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaSlicer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastaSlicer::class)]
#[UsesClass(FastaRecord::class)]
final class FastaSlicerTest extends TestCase
{
    public function testANewSliceStartsWhenTheBasesWrittenExceedTheThreshold(): void
    {
        // 100 basi in 3 slice: soglia int(100 / 3) - 1 = 32; si cambia slice quando le basi scritte sono > 32.
        $slices = $this->slice([20, 13, 30, 3, 10, 24], 3);

        self::assertSame([['s1', 1], ['s2', 1], ['s3', 2], ['s4', 2], ['s5', 3], ['s6', 3]], $slices, '20+13 = 33 > 32 closes the first slice');
    }

    public function testAtTheThresholdTheSliceStaysOpen(): void
    {
        // 67 basi in 2 slice: soglia 32; 32 basi scritte non sono "più di 32", 33 sì.
        self::assertSame([['s1', 1], ['s2', 1], ['s3', 1]], $this->slice([16, 16, 35], 2));
        self::assertSame([['s1', 1], ['s2', 1], ['s3', 2]], $this->slice([16, 17, 34], 2));
    }

    public function testTheNumberOfSlicesCanDifferFromTheRequestedOne(): void
    {
        // 181 basi in 3 slice: soglia 59; ogni sequenza da 60 chiude la sua slice, l'ultima ne apre una quarta.
        self::assertSame([['s1', 1], ['s2', 2], ['s3', 3], ['s4', 4]], $this->slice([60, 60, 60, 1], 3), 'more slices than requested');
        self::assertSame([['s1', 1], ['s2', 1], ['s3', 1]], $this->slice([1, 1, 100], 3), 'fewer slices than requested: the long sequence comes last');
    }

    public function testTheFirstSliceIsNeverLeftEmpty(): void
    {
        // 2 basi in 5 slice: soglia int(2 / 5) - 1 = -1. Il legacy apriva una nuova slice già prima della prima
        // sequenza (slice_1.fasta vuoto, s1 e s2 nelle slice 2 e 3): qui una slice si chiude solo se non è vuota.
        self::assertSame([['s1', 1], ['s2', 2]], $this->slice([1, 1], 5));
        self::assertSame([['s1', 1], ['s2', 2], ['s3', 3]], $this->slice([0, 0, 5], 10), 'a sequence of length zero still fills its slice');
    }

    public function testOneSliceTakesEverything(): void
    {
        self::assertSame([['s1', 1], ['s2', 1], ['s3', 1]], $this->slice([10, 10, 10], 1));
    }

    public function testAtLeastOneSliceIsNeeded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('At least one slice is needed, 0 given.');

        new FastaSlicer()->slices([], 0, 0)->current();
    }

    /**
     * @param list<int> $lengths
     *
     * @return list<array{string, int}> nome della sequenza e numero della slice
     */
    private function slice(array $lengths, int $count): array
    {
        $records = [];

        foreach ($lengths as $index => $length) {
            $records[] = new FastaRecord('s' . ($index + 1), str_repeat('A', $length), 1, $length);
        }

        $sliced = [];

        foreach (new FastaSlicer()->slices($records, array_sum($lengths), $count) as $slice => $record) {
            $sliced[] = [$record->id(), $slice];
        }

        return $sliced;
    }
}
