<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Reference\TaskStatus;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceTask;
use App\Tests\Support\EntityFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceTask::class)]
#[UsesClass(ReferenceRelease::class)]
final class ReferenceTaskTest extends TestCase
{
    public function testEachAttemptIsCountedAndReplacesThePreviousOutcome(): void
    {
        $release = EntityFactory::release();
        $task = new ReferenceTask($release, 'import_proteins:sp');

        self::assertNull($task->getId());
        self::assertSame($release, $task->getRelease());
        self::assertSame('import_proteins:sp', $task->getTask());
        self::assertSame(TaskStatus::Pending, $task->getStatus());
        self::assertSame(0, $task->getAttempts());

        $task->start(self::at('10:00'));
        $task->fail(self::at('10:05'), 'LOAD DATA failed');

        self::assertSame(TaskStatus::Failed, $task->getStatus());
        self::assertSame('LOAD DATA failed', $task->getMessage());
        self::assertSame('10:05', $task->getFinishedAt()?->format('H:i'));

        $task->start(self::at('11:00'));

        self::assertSame(TaskStatus::Running, $task->getStatus());
        self::assertSame(2, $task->getAttempts());
        self::assertSame('11:00', $task->getStartedAt()?->format('H:i'));
        self::assertNull($task->getFinishedAt());
        self::assertNull($task->getMessage());

        $task->complete(self::at('11:30'), 573_230, '12 headers without OS=');

        self::assertSame(TaskStatus::Done, $task->getStatus());
        self::assertSame(573_230, $task->getRowsLoaded());
        self::assertSame('12 headers without OS=', $task->getMessage());
        self::assertSame('11:30', $task->getFinishedAt()?->format('H:i'));

        $task->start(self::at('12:00'));
        self::assertNull($task->getRowsLoaded());
    }

    #[TestWith([''])]
    #[TestWith(['Import_go'])]
    #[TestWith(['import go'])]
    #[TestWith(['import:'])]
    #[TestWith(['import:sp:tr'])]
    #[TestWith(['a_very_long_task_name_that_does_not_fit_the_column'])]
    public function testAnInvalidNameIsRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReferenceTask(EntityFactory::release(), $name);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-28 ' . $time);
    }
}
