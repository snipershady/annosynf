<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UnirefVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProteinRecord::class)]
#[UsesClass(UniProtHeader::class)]
#[UsesClass(UniRefHeader::class)]
final class ProteinRecordTest extends TestCase
{
    public function testAUniProtEntryKeepsItsTags(): void
    {
        $record = ProteinRecord::fromUniProt(new UniProtHeader(ProteinSource::TrEmbl, 'tr|A0A024R161|A0A024R161_HUMAN', 'A0A024R161', 'A0A024R161_HUMAN', 'Uncharacterized protein', 'Homo sapiens', 9606, 'DNAJC25-GNG10', 4, 1));

        self::assertSame(get_object_vars(new ProteinRecord(ProteinSource::TrEmbl, 'A0A024R161', 'Uncharacterized protein', 'tr|A0A024R161|A0A024R161_HUMAN', 'Homo sapiens', 9606, 'DNAJC25-GNG10', 4, 1)), get_object_vars($record));
    }

    public function testAUniRefClusterUsesItsTaxonomyAsOrganism(): void
    {
        $record = ProteinRecord::fromUniRef(new UniRefHeader('UniRef90_P31946', UnirefVariant::UniRef90, '14-3-3 protein beta/alpha', 12, 'Homo sapiens', 9606, '1433B_HUMAN'));

        self::assertSame(get_object_vars(new ProteinRecord(ProteinSource::UniRef, 'UniRef90_P31946', '14-3-3 protein beta/alpha', organism: 'Homo sapiens', taxonId: 9606, memberCount: 12, representativeId: '1433B_HUMAN')), get_object_vars($record));
    }
}
