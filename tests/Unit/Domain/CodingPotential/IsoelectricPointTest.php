<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\IsoelectricPoint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Valori esatti di Biopython 1.76 (tests/Fixtures/golden/cpc2_cases/isoelectric.tsv, colonna repr).
 */
#[CoversClass(IsoelectricPoint::class)]
final class IsoelectricPointTest extends TestCase
{
    public function testUnchargedResiduesLeaveOnlyTheTermini(): void
    {
        self::assertSame(5.27496337890625, new IsoelectricPoint()->of('MAGLIVFWNQST'));
    }

    public function testTheBracketMovesUpOrDownFromSeven(): void
    {
        self::assertSame(12.70721435546875, new IsoelectricPoint()->of('MKKKKRRRRRRHHHHKKKK'));
        self::assertSame(2.99310302734375, new IsoelectricPoint()->of('MDDDDEEEEEEDDDDEEEE'));
        self::assertSame(8.75006103515625, new IsoelectricPoint()->of('K'));
    }

    public function testTerminalPksDependOnTheFirstAndLastResidue(): void
    {
        self::assertSame(5.49395751953125, new IsoelectricPoint()->of('AKRHDECYD'));
        self::assertSame(5.58795166015625, new IsoelectricPoint()->of('PKRHDECYE'));
        self::assertSame(8.18426513671875, new IsoelectricPoint()->of('GKRHDECYK'), 'G and K keep the default terminal pKs');
    }

    public function testOtherCharactersAreIgnored(): void
    {
        self::assertSame(4.36907958984375, new IsoelectricPoint()->of('MKP*DEC*Y'));
    }

    public function testAnEmptyProteinHasNoIsoelectricPoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IsoelectricPoint()->of('');
    }
}
