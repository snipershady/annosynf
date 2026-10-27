<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\CddDomain;
use App\Domain\Reference\CddIdParser;
use App\Domain\Reference\InvalidDomainFileException;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CddIdParser::class)]
#[CoversClass(CddDomain::class)]
#[CoversClass(Pfam2GoParser::class)]
#[CoversClass(Pfam2GoMapping::class)]
#[CoversClass(InvalidDomainFileException::class)]
final class DomainParsersTest extends TestCase
{
    public function testCddDomains(): void
    {
        $domains = iterator_to_array(new CddIdParser()->domains([
            "513053\tpfam00001\t7tm_1\t7 transmembrane receptor (rhodopsin family).\t251\r\n",
            "\n",
            "512444\tCOG1357\tCOG1357\t\t145\n",
            "40050\tLOAD_ACT\tACT\tsmall ligand binding domain.\t76",
        ]));

        self::assertSame([1, 3, 4], array_keys($domains));
        self::assertSame([513053, 'pfam00001', '7tm_1', '7 transmembrane receptor (rhodopsin family).', 251], [$domains[1]->pssmId, $domains[1]->accession, $domains[1]->shortName, $domains[1]->description, $domains[1]->pssmLength]);
        self::assertSame('', $domains[3]->description);
        self::assertSame(['pfam', 'COG', 'LOAD'], [$domains[1]->sourceDatabase(), $domains[3]->sourceDatabase(), $domains[4]->sourceDatabase()]);
        self::assertSame('', new CddDomain(1, '12345', 'x', '', 1)->sourceDatabase());
    }

    public function testPfam2GoMappings(): void
    {
        $parser = new Pfam2GoParser();
        $lines = [
            "!version date: 2026/07/06 18:13:03\n",
            "!description: Mapping of GO terms to Pfam entries.\n",
            "!\n",
            "Pfam:PF00001 7tm_1 > GO:G protein-coupled receptor activity ; GO:0004930\r\n",
            '',
            'Pfam:PF25178 Beta-prop_WDR41 > GO:regulation of autophagy ; GO:0010506',
        ];

        $mappings = iterator_to_array($parser->mappings($lines));

        self::assertSame([4, 6], array_keys($mappings));
        self::assertSame(['pfam00001', '7tm_1', 'GO:0004930', 'G protein-coupled receptor activity'], [$mappings[4]->pfamAccession, $mappings[4]->pfamName, $mappings[4]->goId, $mappings[4]->goName]);
        self::assertSame('pfam25178', $mappings[6]->pfamAccession);
        self::assertSame('2026/07/06', $parser->version($lines));
        self::assertNull($parser->version(['!description: x', 'Pfam:PF00001 7tm_1 > GO:membrane ; GO:0016020', '!version date: 2026/07/06 18:13:03']), 'only the header counts');
    }

    /**
     * @return iterable<string, array{'cdd'|'pfam2go', list<string>, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'four fields' => ['cdd', ["513053\tpfam00001\t7tm_1\t251"], 'cddid_all.tbl line 1: expected 5 tab-separated fields, found 4.'];
        yield 'pssm id' => ['cdd', ["x\tpfam00001\t7tm_1\t\t251"], 'cddid_all.tbl line 1: malformed domain'];
        yield 'length' => ['cdd', ["1\tpfam00001\t7tm_1\t\t-1"], 'cddid_all.tbl line 1: malformed domain'];
        yield 'accession' => ['cdd', ["1\t\t7tm_1\t\t1"], 'cddid_all.tbl line 1: malformed domain'];
        yield 'go id' => ['pfam2go', ['!', 'Pfam:PF00001 7tm_1 > GO:membrane ; GO:16020'], 'pfam2go line 2: expected "Pfam:PF… name > GO:term ; GO:…", found "Pfam:PF00001 7tm_1 > GO:membrane ; GO:16020".'];
        yield 'other database' => ['pfam2go', ['InterPro:IPR000001 Kringle > GO:x ; GO:0000001'], 'pfam2go line 1: expected'];
    }

    /**
     * @param 'cdd'|'pfam2go' $file
     * @param list<string>    $lines
     */
    #[DataProvider('invalidFiles')]
    public function testInvalidFiles(string $file, array $lines, string $message): void
    {
        $this->expectException(InvalidDomainFileException::class);
        $this->expectExceptionMessageIsOrContains($message);

        iterator_to_array($file === 'cdd' ? new CddIdParser()->domains($lines) : new Pfam2GoParser()->mappings($lines));
    }
}
