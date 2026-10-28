<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\BlastDbKind;
use App\Domain\Reference\IndexTask;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceKind;
use App\Domain\Reference\UnirefVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexTask::class)]
#[UsesClass(BlastDbKind::class)]
final class IndexTaskTest extends TestCase
{
    public function testEachReleaseTypeIndexesItsOwnProteins(): void
    {
        self::assertSame(
            ['extract_cdd', 'build_blastdb:sp', 'build_blastdb:tr', 'build_ncrna'],
            array_map(static fn (IndexTask $task): string => $task->value, IndexTask::planFor(ReleaseType::UniProtKb)),
        );
        self::assertSame(
            ['extract_cdd', 'build_blastdb:sp', 'build_blastdb:uniref', 'build_ncrna'],
            array_map(static fn (IndexTask $task): string => $task->value, IndexTask::planFor(ReleaseType::UniRef)),
        );
    }

    public function testEveryTaskReadsOneSourceAndProducesOneKindOfDatabase(): void
    {
        $sources = [];
        $kinds = [];
        foreach (IndexTask::cases() as $task) {
            $sources[$task->value] = $task->source();
            $kinds[$task->value] = $task->kind();
        }

        self::assertSame(
            ['extract_cdd' => SourceKind::CddDatabase, 'build_blastdb:sp' => SourceKind::SwissProt, 'build_blastdb:tr' => SourceKind::TrEmbl, 'build_blastdb:uniref' => SourceKind::UniRef, 'build_ncrna' => SourceKind::RnaCentral],
            $sources,
        );
        self::assertSame(
            ['extract_cdd' => BlastDbKind::Cdd, 'build_blastdb:sp' => BlastDbKind::SwissProt, 'build_blastdb:tr' => BlastDbKind::TrEmbl, 'build_blastdb:uniref' => BlastDbKind::UniRef, 'build_ncrna' => BlastDbKind::NonCodingRna],
            $kinds,
        );
    }

    public function testDatabasesKeepTheNamesOfAnnocriptFiles(): void
    {
        self::assertSame('Cdd', IndexTask::ExtractCdd->databaseName(null));
        self::assertSame('uniprot_sprot', IndexTask::SwissProtDatabase->databaseName(null));
        self::assertSame('uniprot_trembl', IndexTask::TrEmblDatabase->databaseName(null));
        self::assertSame('ncRNA', IndexTask::NonCodingRnaDatabase->databaseName(null));
        self::assertSame('uniref50', IndexTask::UniRefDatabase->databaseName(UnirefVariant::UniRef50));
        self::assertSame('uniref90', IndexTask::UniRefDatabase->databaseName(null), 'UniRef90 is the legacy default');
    }
}
