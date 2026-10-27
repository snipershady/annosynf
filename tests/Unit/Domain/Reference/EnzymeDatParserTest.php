<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\EnzymeDatParser;
use App\Domain\Reference\EnzymeEntry;
use App\Domain\Reference\InvalidEnzymeDatException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnzymeDatParser::class)]
#[CoversClass(EnzymeEntry::class)]
#[CoversClass(InvalidEnzymeDatException::class)]
final class EnzymeDatParserTest extends TestCase
{
    public function testEntries(): void
    {
        $entries = iterator_to_array(new EnzymeDatParser()->entries([
            "CC   Release of 02-Sep-2026\r\n",
            "//\r\n",
            "ID   1.7.99.4\r\n",
            "DE   Transferred entry: 1.7.1.1, 1.7.1.2 and\r\n",
            "DE   1.9.6.1.\r\n",
            "//\r\n",
            'ID   1.1.1.1',
            'DE   alcohol dehydrogenase.',
            'AN   aldehyde reductase.',
            'CA   (1) a primary alcohol + NAD(+) = an aldehyde + NADH + H(+).',
            'CC   -!- Acts on primary or secondary alcohols.',
            'DR   P07327, ADH1A_HUMAN;  P28469, ADH1A_MACMU;',
            'DR   P31134, POTG_ECOLI ;',
            '//',
        ]));

        self::assertSame([3, 7], array_keys($entries), 'keyed by the line of the ID');
        self::assertSame('1.7.99.4', $entries[3]->ecNumber);
        self::assertSame('Transferred entry: 1.7.1.1, 1.7.1.2 and 1.9.6.1.', $entries[3]->description());
        self::assertSame('Transferred entry  1.7.1.1  1.7.1.2 and  1.9.6.1.', $entries[3]->legacyDescription());
        self::assertSame([], $entries[3]->accessions);
        self::assertSame(['P07327', 'P28469', 'P31134'], $entries[7]->accessions);
    }

    public function testEntriesWithoutTerminatorAndProvisionalNumbers(): void
    {
        $entries = iterator_to_array(new EnzymeDatParser()->entries([
            'ID   1.1.1.n1', 'DE   NAD(P)(+) dependent [x] {y}; z', 'ID   1.1.1.74', 'DE   Deleted entry.',
        ]), false);

        self::assertSame(['1.1.1.n1', '1.1.1.74'], array_map(static fn (EnzymeEntry $entry): string => $entry->ecNumber, $entries));
        self::assertSame('NAD(P)( ) dependent  x   y   z', $entries[0]->legacyDescription());
        self::assertSame('Deleted entry.', $entries[1]->description(), 'the last entry is kept without DR lines');
    }

    public function testRelease(): void
    {
        $parser = new EnzymeDatParser();

        self::assertSame('02-Sep-2026', $parser->release(['CC   ---', "CC   Release of 02-Sep-2026\n", '//']));
        self::assertNull($parser->release(['//', 'ID   1.1.1.1', 'CC   Release of 02-Sep-2026']), 'only the header counts');
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'malformed EC number' => [['ID   1.1.1'], 'enzyme.dat line 1: malformed EC number "1.1.1".'];
        yield 'description outside an entry' => [['CC   header', 'DE   orphan'], 'enzyme.dat line 2: "DE" line outside an entry.'];
        yield 'accessions after the terminator' => [['ID   1.1.1.1', 'DE   a.', '//', 'DR   P07327, ADH1A_HUMAN;'], 'enzyme.dat line 4: "DR" line outside an entry.'];
        yield 'no description' => [['ID   1.1.1.1', 'DR   P07327, ADH1A_HUMAN;', '//'], 'enzyme.dat line 1: entry 1.1.1.1 has no "DE" line.'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('invalidFiles')]
    public function testInvalidFiles(array $lines, string $message): void
    {
        $this->expectException(InvalidEnzymeDatException::class);
        $this->expectExceptionMessageIsOrContains($message);

        iterator_to_array(new EnzymeDatParser()->entries($lines));
    }
}
