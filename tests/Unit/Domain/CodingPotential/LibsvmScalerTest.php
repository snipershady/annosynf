<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\InvalidLibsvmFileException;
use App\Domain\CodingPotential\LibsvmScaler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LibsvmScaler::class)]
#[CoversClass(InvalidLibsvmFileException::class)]
final class LibsvmScalerTest extends TestCase
{
    public function testTheRangeFileOfCpc2(): void
    {
        $scaler = LibsvmScaler::parse(["x\r", '-1 1', '1 0 14507', '2 0.22328 0.51262', '', '3 0 13.2714233398', '4 -1 1']);

        self::assertSame([-1.0, 1.0], [$scaler->lower, $scaler->upper]);
        self::assertSame([0.22328, 0.51262], $scaler->ranges[2]);
    }

    public function testValuesAreInterpolatedLikeSvmScale(): void
    {
        $scaler = LibsvmScaler::parse(['x', '0 10', '1 2 4', '2 0 1', '3 5 5']);

        self::assertSame([1 => 0.0, 2 => 10.0], $scaler->scale([1 => 2.0, 2 => 1.0]), 'minimum and maximum map to the limits');
        self::assertSame([1 => 5.0, 2 => 15.0], $scaler->scale([1 => 3.0, 2 => 1.5]), 'values outside the range are not clipped');
        self::assertSame([], $scaler->scale([3 => 5.0, 7 => 1.0]), 'single-valued features and features without range are dropped');
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'target range' => [['y', '-1 1'], 'line 1: only feature ranges ("x") are supported'];
        yield 'one limit' => [['x', '-1', '1 0 1'], 'line 2: expected "lower upper"'];
        yield 'two values' => [['x', '-1 1', '1 0'], 'line 3: expected "index min max"'];
        yield 'index zero' => [['x', '-1 1', '0 0 1'], 'line 3: expected "index min max"'];
        yield 'not a number' => [['x', '-1 1', '1 0 max'], 'line 3: "max" is not a number'];
        yield 'no ranges' => [['x', '-1 1'], 'libsvm range: no feature ranges.'];
        yield 'empty' => [[], 'no feature ranges'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('invalidRanges')]
    public function testInvalidRangesAreRejected(array $lines, string $message): void
    {
        $this->expectException(InvalidLibsvmFileException::class);
        $this->expectExceptionMessageIsOrContains($message);

        LibsvmScaler::parse($lines);
    }
}
