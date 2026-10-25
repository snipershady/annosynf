<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\CpcOrf;
use App\Domain\CodingPotential\CpcOrfFinder;
use App\Domain\CodingPotential\OrfIntegrity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Gli esiti attesi sono quelli di FindCDS di CPC2-beta sulle stesse sequenze
 * (tests/Fixtures/fasta/cpc2_orf_cases.fasta, golden in tests/Fixtures/golden/cpc2_cases).
 */
#[CoversClass(CpcOrfFinder::class)]
#[CoversClass(CpcOrf::class)]
#[CoversClass(OrfIntegrity::class)]
final class CpcOrfFinderTest extends TestCase
{
    public function testAnOrfGoesFromAtgToTheStopIncluded(): void
    {
        $orf = new CpcOrfFinder()->find('GCCATGAAACCCTAAGGG');

        self::assertNotNull($orf);
        self::assertSame('ATGAAACCCTAA', $orf->dna);
        self::assertSame(OrfIntegrity::Complete, $orf->integrity);
        self::assertSame([4, 15], [$orf->start, $orf->end()], 'bases of the sequence, from 1');
        self::assertSame(4, $orf->codons(), 'the stop codon counts');
    }

    public function testWithoutStopTheOrfEndsWithTheLastCompleteCodon(): void
    {
        $orf = new CpcOrfFinder()->find('ATGAAACCCGG');

        self::assertSame('ATGAAACCC', $orf?->dna);
        self::assertSame(OrfIntegrity::Incomplete, $orf->integrity);

        self::assertSame(['ATG', 4], [new CpcOrfFinder()->find('GGGATGA')?->dna, new CpcOrfFinder()->find('GGGATGA')?->start], 'an ATG as last codon is an ORF');
    }

    public function testAnAtgInsideAnOrfDoesNotStartAnother(): void
    {
        self::assertSame('ATGATGAAATAG', new CpcOrfFinder()->find('ATGATGAAATAGCC')?->dna);
        self::assertSame('ATGAAATTTATGCCC', new CpcOrfFinder()->find('ATGAAATTTATGCCC')?->dna);
    }

    public function testTheLongestOrfWinsAcrossFrames(): void
    {
        $orf = new CpcOrfFinder()->find('CCATGAAACCCGGGTAAATGTAG');

        self::assertSame(['ATGAAACCCGGGTAA', 3], [$orf?->dna, $orf?->start]);
        self::assertSame('ATGAAACCCTAG', new CpcOrfFinder()->find('ATGTAAATGAAACCCTAG')?->dna, 'a later, longer ORF in the same frame');
    }

    public function testATieGoesToTheUpstreamOrfInAnyFrame(): void
    {
        // Due ORF di 3 codoni: in frame 0 dalla base 13, in frame 1 dalla base 2. Vince quello a monte.
        self::assertSame(2, new CpcOrfFinder()->find('CATGAAATAATTATGCCCTAG')?->start);
        // In frame 0 dalla base 1, in frame 1 dalla base 11: resta quello del frame 0.
        self::assertSame(1, new CpcOrfFinder()->find('ATGAAATAACATGCCCTAGG')?->start);
    }

    public function testOnlyTheForwardStrandIsSearched(): void
    {
        // Sul reverse complement c'è ATG AAA CCC TAA; sul filamento diretto solo l'ATG finale.
        $orf = new CpcOrfFinder()->find('CTTAGGGTTTCATGG');

        self::assertSame(['ATG', 12, OrfIntegrity::Incomplete], [$orf?->dna, $orf?->start, $orf?->integrity]);
    }

    public function testTheSequenceIsNormalizedAsCpc2Does(): void
    {
        self::assertSame('CCATGGCTTTTAAGG', CpcOrfFinder::normalize(" ccauggcuuuuaagg\n"));
        self::assertSame('ATGGCTTTTAAG', new CpcOrfFinder()->find('ccauggcuuuuaagg')?->dna);
    }

    public function testAmbiguousCodonsExtendTheOrfButNeverStartIt(): void
    {
        self::assertSame('ATGNNNRAAKMSTAG', new CpcOrfFinder()->find('ATGNNNRAAKMSTAG')?->dna);
        self::assertNull(new CpcOrfFinder()->find('ATNAAATAG'));
    }

    public function testWithoutAtgThereIsNoOrf(): void
    {
        self::assertNull(new CpcOrfFinder()->find('GCCGCCTAAGCC'));
        self::assertNull(new CpcOrfFinder()->find('TAATAA'));
        self::assertNull(new CpcOrfFinder()->find('AT'));
        self::assertNull(new CpcOrfFinder()->find(''));
    }
}
