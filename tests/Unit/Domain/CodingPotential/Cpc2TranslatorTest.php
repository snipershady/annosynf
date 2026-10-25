<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\Cpc2Translator;
use App\Domain\Sequence\GeneticCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Gli esiti attesi sono quelli di Biopython 1.76 (tests/Fixtures/golden/cpc2_cases/codons.tsv).
 */
#[CoversClass(Cpc2Translator::class)]
#[UsesClass(GeneticCode::class)]
final class Cpc2TranslatorTest extends TestCase
{
    public function testExactCodonsAndStops(): void
    {
        self::assertSame('MKP*', new Cpc2Translator()->translate('ATGAAACCCTAA'));
    }

    public function testAmbiguousCodonsAsBiopython(): void
    {
        $translator = new Cpc2Translator();

        self::assertSame('A', $translator->translate('GCN'), 'every expansion is alanine');
        self::assertSame('L', $translator->translate('YTR'));
        self::assertSame('B', $translator->translate('RAY'), 'aspartate or asparagine');
        self::assertSame('J', $translator->translate('MTH'), 'isoleucine or leucine');
        self::assertSame('X', $translator->translate('SAW'), 'no letter narrower than X');
        self::assertSame('*', $translator->translate('TAR'), 'only stops');
        self::assertSame('X', $translator->translate('TAN'), 'a stop or an amino acid');
        self::assertSame('A', $translator->translate('GCX'), 'X is any base, as in Biopython');
    }

    public function testCodonsBiopythonCannotTranslateBecomeX(): void
    {
        // Biopython solleva TranslationError e CPC2 si ferma: qui il codone vale X.
        self::assertSame('MXK*', new Cpc2Translator()->translate('ATG---AAATAG'));
        self::assertSame('X', new Cpc2Translator()->translate('TAX'));
    }

    public function testBasesAfterTheLastCompleteCodonAreIgnored(): void
    {
        self::assertSame('MK', new Cpc2Translator()->translate('ATGAAACC'));
        self::assertSame('', new Cpc2Translator()->translate('AT'));
    }
}
