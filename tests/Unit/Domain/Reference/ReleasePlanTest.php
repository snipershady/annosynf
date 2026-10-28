<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\ImportTask;
use App\Domain\Reference\ReleasePlan;
use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceKind;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReleasePlan::class)]
#[UsesClass(ImportTask::class)]
#[UsesClass(SourceCatalogue::class)]
final class ReleasePlanTest extends TestCase
{
    public function testTheWorkflowPhasesFollowEachOther(): void
    {
        $order = [ReleaseStatus::Downloading, ReleaseStatus::Importing, ReleaseStatus::Mapping, ReleaseStatus::Indexing];

        foreach (ReleaseType::cases() as $type) {
            $tasks = ReleasePlan::tasks($type);
            $phases = array_values(array_unique(array_map(static fn (string $task): string => ReleasePlan::phase($task)->value, $tasks)));

            self::assertSame(array_map(static fn (ReleaseStatus $status): string => $status->value, $order), $phases);
            self::assertSame('download:' . SourceCatalogue::versionSource($type)->value, $tasks[0]);
            self::assertSame(ReleasePlan::FINALIZE, $tasks[\count($tasks) - 1]);
            self::assertCount(12 + 11 + 1, $tasks);
        }
    }

    public function testDownloadTasksNameTheirSource(): void
    {
        self::assertSame('download:cdd_table', ReleasePlan::download(SourceKind::CddTable));
        self::assertSame(SourceKind::CddTable, ReleasePlan::downloadedSource('download:cdd_table'));
        self::assertNull(ReleasePlan::downloadedSource('download:unknown'));
        self::assertNull(ReleasePlan::downloadedSource('import_go'));
    }

    public function testAnUnknownTaskHasNoPhase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('"build_blastdb:sp" is not a release task.');

        ReleasePlan::phase('build_blastdb:sp');
    }
}
