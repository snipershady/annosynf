<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\IdMappingParser;
use App\Domain\Reference\InvalidDomainFileException;
use App\Domain\Reference\UnirefVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdMappingParser::class)]
#[CoversClass(IdMappingEntry::class)]
#[CoversClass(InvalidDomainFileException::class)]
final class IdMappingParserTest extends TestCase
{
    public function testFieldsAreRecognisedByTheirValues(): void
    {
        $entries = iterator_to_array(new IdMappingParser()->entries([
            "Q6GZX4\t001R_FRG3G\t2947773\tYP_031579.1\t47060116; 81941549\t\tGO:0046782; GO:0033644\tUniRef100_Q6GZX4\tUniRef90_Q6GZX4\tUniRef50_Q6GZX4\tUPI00003B0FD4\t\t654924\t\t\t15165820\tAY548484\tAAT09660.1\t\t\t\t\r\n",
            "\n",
            "P85941\t14333_PSEMZ\t\t\t205686168\t\t\tUniRef100_P85941\t\t\tUPI00017BE7CB\t\t3357",
            // colonne in un altro ordine: contano i valori
            "P99999\tUniRef90_P00001\tGO:0005737",
        ]));

        self::assertSame([1, 3, 4], array_keys($entries));
        self::assertSame('Q6GZX4', $entries[1]->accession);
        self::assertSame(['GO:0046782', 'GO:0033644'], $entries[1]->goIds);
        self::assertSame(['UniRef100' => 'UniRef100_Q6GZX4', 'UniRef90' => 'UniRef90_Q6GZX4', 'UniRef50' => 'UniRef50_Q6GZX4'], $entries[1]->clusters);
        self::assertSame([], $entries[3]->goIds);
        self::assertSame('UniRef100_P85941', $entries[3]->cluster(UnirefVariant::UniRef100));
        self::assertNull($entries[3]->cluster(UnirefVariant::UniRef90));
        self::assertSame(['GO:0005737'], $entries[4]->goIds);
        self::assertSame('UniRef90_P00001', $entries[4]->cluster(UnirefVariant::UniRef90));
    }

    public function testTheAccessionIsRequired(): void
    {
        $this->expectException(InvalidDomainFileException::class);
        $this->expectExceptionMessageIsOrContains('idmapping_selected.tab line 2: the first column must be a UniProtKB accession.');

        iterator_to_array(new IdMappingParser()->entries(["Q6GZX4\t001R_FRG3G", "\t002L_FRG3G\t\t\t\t\tGO:0033644"]));
    }
}
