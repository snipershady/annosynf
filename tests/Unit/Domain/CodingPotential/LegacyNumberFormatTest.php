<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\LegacyNumberFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyNumberFormat::class)]
final class LegacyNumberFormatTest extends TestCase
{
    public function testCFormatG(): void
    {
        self::assertSame('0.00443655', LegacyNumberFormat::c(0.004436554));
        self::assertSame('1.68503e-05', LegacyNumberFormat::c(1.685032e-5), 'exponent with sign and two digits');
        self::assertSame('1.23457e+08', LegacyNumberFormat::c(123456789.0));
        self::assertSame('0.0001', LegacyNumberFormat::c(0.0001), 'exponent -4 is still decimal');
        self::assertSame('0.5', LegacyNumberFormat::c(0.5000001), 'trailing zeros removed');
        self::assertSame('7', LegacyNumberFormat::c(7.0));
        self::assertSame('-0.25', LegacyNumberFormat::c(-0.25));
        self::assertSame('0', LegacyNumberFormat::c(0.0));
        self::assertSame('13.2714233398', LegacyNumberFormat::c(13.27142333984375, 12));
        self::assertSame('1e+100', LegacyNumberFormat::c(1e100, 1));
        self::assertSame('2', LegacyNumberFormat::c(1.5, 0), 'precision 0 counts as 1');
    }

    public function testNonFiniteNumbers(): void
    {
        self::assertSame('nan', LegacyNumberFormat::c(\NAN));
        self::assertSame('inf', LegacyNumberFormat::c(\INF));
        self::assertSame('-inf', LegacyNumberFormat::c(-\INF));
    }

    public function testPythonTwoStr(): void
    {
        self::assertSame('8.50006103516', LegacyNumberFormat::python(8.50006103515625));
        self::assertSame('0.0', LegacyNumberFormat::python(0.0));
        self::assertSame('123456789.0', LegacyNumberFormat::python(123456789.0));
        self::assertSame('1e+16', LegacyNumberFormat::python(1e16));
        self::assertSame('nan', LegacyNumberFormat::python(\NAN));
    }

    public function testRoundTripThroughText(): void
    {
        self::assertSame(-0.16389, LegacyNumberFormat::roundTrip(-0.163890123, 6));
        self::assertSame(5.95758056641, LegacyNumberFormat::roundTrip(5.95758056640625, 12));
    }
}
