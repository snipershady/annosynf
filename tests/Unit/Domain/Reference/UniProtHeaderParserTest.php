<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\HeaderTags;
use App\Domain\Reference\InvalidProteinHeaderException;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniProtHeaderParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UniProtHeaderParser::class)]
#[CoversClass(UniProtHeader::class)]
#[CoversClass(HeaderTags::class)]
#[CoversClass(InvalidProteinHeaderException::class)]
final class UniProtHeaderParserTest extends TestCase
{
    public function testAModernSwissProtHeader(): void
    {
        $header = new UniProtHeaderParser()->parse(">sp|P31946|1433B_HUMAN 14-3-3 protein beta/alpha OS=Homo sapiens OX=9606 GN=YWHAB PE=1 SV=3\r\n");

        self::assertSame(ProteinSource::SwissProt, $header->source);
        self::assertSame('sp|P31946|1433B_HUMAN', $header->entryId);
        self::assertSame('P31946', $header->accession);
        self::assertSame('1433B_HUMAN', $header->entryName);
        self::assertSame('14-3-3 protein beta/alpha', $header->description);
        self::assertSame('Homo sapiens', $header->organism);
        self::assertSame(9606, $header->taxonId);
        self::assertSame('YWHAB', $header->geneName);
        self::assertSame(1, $header->proteinExistence);
        self::assertSame(3, $header->sequenceVersion);
    }

    public function testTheDescriptionEndsAtTheLastOrganismTagLikeAnnocript(): void
    {
        $header = new UniProtHeaderParser()->parse('>tr|Q00001|Q00001_HUMAN Protein PE=2 OS=fake OS=Homo sapiens OX=9606 PE=1 PE=3 SV=1');

        self::assertSame('Protein PE=2 OS=fake', $header->description);
        self::assertSame('Homo sapiens', $header->organism);
        self::assertSame(1, $header->proteinExistence, 'a repeated tag keeps its first value');
    }

    public function testMissingAndEmptyTagsAreNull(): void
    {
        $header = new UniProtHeaderParser()->parse('>tr|Q00002|Q00002_HUMAN Only a description GN= SV=2');

        self::assertSame('Only a description', $header->description);
        self::assertNull($header->organism);
        self::assertNull($header->taxonId);
        self::assertNull($header->geneName);
        self::assertNull($header->proteinExistence);
        self::assertSame(2, $header->sequenceVersion);
        self::assertSame('', new UniProtHeaderParser()->parse('>sp|Q00003|Q00003_HUMAN')->description);
    }

    public function testHeadersSkipSequenceLinesAndKeepLineNumbers(): void
    {
        $headers = new UniProtHeaderParser()->headers([
            '>sp|Q6GZX4|001R_FRG3G Putative transcription factor 001R OS=Frog virus 3 (isolate Goorha) OX=654924 GN=FV3-001R PE=4 SV=1',
            'MAFSAEDVLKEYDRRRRMEALLLSLYYPNDRKLLDYKEWSPPRVQVECPKAPVEWNNPPS',
            'EKGLIVGHFSGIKYKGEKAQASEVDVNKMCCWVSKFKDAMRRYQGIQTCKIPGKVLSDLD',
            '>tr|A0A0U3GSY3|A0A0U3GSY3_9CREN 16S rRNA methyltransferase OS=Sulfolobus acidocaldarius OX=2285 GN=ATY89_03280 PE=3 SV=1',
        ]);

        self::assertSame([1 => 'Q6GZX4', 4 => 'A0A0U3GSY3'], array_map(static fn (UniProtHeader $header): string => $header->accession, iterator_to_array($headers)));
    }

    public function testAnInvalidHeaderReportsItsLine(): void
    {
        $this->expectException(InvalidProteinHeaderException::class);
        $this->expectExceptionMessageIsOrContains('Line 3: sp|Q6GZX4|001R_FRG3G: malformed OX value "9606a".');

        iterator_to_array(new UniProtHeaderParser()->headers(['>sp|Q6GZX3|002L_FRG3G Protein OS=Frog virus 3', 'MAFSAEDVLK', '>sp|Q6GZX4|001R_FRG3G Protein OS=Homo sapiens OX=9606a PE=1 SV=1']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'sequence line' => ['MAFSAEDVLKEYDRRRRMEALLLSLYYPNDRKLLDYKEWSPPRVQVECPKAPVEWNNPPS', 'Not a FASTA header: "MAFSAEDVLKEYDRRRRMEALLLSLYYPNDRKLLDYKEWSPPRVQVECPKAPVEWNNPPS".'];
        yield 'space after ">"' => ['> sp|P31946|1433B_HUMAN Protein', 'Not a FASTA header: "> sp|P31946|1433B_HUMAN Protein".'];
        yield 'uniref identifier' => ['>UniRef90_P31946 Protein n=1', 'Malformed identifier "UniRef90_P31946": expected "sp|ACCESSION|NAME" or "tr|ACCESSION|NAME".'];
        yield 'two fields' => ['>sp|P31946 Protein', 'Malformed identifier "sp|P31946": expected "sp|ACCESSION|NAME" or "tr|ACCESSION|NAME".'];
        yield 'protein existence' => ['>sp|P31946|1433B_HUMAN Protein OS=Homo sapiens PE=x SV=1', 'sp|P31946|1433B_HUMAN: malformed PE value "x".'];
        yield 'long header' => ['>' . str_repeat('A', 200), 'Malformed identifier "' . str_repeat('A', 119) . '…": expected'];
    }

    #[DataProvider('invalidHeaders')]
    public function testInvalidHeaders(string $header, string $message): void
    {
        $this->expectException(InvalidProteinHeaderException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new UniProtHeaderParser()->parse($header);
    }
}
