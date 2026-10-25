<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\CpcOrfFinder;
use App\Domain\CodingPotential\FickettScore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Valori attesi di CPC2-beta (tests/Fixtures/golden/cpc2_cases/cpc2.tsv), scritti come li stampa Python 2
 * (12 cifre significative).
 */
#[CoversClass(FickettScore::class)]
#[UsesClass(CpcOrfFinder::class)]
final class FickettScoreTest extends TestCase
{
    public function testScoreOfTheWholeSequence(): void
    {
        self::assertSame('0.26962', $this->python(new FickettScore()->of('GCCATGAAACCCTAAGGG')));
        self::assertSame('0.23483', $this->python(new FickettScore()->of(str_repeat('A', 44))), 'a homopolymer hits the highest position threshold');
    }

    public function testOtherLettersCountInTheLengthOnly(): void
    {
        self::assertSame('0.25877', $this->python(new FickettScore()->of('NNNNNNNNNNACGTACGTAAGGCT')));
    }

    public function testTheSequenceIsNormalizedAsCpc2Does(): void
    {
        self::assertSame('0.36163', $this->python(new FickettScore()->of('ccauggcuuuuaagg')));
    }

    public function testSequencesLongerThanABlock(): void
    {
        // Valori di Fickett.fickett_value di CPC2 (repr), calcolati nel container legacy-runner.
        self::assertSame(0.29268000000000005, new FickettScore()->of(str_repeat('ACGTTGCAAGT', 10000) . 'GGA'));
        self::assertSame(0.24471, new FickettScore()->of(str_repeat('AAAC', 30000)));
    }

    public function testShortSequences(): void
    {
        self::assertSame('0.22328', $this->python(new FickettScore()->of('AT')), 'two bases are enough');
        self::assertSame(0.0, new FickettScore()->of('A'));
        self::assertSame(0.0, new FickettScore()->of(''));
    }

    private function python(float $value): string
    {
        return \sprintf('%.12g', $value);
    }
}
