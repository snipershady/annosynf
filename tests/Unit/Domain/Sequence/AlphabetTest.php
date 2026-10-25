<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\Alphabet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alphabet::class)]
final class AlphabetTest extends TestCase
{
    public function testDnaAndRnaShareTheNucleotideCodes(): void
    {
        foreach ([Alphabet::Dna, Alphabet::Rna] as $alphabet) {
            self::assertTrue($alphabet->isNucleotide());
            self::assertSame('ACGTURYSWKMBDHVN-', $alphabet->allowedCharacters());
        }
    }

    public function testProteinsUseTheLegacyAminoAcidCodes(): void
    {
        self::assertFalse(Alphabet::Protein->isNucleotide());
        self::assertSame('ABCDEFGHIKLMNPQRSTUVWYZX*-', Alphabet::Protein->allowedCharacters());
    }
}
