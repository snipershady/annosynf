<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\GeneticCode;
use App\Domain\Sequence\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Translator::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(CodonMark::class)]
final class TranslatorTest extends TestCase
{
    public function testTheSequenceIsNormalisedBeforeTranslating(): void
    {
        self::assertSame('ACGTTA', Translator::normalize('acguUa'));
        self::assertSame('MK', new Translator()->translate('augaaa'));
    }

    public function testTheFirstCodonIsReadAsAStartOnlyWhenAsked(): void
    {
        $translator = new Translator();

        self::assertSame('ML', $translator->translate('TTGTTG'), 'TTG is methionine only as first codon');
        self::assertSame('LL', $translator->translate('TTGTTG', firstIsStart: false));
        self::assertSame('L', $translator->translate('A-ATTG'), 'the first codon is the first position, even when it gives no amino acid');
    }

    public function testStopsAreReadThroughOrEndTheTranslation(): void
    {
        $translator = new Translator();

        self::assertSame('MK*K', $translator->translate('ATGAAATAAAAA'));
        self::assertSame('MK', $translator->translate('ATGAAATAAAAA', readThroughStop: false));
        self::assertSame('', $translator->translate('TRA', readThroughStop: false), 'TAA and TGA: a stop');
        self::assertSame('X', $translator->translate('YAA', readThroughStop: false), 'CAA comes before TAA: not a stop for translate, like dna2pep');
    }

    public function testDegenerateCodonsGiveOneAminoAcidOrAnAmbiguityCode(): void
    {
        $translator = new Translator();

        self::assertSame('D', $translator->translate('GAY'), 'GAC and GAT are both aspartate');
        self::assertSame('B', $translator->translate('RAY'), 'asparagine or aspartate');
        self::assertSame('Z', $translator->translate('SAA'), 'glutamine or glutamate');
        self::assertSame('X', $translator->translate('NNN'));
        self::assertSame('I', $translator->translate('ATH'));
        self::assertSame('M', $translator->translate('MTG'), 'CTG and ATG: both methionine as first codon');
        self::assertSame('X', $translator->translate('MTG', firstIsStart: false), 'leucine or methionine inside');
    }

    public function testIncompleteAndIllegalCodonsAreSkipped(): void
    {
        $translator = new Translator();

        self::assertSame('MK', $translator->translate('ATGAAAGC'), 'the last two bases are not a codon');
        self::assertSame('MK', $translator->translate('ATGA-AAAA'), 'dna2pep never gets here: it skips sequences with non IUPAC characters');
        self::assertSame('', $translator->translate(''));
    }

    public function testEveryCodonIsAnnotated(): void
    {
        $translator = new Translator();

        self::assertSame('M.m*', $translator->annotate('ATGGCCTTGTAA'));
        self::assertSame('m*', $translator->annotate('MTGYAA'), 'any possible stop is a stop; methionine only as first codon is an alternative start');
        self::assertSame('*.', $translator->annotate('NNNRAYAC'), 'NNN can be a stop; the incomplete codon has no mark');
        self::assertSame([CodonMark::PrimaryStart, CodonMark::Stop], $translator->marks('ATGTAG'));
    }

    public function testAnotherGeneticCodeCanBeUsed(): void
    {
        $mitochondrial = GeneticCode::fromNcbi(
            2,
            'Vertebrate Mitochondrial',
            'FFLLSSSSYY**CCWWLLLLPPPPHHQQRRRRIIMMTTTTNNKKSS**VVVVAAAADDEEGGGG',
            '--------------------------------MMMM---------------M------------',
        );

        self::assertSame('MW', new Translator($mitochondrial)->translate('ATATGA'));
    }
}
