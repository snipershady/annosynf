<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\GeneticCode;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Il codice genetico standard è quello che dna2pep usa di default (`-m 1`, tabella letta da mod_translate):
 * amminoacido interno, amminoacido come primo codone e annotazione di ciascuno dei 64 codoni.
 */
#[CoversClass(GeneticCode::class)]
#[CoversClass(CodonMark::class)]
final class GeneticCodeParityTest extends TestCase
{
    public function testEveryCodonMatchesDna2Pep(): void
    {
        $code = GeneticCode::standard();
        $comparison = new GoldenComparison();
        $codons = [];

        foreach (GoldenFixtures::lines('golden/genetic_code/table_1.tsv') as $line) {
            [$codon, $internal, $first, $mark] = explode("\t", $line);
            $codons[] = $codon;
            $comparison->same($codon, 'internal', $internal, $code->aminoAcid($codon) ?? '');
            $comparison->same($codon, 'first', $first, $code->firstAminoAcid($codon) ?? '');
            $comparison->same($codon, 'mark', $mark, $code->mark($codon)->value ?? '');
        }

        self::assertSame(GeneticCode::codons(), $codons);
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
