<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\UniProtDocumentHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathwayTxtParser::class)]
#[CoversClass(Pathway::class)]
#[CoversClass(UniProtDocumentHeader::class)]
final class PathwayTxtParserTest extends TestCase
{
    private const string RULE = '--------------------------------------------------------------------------------';

    public function testPathwaysOfTheBlocksWithAccessions(): void
    {
        $pathways = new PathwayTxtParser()->pathways([
            self::RULE,
            '        European Bioinformatics Institute (EBI); Hinxton, United Kingdom',
            self::RULE,
            'Description: PATHWAY comments: index',
            'Release:     2026_03 of 02-Sep-2026',
            self::RULE . "\r\n",
            "Amine biosynthesis; no proteins yet.\r\n",
            "\r\n",
            "Amino-acid biosynthesis; L-lysine biosynthesis.  \r\n",
            "     DAPA_ECOLI  (P0A6L2)    , DAPA_BACSU  (Q04796)    ,\r\n",
            "     DAPB_ECOLI  (P04036)    \r\n",
            'Amino-acid biosynthesis; no proteins.',
            '9-membered ring; last pathway',
            '     X_ECOLI  (P00001)',
            self::RULE,
            'Copyrighted by the UniProt Consortium, see https://www.uniprot.org/terms',
            'Distributed under the Creative Commons Attribution (CC BY 4.0) License',
            self::RULE,
        ]);

        $pathways = iterator_to_array($pathways);

        self::assertSame([7, 9, 12, 13], array_keys($pathways), 'keyed by the line of the description');
        self::assertSame('Amine biosynthesis; no proteins yet', $pathways[7]->description, 'a pathway before the first accessions is kept');
        self::assertSame([], $pathways[7]->accessions);
        self::assertSame(['P0A6L2', 'Q04796', 'P04036'], $pathways[9]->accessions);
        self::assertSame(['Amino-acid biosynthesis', 'L-lysine biosynthesis'], $pathways[9]->levels());
        self::assertSame([], $pathways[12]->accessions);
        self::assertSame(['P00001'], $pathways[13]->accessions);
    }

    public function testAFileWithoutRulesOrFooter(): void
    {
        $pathways = iterator_to_array(new PathwayTxtParser()->pathways(['Alkaloid biosynthesis.', '  A_B (P1), C_D (P2)', 'Other']), false);

        self::assertSame(['Alkaloid biosynthesis', 'Other'], array_map(static fn (Pathway $pathway): string => $pathway->description, $pathways));
        self::assertSame([], iterator_to_array(new PathwayTxtParser()->pathways(['Only a description', self::RULE, 'Another'])), 'no accessions, no pathways');
    }

    public function testRelease(): void
    {
        $parser = new PathwayTxtParser();

        self::assertSame('2026_03', $parser->release([self::RULE, 'Release:     2026_03 of 02-Sep-2026', self::RULE]));
        self::assertNull($parser->release([self::RULE, self::RULE, self::RULE, 'Release:     2026_03 of 02-Sep-2026']), 'only the header counts');
        self::assertNull($parser->release([]));
    }
}
