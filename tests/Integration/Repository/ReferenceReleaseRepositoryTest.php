<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Reference\BlastDbKind;
use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SourceKind;
use App\Domain\Reference\TaskStatus;
use App\Domain\Reference\UnirefVariant;
use App\Entity\OrganismSet;
use App\Entity\OrganismSetMember;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Entity\User;
use App\Repository\ReferenceReleaseRepository;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\EntityFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ReferenceReleaseRepository::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(ReferenceSourceFile::class)]
#[UsesClass(ReferenceTask::class)]
#[UsesClass(ReferenceBlastDb::class)]
#[UsesClass(OrganismSet::class)]
#[UsesClass(OrganismSetMember::class)]
#[UsesClass(User::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(BlastDbKind::class)]
final class ReferenceReleaseRepositoryTest extends DatabaseTestCase
{
    public function testAReleaseIsFoundByItsIdentity(): void
    {
        $this->persistAndReload(
            EntityFactory::release(ReleaseType::UniRef, '2026_03', UnirefVariant::UniRef50),
            EntityFactory::release(ReleaseType::UniRef, '2026_03', UnirefVariant::UniRef90),
            EntityFactory::release(ReleaseType::UniProtKb, '2026_03'),
        );
        $releases = self::getContainer()->get(ReferenceReleaseRepository::class);

        self::assertSame(UnirefVariant::UniRef90, $releases->findOneByIdentity(ReleaseType::UniRef, '2026_03', UnirefVariant::UniRef90)?->getUnirefVariant());
        self::assertSame(ReleaseType::UniProtKb, $releases->findOneByIdentity(ReleaseType::UniProtKb, '2026_03', null)?->getType());
        self::assertNull($releases->findOneByIdentity(ReleaseType::UniRef, '2026_03', UnirefVariant::UniRef100));
        self::assertNull($releases->findOneByIdentity(ReleaseType::UniProtKb, '2026_04', null));
    }

    public function testBlastDatabasesAreListedInCreationOrder(): void
    {
        $release = EntityFactory::release(ReleaseType::UniRef, '2026_03');
        $other = EntityFactory::release(ReleaseType::UniRef, '2026_04');
        $this->persistAndReload(
            $release,
            $other,
            new ReferenceBlastDb($release, BlastDbKind::TrEmbl, 'uniprot_trembl', '/blastdb/1/uniprot_trembl', 10, 100, '2.17.0+', EntityFactory::now()),
            new ReferenceBlastDb($release, BlastDbKind::SwissProt, 'uniprot_sprot', '/blastdb/1/uniprot_sprot', 5, 50, '2.17.0+', EntityFactory::now()),
            new ReferenceBlastDb($other, BlastDbKind::SwissProt, 'uniprot_sprot', '/blastdb/2/uniprot_sprot', 5, 50, '2.17.0+', EntityFactory::now()),
        );
        $releases = self::getContainer()->get(ReferenceReleaseRepository::class);
        $loaded = $releases->findAllById()[0];

        self::assertSame(['uniprot_trembl', 'uniprot_sprot'], array_map(static fn (ReferenceBlastDb $database): string => $database->getName(), $releases->blastDatabases($loaded)));
    }

    public function testReleasesTasksAndSourcesAreListedInCreationOrder(): void
    {
        $second = EntityFactory::release(ReleaseType::UniRef, '2026_04');
        $first = EntityFactory::release(ReleaseType::UniRef, '2026_03');
        $tasks = [new ReferenceTask($first, 'import_go'), new ReferenceTask($first, 'download:go'), new ReferenceTask($second, 'finalize')];
        $sources = [new ReferenceSourceFile($first, SourceKind::Pfam2Go, 'https://example.org/pfam2go'), new ReferenceSourceFile($first, SourceKind::GeneOntology, 'https://example.org/go')];
        $this->persistAndReload($first, $second, ...$tasks, ...$sources);
        $releases = self::getContainer()->get(ReferenceReleaseRepository::class);
        $all = $releases->findAllById();

        self::assertSame(['2026_03', '2026_04'], array_map(static fn (ReferenceRelease $release): string => $release->getVersion(), $all));
        self::assertSame(['import_go', 'download:go'], array_map(static fn (ReferenceTask $task): string => $task->getTask(), $releases->tasks($all[0])));
        self::assertSame([SourceKind::Pfam2Go, SourceKind::GeneOntology], array_map(static fn (ReferenceSourceFile $source): SourceKind => $source->getKind(), $releases->sources($all[0])));
        self::assertSame([], $releases->sources($all[1]));
    }

    public function testTheSameUniProtKbReleaseCannotBeCreatedTwice(): void
    {
        $this->persistAndReload(EntityFactory::release(ReleaseType::UniProtKb));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->persistAndReload(EntityFactory::release(ReleaseType::UniProtKb));
    }

    public function testTheWholeCatalogueIsSavedAndReloaded(): void
    {
        $user = EntityFactory::user();
        $release = EntityFactory::release();
        $release->setStatus(ReleaseStatus::Ready, ['at' => EntityFactory::now()]);
        $release->recordSourceVersion('enzyme', '02-Sep-2026');
        $release->recordCounts(['ref_protein' => 3]);

        $source = new ReferenceSourceFile($release, SourceKind::TrEmbl, 'https://example.org/trembl.fasta.gz');
        $source->recordDownload(new SourceFile('https://example.org/trembl.fasta.gz', '/data/trembl.fasta.gz', 43_600_000_000, str_repeat('0', 64), false), EntityFactory::now());

        $task = new ReferenceTask($release, 'import_go');
        $task->start(EntityFactory::now());
        $task->complete(EntityFactory::now(), 48_000);

        $set = new OrganismSet($release, 'humans', OrganismMatchMode::ExactName, EntityFactory::now(), $user);
        $set->addMember('Homo sapiens');
        $set->addMember('homo sapiens');

        $database = new ReferenceBlastDb($release, BlastDbKind::SwissProt, 'sp_humans', '/blastdb/sp_humans', 20_421, 11_400_000, '2.17.0+', EntityFactory::now(), $set);

        $this->persistAndReload($user, $release, $source, $task, $set, $database);

        $loaded = $this->entityManager->getRepository(ReferenceBlastDb::class)->findOneBy(['name' => 'sp_humans']) ?? self::fail('database not saved');
        $loadedRelease = $loaded->getRelease();

        self::assertSame(ReleaseStatus::Ready, $loadedRelease->getStatus());
        self::assertSame(['enzyme' => '02-Sep-2026'], $loadedRelease->getSourceVersions());
        self::assertSame(['ref_protein' => 3], $loadedRelease->getCounts());
        self::assertSame(['Homo sapiens', 'homo sapiens'], array_map(static fn (OrganismSetMember $member): string => $member->getValue(), $loaded->getOrganismSet()?->getMembers() ?? []));
        self::assertSame('user@example.org', $loaded->getOrganismSet()?->getCreatedBy()?->getEmail());

        $loadedSource = $this->entityManager->getRepository(ReferenceSourceFile::class)->findOneBy(['release' => $loadedRelease]);
        self::assertSame(43_600_000_000, $loadedSource?->getBytes());
        self::assertSame(SourceKind::TrEmbl, $loadedSource->getKind());

        $loadedTask = $this->entityManager->getRepository(ReferenceTask::class)->findOneBy(['release' => $loadedRelease]);
        self::assertSame(TaskStatus::Done, $loadedTask?->getStatus());
        self::assertSame(48_000, $loadedTask->getRowsLoaded());
    }
}
