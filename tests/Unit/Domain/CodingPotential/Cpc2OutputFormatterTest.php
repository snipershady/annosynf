<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\CodingLabel;
use App\Domain\CodingPotential\Cpc2OutputFormatter;
use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\CodingPotential\CpcOrf;
use App\Domain\CodingPotential\LegacyNumberFormat;
use App\Domain\CodingPotential\OrfIntegrity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Righe attese copiate dall'output di CPC2-beta (tests/Fixtures/golden).
 */
#[CoversClass(Cpc2OutputFormatter::class)]
#[UsesClass(CodingLabel::class)]
#[UsesClass(Cpc2Prediction::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(OrfIntegrity::class)]
final class Cpc2OutputFormatterTest extends TestCase
{
    public function testALineOfCpc2(): void
    {
        $prediction = new Cpc2Prediction(671, new CpcOrf(str_repeat('ATG', 120), 1, OrfIntegrity::Complete), 120, 0.42175000000012, 9.546447753906, OrfIntegrity::Complete, 0.6501403, CodingLabel::Coding);

        self::assertSame("comp2940_c1_seq1\t671\t120\t0.42175\t9.54644775391\t1\t0.65014\tcoding", new Cpc2OutputFormatter()->line('comp2940_c1_seq1', $prediction));
    }

    public function testShortSequencesHaveAnIntegerFickettScore(): void
    {
        $prediction = new Cpc2Prediction(1, null, 0, 0.0, 0.0, OrfIntegrity::Incomplete, 0.99901912, CodingLabel::Coding);

        self::assertSame("single_base\t1\t0\t0\t0.0\t-1\t0.999019\tcoding", new Cpc2OutputFormatter()->line('single_base', $prediction));
    }
}
