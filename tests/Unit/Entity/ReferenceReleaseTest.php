<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Entity\ReferenceRelease;
use App\Tests\Support\EntityFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceRelease::class)]
final class ReferenceReleaseTest extends TestCase
{
    public function testANewReleaseIsPending(): void
    {
        $release = EntityFactory::release();

        self::assertNull($release->getId());
        self::assertSame(ReleaseType::UniRef, $release->getType());
        self::assertSame('2026_03', $release->getVersion());
        self::assertSame(UnirefVariant::UniRef90, $release->getUnirefVariant());
        self::assertSame('Cdd', $release->getCddName());
        self::assertSame(ReferenceRelease::SCHEMA_VERSION, $release->getSchemaVersion());
        self::assertSame(ReleaseStatus::Pending, $release->getStatus());
        self::assertSame([], $release->getSourceVersions());
        self::assertNull($release->getCounts());
        self::assertNull($release->getError());
        self::assertSame(EntityFactory::NOW, $release->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertNull($release->getStartedAt());
        self::assertNull($release->getReadyAt());
        self::assertNull(EntityFactory::release(ReleaseType::UniProtKb)->getUnirefVariant());
    }

    public function testTheWorkflowRecordsStartEndAndErrors(): void
    {
        $release = EntityFactory::release();

        $release->setStatus(ReleaseStatus::Pending, ['at' => self::at('10:00')]);
        self::assertNull($release->getStartedAt(), 'still waiting');

        $release->setStatus(ReleaseStatus::Downloading, ['at' => self::at('10:01')]);
        $release->setStatus(ReleaseStatus::Failed, ['at' => self::at('10:02'), 'error' => 'download of trembl failed']);

        self::assertSame(ReleaseStatus::Failed, $release->getStatus());
        self::assertSame('download of trembl failed', $release->getError());

        $release->setStatus(ReleaseStatus::Importing, ['at' => self::at('11:00')]);
        self::assertNull($release->getError(), 'a retry clears the previous error');

        $release->setStatus(ReleaseStatus::Ready, ['at' => self::at('12:00')]);

        self::assertSame(ReleaseStatus::Ready, $release->getStatus());
        self::assertSame('10:01', $release->getStartedAt()?->format('H:i'), 'the first start is kept');
        self::assertSame('12:00', $release->getReadyAt()?->format('H:i'));

        $release->setStatus(ReleaseStatus::Failed, ['at' => self::at('13:00'), 'error' => 'rebuilt']);
        self::assertNull($release->getReadyAt());
    }

    /**
     * @return iterable<string, array{ReleaseStatus, array<string, mixed>}>
     */
    public static function invalidStatusChanges(): iterable
    {
        yield 'no time' => [ReleaseStatus::Importing, []];
        yield 'failure without error' => [ReleaseStatus::Failed, ['at' => new DateTimeImmutable()]];
        yield 'error without failure' => [ReleaseStatus::Ready, ['at' => new DateTimeImmutable(), 'error' => 'x']];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('invalidStatusChanges')]
    public function testAStatusChangeNeedsItsContext(ReleaseStatus $status, array $context): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntityFactory::release()->setStatus($status, $context);
    }

    public function testSourceVersionsAndCountsAreKeptSorted(): void
    {
        $release = EntityFactory::release();
        $release->recordSourceVersion('pfam2go', '2026/07/26');
        $release->recordSourceVersion('enzyme', '02-Sep-2026');
        $release->recordSourceVersion('pfam2go', '2026/08/01');
        $release->recordCounts(['ref_protein' => 12, 'ref_go_term' => 3]);

        self::assertSame(['enzyme' => '02-Sep-2026', 'pfam2go' => '2026/08/01'], $release->getSourceVersions());
        self::assertSame(['ref_go_term' => 3, 'ref_protein' => 12], $release->getCounts());
    }

    /**
     * @return iterable<string, array{ReleaseType, string, ?UnirefVariant}>
     */
    public static function invalidReleases(): iterable
    {
        yield 'version with the date' => [ReleaseType::UniProtKb, '2026_03 of 2026', null];
        yield 'month 13' => [ReleaseType::UniProtKb, '2026_13', null];
        yield 'uniref without variant' => [ReleaseType::UniRef, '2026_03', null];
        yield 'uniprotkb with a variant' => [ReleaseType::UniProtKb, '2026_03', UnirefVariant::UniRef50];
    }

    #[DataProvider('invalidReleases')]
    public function testAnInvalidReleaseIsRejected(ReleaseType $type, string $version, ?UnirefVariant $variant): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReferenceRelease($type, $version, $variant, 'Cdd', EntityFactory::now());
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-28 ' . $time);
    }
}
