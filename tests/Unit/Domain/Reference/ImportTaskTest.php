<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\ImportTask;
use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportTask::class)]
#[UsesClass(SourceCatalogue::class)]
final class ImportTaskTest extends TestCase
{
    public function testTheSecondProteinDatabaseFollowsTheReleaseType(): void
    {
        $uniprot = ImportTask::planFor(ReleaseType::UniProtKb);
        $uniref = ImportTask::planFor(ReleaseType::UniRef);

        self::assertContains(ImportTask::TrEmblProteins, $uniprot);
        self::assertNotContains(ImportTask::UniRefProteins, $uniprot);
        self::assertContains(ImportTask::UniRefProteins, $uniref);
        self::assertNotContains(ImportTask::TrEmblProteins, $uniref);
    }

    public function testMappingsRunAfterTheDataTheyJoin(): void
    {
        foreach (ReleaseType::cases() as $type) {
            $phases = array_map(static fn (ImportTask $task): ReleaseStatus => $task->phase(), ImportTask::planFor($type));
            $firstMapping = array_search(ReleaseStatus::Mapping, $phases, true);

            self::assertSame(7, $firstMapping);
            self::assertSame([ReleaseStatus::Mapping], array_values(array_unique(\array_slice($phases, 7), \SORT_REGULAR)));
            self::assertNotContains(ReleaseStatus::Mapping, \array_slice($phases, 0, 7));
        }
    }

    public function testEveryTaskReadsASourceOfItsRelease(): void
    {
        foreach (ReleaseType::cases() as $type) {
            $sources = array_map(static fn (ImportTask $task): SourceKind => $task->source(), ImportTask::planFor($type));

            self::assertSame([], array_values(array_udiff($sources, SourceCatalogue::sourcesFor($type), static fn (SourceKind $a, SourceKind $b): int => strcmp($a->value, $b->value))));
        }

        self::assertSame(SourceKind::IdMapping, ImportTask::ProteinGo->source());
    }
}
