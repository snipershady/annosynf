<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\HeaderTags;
use App\Domain\Reference\InvalidProteinHeaderException;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UniRefHeaderParser;
use App\Domain\Reference\UnirefVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UniRefHeaderParser::class)]
#[CoversClass(UniRefHeader::class)]
#[CoversClass(HeaderTags::class)]
#[CoversClass(InvalidProteinHeaderException::class)]
final class UniRefHeaderParserTest extends TestCase
{
    public function testAModernUniRefHeader(): void
    {
        $header = new UniRefHeaderParser()->parse(">UniRef100_Q7N2I4 5'-deoxynucleotidase plu3092 n=1 Tax=Photorhabdus laumondii subsp. laumondii (strain DSM 15139 / CIP 105565 / TT01) TaxID=243265 RepID=5DNU_PHOLL\n");

        self::assertSame('UniRef100_Q7N2I4', $header->clusterId);
        self::assertSame(UnirefVariant::UniRef100, $header->variant);
        self::assertSame("5'-deoxynucleotidase plu3092", $header->description);
        self::assertSame(1, $header->memberCount);
        self::assertSame('Photorhabdus laumondii subsp. laumondii (strain DSM 15139 / CIP 105565 / TT01)', $header->taxonomy);
        self::assertSame(243265, $header->taxonId);
        self::assertSame('5DNU_PHOLL', $header->representativeId);
    }

    public function testAnOldHeaderKeepsTheClusterPrefixLikeAnnocript(): void
    {
        $header = new UniRefHeaderParser()->parse('>UniRef50_Q6GZX4 Cluster: Putative transcription factor 001R n=1 tax=Frog virus 3 RepID=001R_FRG3G');

        self::assertSame('Cluster: Putative transcription factor 001R', $header->description);
        self::assertSame('Frog virus 3', $header->taxonomy, 'the lowercase tag of old files');
        self::assertNull($header->taxonId);
    }

    public function testTheDescriptionEndsAtTheLastMemberCount(): void
    {
        $header = new UniRefHeaderParser()->parse('>UniRef90_Q00001 Protein n=2 of a family n=7 Tax=Bacteria TaxID=2');

        self::assertSame('Protein n=2 of a family', $header->description);
        self::assertSame(7, $header->memberCount);
        self::assertNull($header->representativeId);
    }

    public function testHeadersSkipSequenceLines(): void
    {
        $headers = new UniRefHeaderParser()->headers([
            '>UniRef90_Q81KV0 Acetate kinase n=2 Tax=Bacteria TaxID=2 RepID=ACKA_BACAN',
            'MKVLVINCGSSSIKYQ',
            '>UniRef90_Q197F7 Uncharacterized protein Tax=Invertebrate iridescent virus 3',
        ]);

        self::assertSame([1 => 2, 3 => null], array_map(static fn (UniRefHeader $header): ?int => $header->memberCount, iterator_to_array($headers)));
    }

    public function testAnInvalidHeaderReportsItsLine(): void
    {
        $this->expectException(InvalidProteinHeaderException::class);
        $this->expectExceptionMessageIsOrContains('Line 2: UniRef90_Q81KV0: malformed n value "two".');

        iterator_to_array(new UniRefHeaderParser()->headers(['MKVLVINCGSSSIKYQ', '>UniRef90_Q81KV0 Acetate kinase n=two Tax=Bacteria']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'uniprot identifier' => ['>sp|P31946|1433B_HUMAN Protein', 'Malformed identifier "sp|P31946|1433B_HUMAN": expected "UniRef50_…", "UniRef90_…" or "UniRef100_…".'];
        yield 'unknown identity level' => ['>UniRef75_P31946 Protein n=1', 'Malformed identifier "UniRef75_P31946"'];
        yield 'taxon' => ['>UniRef90_P31946 Protein n=1 Tax=Homo sapiens TaxID=-9606', 'UniRef90_P31946: malformed TaxID value "-9606".'];
        yield 'empty line' => ['', 'Not a FASTA header: "".'];
    }

    #[DataProvider('invalidHeaders')]
    public function testInvalidHeaders(string $header, string $message): void
    {
        $this->expectException(InvalidProteinHeaderException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new UniRefHeaderParser()->parse($header);
    }
}
