<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\GeneticCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeneticCode::class)]
#[CoversClass(CodonMark::class)]
final class GeneticCodeTest extends TestCase
{
    public function testTheStandardCodeIsNcbiTableOne(): void
    {
        $code = GeneticCode::standard();

        self::assertSame(1, $code->id);
        self::assertSame('Standard', $code->name);
        self::assertSame('F', $code->aminoAcid('TTT'));
        self::assertSame('W', $code->aminoAcid('TGG'));
        self::assertSame('G', $code->aminoAcid('GGG'));
    }

    public function testCodonsFollowTheNcbiOrder(): void
    {
        $codons = GeneticCode::codons();

        self::assertCount(64, $codons);
        self::assertSame(['TTT', 'TTC', 'TTA', 'TTG', 'TCT'], \array_slice($codons, 0, 5));
        self::assertSame('GGG', $codons[63]);
    }

    public function testStopsAndStartsOfTheStandardCode(): void
    {
        $code = GeneticCode::standard();
        $stops = array_values(array_filter(GeneticCode::codons(), $code->isStop(...)));

        self::assertSame(['TAA', 'TAG', 'TGA'], $stops);
        self::assertSame('M', $code->firstAminoAcid('ATG'));
        self::assertSame('M', $code->firstAminoAcid('TTG'), 'alternative start: methionine only as first codon');
        self::assertSame('L', $code->aminoAcid('TTG'));
        self::assertSame('M', $code->firstAminoAcid('CTG'));
        self::assertSame('V', $code->firstAminoAcid('GTG'), 'GTG is not a start in the standard code');
        self::assertSame('*', $code->firstAminoAcid('TAA'));
    }

    public function testEveryCodonHasTheMarkUsedByTheAnnotation(): void
    {
        $code = GeneticCode::standard();

        self::assertSame(CodonMark::PrimaryStart, $code->mark('ATG'));
        self::assertSame(CodonMark::AlternativeStart, $code->mark('TTG'));
        self::assertSame(CodonMark::AlternativeStart, $code->mark('CTG'));
        self::assertSame(CodonMark::Stop, $code->mark('TGA'));
        self::assertSame(CodonMark::Other, $code->mark('GCC'));
        self::assertSame(['*', 'M', 'm', '.'], array_map(static fn (CodonMark $mark): string => $mark->value, CodonMark::cases()));
    }

    public function testOnlyExactUppercaseDnaCodonsAreTranslated(): void
    {
        $code = GeneticCode::standard();

        foreach (['atg', 'AUG', 'ATN', 'AT', 'ATGA', ''] as $notACodon) {
            self::assertNull($code->aminoAcid($notACodon), $notACodon);
            self::assertNull($code->firstAminoAcid($notACodon), $notACodon);
            self::assertNull($code->mark($notACodon), $notACodon);
            self::assertFalse($code->isStop($notACodon), $notACodon);
        }
    }

    public function testAnyNcbiTableCanBeBuilt(): void
    {
        // Codice 2 (mitocondriale dei vertebrati): AGA/AGG sono stop, ATA è metionina, quattro start.
        $code = GeneticCode::fromNcbi(
            2,
            'Vertebrate Mitochondrial',
            'FFLLSSSSYY**CCWWLLLLPPPPHHQQRRRRIIMMTTTTNNKKSS**VVVVAAAADDEEGGGG',
            '--------------------------------MMMM---------------M------------',
        );

        self::assertTrue($code->isStop('AGA'));
        self::assertSame('W', $code->aminoAcid('TGA'));
        self::assertSame(CodonMark::PrimaryStart, $code->mark('ATA'));
        self::assertSame(CodonMark::AlternativeStart, $code->mark('ATT'));
        self::assertSame(CodonMark::AlternativeStart, $code->mark('GTG'));
    }

    public function testATableNeedsSixtyFourCodons(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An NCBI genetic code needs 64 amino acids and 64 start marks, got 3 and 64.');

        GeneticCode::fromNcbi(99, 'Broken', 'FFL', str_repeat('-', 64));
    }
}
