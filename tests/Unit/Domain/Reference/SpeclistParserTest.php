<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\InvalidDomainFileException;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\SpeclistParser;
use App\Domain\Reference\UniProtDocumentHeader;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Estratto reale di speclist.txt 2026_03 (tests/Fixtures/reference/speclist): il legacy non lo analizza, quindi
 * non c'è un golden.
 */
#[CoversClass(SpeclistParser::class)]
#[CoversClass(SpeciesCode::class)]
#[CoversClass(UniProtDocumentHeader::class)]
#[CoversClass(InvalidDomainFileException::class)]
final class SpeclistParserTest extends TestCase
{
    private const string FIXTURE = 'reference/speclist/speclist.txt';

    public function testRealAndVirtualCodes(): void
    {
        $codes = [];

        foreach (new SpeclistParser()->codes(GoldenFixtures::lines(self::FIXTURE)) as $code) {
            $codes[$code->code] = $code;
        }

        self::assertCount(25, $codes);
        self::assertSame(['E', 9606, 'Homo sapiens', 'Human', null, false], [$codes['HUMAN']->kingdom, $codes['HUMAN']->taxonId, $codes['HUMAN']->officialName, $codes['HUMAN']->commonName, $codes['HUMAN']->synonym, $codes['HUMAN']->virtual]);
        self::assertSame(['Zythia resinae', 'Resin sarea lichen', 'Sarea resinae'], [$codes['ZYTRE']->officialName, $codes['ZYTRE']->commonName, $codes['ZYTRE']->synonym]);
        self::assertSame('Desulfurolobus ambivalens', $codes['ACIAM']->synonym);
        self::assertSame('A', $codes['ACIAM']->kingdom);
        self::assertSame('O', $codes['UNCCM']->kingdom);
        self::assertTrue($codes['9ACTN']->virtual);
        self::assertSame('Actinobacteria', $codes['9ACTN']->synonym);
        self::assertSame(['X', 1, 'root', true], [$codes['9ZZZZ']->kingdom, $codes['9ZZZZ']->taxonId, $codes['9ZZZZ']->officialName, $codes['9ZZZZ']->virtual]);
        self::assertSame('2026_03', new SpeclistParser()->release(GoldenFixtures::lines(self::FIXTURE)));
    }

    public function testTheLastCodeOfAFileWithoutFooter(): void
    {
        $codes = iterator_to_array(new SpeclistParser()->codes(["HUMAN E    9606: N=Homo sapiens\r\n", "                 C=Human\r\n"]));

        self::assertSame('Human', $codes[1]->commonName);
    }

    public function testARepeatedNameIsAnError(): void
    {
        $this->expectException(InvalidDomainFileException::class);
        $this->expectExceptionMessageIsOrContains('speclist.txt line 3: repeated "C=" for HUMAN.');

        iterator_to_array(new SpeclistParser()->codes(['HUMAN E    9606: N=Homo sapiens', '                 C=Human', '                 C=Man']));
    }
}
