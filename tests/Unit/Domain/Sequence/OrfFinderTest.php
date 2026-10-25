<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\GeneticCode;
use App\Domain\Sequence\Orf;
use App\Domain\Sequence\OrfFinder;
use App\Domain\Sequence\OrfMode;
use App\Domain\Sequence\ReadingFrame;
use App\Domain\Sequence\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrfFinder::class)]
#[CoversClass(Orf::class)]
#[CoversClass(OrfMode::class)]
#[CoversClass(ReadingFrame::class)]
#[UsesClass(CodonMark::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(Translator::class)]
final class OrfFinderTest extends TestCase
{
    public function testFramesAreSearchedInTheOrderOfDna2Pep(): void
    {
        self::assertSame([1, 3, 2, -1, -3, -2], array_map(static fn (ReadingFrame $frame): int => $frame->value, ReadingFrame::searchOrder()));
        self::assertSame(['+', 0], [ReadingFrame::Plus1->strand(), ReadingFrame::Plus1->offset()]);
        self::assertSame(['-', 2], [ReadingFrame::Minus3->strand(), ReadingFrame::Minus3->offset()]);
    }

    // Gli esiti attesi sono quelli di dna2pep 1.1 sulle stesse sequenze (tests/Fixtures/fasta/orf_cases.fasta).

    public function testStrictOrfsStartAtAtg(): void
    {
        //           frame +1: GCC ATG AAA CCC TAA GGG
        $orf = new OrfFinder()->find('GCCATGAAACCCTAAGGG', OrfMode::Strict);

        self::assertNotNull($orf);
        self::assertSame(ReadingFrame::Plus1, $orf->frame);
        self::assertSame(OrfMode::Strict, $orf->mode);
        self::assertSame('MKP', $orf->peptide);
        self::assertSame('M..', $orf->annotation);
        self::assertSame('ATGAAACCC', $orf->dna, 'the stop is not part of the ORF');
        self::assertSame(3, $orf->length());
        self::assertSame([4, 12], [$orf->start, $orf->end], 'nucleotides on the forward strand, from 1');
    }

    public function testAnyModeAlsoStartsAtAlternativeStartsReadAsMethionine(): void
    {
        //           frame +1: CCC TTG AAA ATG CCC TAA
        $sequence = 'CCCTTGAAAATGCCCTAA';

        self::assertSame('MP', new OrfFinder()->find($sequence, OrfMode::Strict)?->peptide);

        $any = new OrfFinder()->find($sequence, OrfMode::Any);
        self::assertSame('MKMP', $any?->peptide, 'TTG is read as a start: the ORF is translated again with its first codon as a start');
        self::assertSame('m.M.', $any->annotation);
        self::assertSame('TTGAAAATGCCC', $any->dna);
    }

    public function testNoneModeTakesTheLongestSegmentBetweenStopsOnAnyStrand(): void
    {
        $orf = new OrfFinder()->find('TAATTGAAACCCGGGTAG', OrfMode::None);

        self::assertNotNull($orf);
        self::assertSame(ReadingFrame::Minus1, $orf->frame);
        self::assertSame('LPGFQL', $orf->peptide);
        self::assertSame('CTACCCGGGTTTCAATTA', $orf->dna);
        self::assertSame([1, 18], [$orf->start, $orf->end]);
    }

    public function testATieGoesToTheFrameVisitedFirst(): void
    {
        // Due ORF da 2 codoni, in +1 (ATG AAA TAA) e in +3 (GAA ATA AGG ATG CCC TAG): vince +1.
        $orf = new OrfFinder()->find('ATGAAATAAGGATGCCCTAGG', OrfMode::Strict);

        self::assertSame(ReadingFrame::Plus1, $orf?->frame);
        self::assertSame('MK', $orf->peptide);
    }

    public function testOrfsOnTheReverseStrandHaveForwardCoordinates(): void
    {
        // Reverse complement di CTTAGGGTTTCATGG = CCATGAAACCCTAAG: ATG AAA CCC in -3.
        $orf = new OrfFinder()->find('CTTAGGGTTTCATGG', OrfMode::Strict);

        self::assertNotNull($orf);
        self::assertSame(ReadingFrame::Minus3, $orf->frame);
        self::assertSame('-', $orf->strand());
        self::assertSame(2, $orf->frameOffset(), 'Annocript writes frame -3 as 2');
        self::assertSame('MKP', $orf->peptide);
        self::assertSame('ATGAAACCC', $orf->dna);
        self::assertSame([5, 13], [$orf->start, $orf->end]);
        self::assertSame('GGGTTTCAT', substr('CTTAGGGTTTCATGG', $orf->start - 1, $orf->end - $orf->start + 1));
    }

    public function testAnOrfMayRunToTheEndOfTheSequence(): void
    {
        $orf = new OrfFinder()->find('ATGAAACCCGG', OrfMode::Strict);

        self::assertSame('MKP', $orf?->peptide);
        self::assertSame([1, 9], [$orf->start, $orf->end]);
    }

    public function testWithoutStartsOrCodonsThereIsNoOrf(): void
    {
        self::assertNull(new OrfFinder()->find('GCCGCCTAAGCC', OrfMode::Strict));
        self::assertNull(new OrfFinder()->find('TAATAA', OrfMode::Any));
        self::assertNull(new OrfFinder()->find('AC', OrfMode::None), 'not even one codon in any frame');
    }

    public function testAnEmptySegmentCountsWhenNothingLongerExists(): void
    {
        // TAA: in +1 c'è solo lo stop, ma in -1 si legge TTA, un codone.
        self::assertSame(['L', ReadingFrame::Minus1], [new OrfFinder()->find('TAA', OrfMode::None)?->peptide, new OrfFinder()->find('TAA', OrfMode::None)?->frame]);

        // NNN può essere uno stop su entrambi i filamenti: resta il segmento vuoto di +1, che batte la lunghezza
        // iniziale di -1 (dna2pep scrive la stessa riga vuota di quando non trova nulla).
        $orf = new OrfFinder()->find('NNN', OrfMode::None);

        self::assertNotNull($orf);
        self::assertSame(0, $orf->length());
        self::assertSame(ReadingFrame::Plus1, $orf->frame);
        self::assertSame('', $orf->dna);
        self::assertSame([1, 0], [$orf->start, $orf->end], 'an empty ORF ends one base before it starts');
    }

    public function testTheCaseOfTheDnaIsKept(): void
    {
        $orf = new OrfFinder()->find('ccatgaaaccctaag', OrfMode::Strict);

        self::assertSame(ReadingFrame::Plus3, $orf?->frame);
        self::assertSame('atgaaaccc', $orf->dna);
        self::assertSame('MKP', $orf->peptide);
    }

    public function testOnlyDna2PepCharactersAreAccepted(): void
    {
        $finder = new OrfFinder();

        self::assertTrue($finder->accepts('ACGTUacgtuRYKMSWBDHVNrykmswbdhvn'));
        self::assertFalse($finder->accepts('ACGT-ACGT'), 'dna2pep skips the whole sequence');
        self::assertFalse($finder->accepts('ACGTX'));
    }

    public function testTheReverseComplementFollowsIupac(): void
    {
        self::assertSame('NBDHVKMWSRYAACGT', OrfFinder::reverseComplement('ACGTURYSWKMBDHVN'));
        self::assertSame('acgtn', OrfFinder::reverseComplement('nacgt'), 'the case is kept');
        self::assertSame('SW', OrfFinder::reverseComplement('WS'), 'dna2pep swapped S and W; S and W are their own complement');
    }
}
