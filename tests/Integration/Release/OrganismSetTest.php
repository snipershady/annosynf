<?php

declare(strict_types=1);

namespace App\Tests\Integration\Release;

use App\Application\Port\OrganismLookup;
use App\Application\Release\ImportOutcome;
use App\Application\Release\ImportReferenceData;
use App\Domain\Blast\BlastDbInfo;
use App\Domain\Blast\BlastDbInfoParser;
use App\Domain\Reference\BlastDbKind;
use App\Domain\Reference\CddDomain;
use App\Domain\Reference\CddIdParser;
use App\Domain\Reference\EnzymeDatParser;
use App\Domain\Reference\EnzymeEntry;
use App\Domain\Reference\GoNamespace;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\HeaderTags;
use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\IdMappingParser;
use App\Domain\Reference\ImportTask;
use App\Domain\Reference\IndexTask;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\OboParser;
use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Reference\OrganismSearchHit;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\ReleaseNote;
use App\Domain\Reference\ReleaseNoteParser;
use App\Domain\Reference\ReleasePlan;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\SpeclistParser;
use App\Domain\Reference\UniProtDocumentHeader;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniProtHeaderParser;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UniRefHeaderParser;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaSubset;
use App\Entity\OrganismSet;
use App\Entity\OrganismSetMember;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Infrastructure\Persistence\MysqlLoadDataBulkLoader;
use App\Infrastructure\Persistence\MysqlOrganismLookup;
use App\Infrastructure\Persistence\MysqlReferenceDataWriter;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastPlusDbBuilder;
use App\Infrastructure\Process\BlastProcessExecutor;
use App\Infrastructure\Process\BlastVersionReader;
use App\Infrastructure\Process\ProcessResult;
use App\Infrastructure\Process\SymfonyProcessRunner;
use App\Infrastructure\Storage\ByteStream;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\PendingFile;
use App\Infrastructure\Storage\StreamingArchiveExtractor;
use App\Infrastructure\Storage\TarHeader;
use App\Infrastructure\Storage\TextFileReader;
use App\Message\BuildOrganismSetDatabases;
use App\Message\RunReleaseTask;
use App\MessageHandler\BuildOrganismSetDatabasesHandler;
use App\MessageHandler\RunReleaseTaskHandler;
use App\Release\BlastDatabaseRegistry;
use App\Release\OrganismSetCreator;
use App\Release\OrganismSetDatabaseBuilder;
use App\Release\OrganismSetNotCreatedException;
use App\Release\ReleaseCreator;
use App\Release\ReleaseLock;
use App\Release\ReleaseStorage;
use App\Release\ReleaseTaskRunner;
use App\Repository\ReferenceReleaseRepository;
use App\Tests\Support\ReleaseTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(MysqlOrganismLookup::class)]
#[CoversClass(OrganismSearchHit::class)]
#[CoversClass(OrganismSetCreator::class)]
#[CoversClass(OrganismSetDatabaseBuilder::class)]
#[CoversClass(OrganismSetNotCreatedException::class)]
#[CoversClass(BlastDatabaseRegistry::class)]
#[CoversClass(BuildOrganismSetDatabases::class)]
#[CoversClass(BuildOrganismSetDatabasesHandler::class)]
#[UsesClass(ImportOutcome::class)]
#[UsesClass(ImportReferenceData::class)]
#[UsesClass(BlastDbInfo::class)]
#[UsesClass(BlastDbInfoParser::class)]
#[UsesClass(BlastDbKind::class)]
#[UsesClass(CddDomain::class)]
#[UsesClass(CddIdParser::class)]
#[UsesClass(EnzymeDatParser::class)]
#[UsesClass(EnzymeEntry::class)]
#[UsesClass(GoNamespace::class)]
#[UsesClass(GoTerm::class)]
#[UsesClass(HeaderTags::class)]
#[UsesClass(IdMappingEntry::class)]
#[UsesClass(IdMappingParser::class)]
#[UsesClass(ImportTask::class)]
#[UsesClass(IndexTask::class)]
#[UsesClass(MappingResult::class)]
#[UsesClass(OboParser::class)]
#[UsesClass(Pathway::class)]
#[UsesClass(PathwayTxtParser::class)]
#[UsesClass(Pfam2GoMapping::class)]
#[UsesClass(Pfam2GoParser::class)]
#[UsesClass(ProteinRecord::class)]
#[UsesClass(ReleaseNote::class)]
#[UsesClass(ReleaseNoteParser::class)]
#[UsesClass(ReleasePlan::class)]
#[UsesClass(SourceCatalogue::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SpeciesCode::class)]
#[UsesClass(SpeclistParser::class)]
#[UsesClass(UniProtDocumentHeader::class)]
#[UsesClass(UniProtHeader::class)]
#[UsesClass(UniProtHeaderParser::class)]
#[UsesClass(UniRefHeader::class)]
#[UsesClass(UniRefHeaderParser::class)]
#[UsesClass(FastaFormatter::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FastaSubset::class)]
#[UsesClass(OrganismSet::class)]
#[UsesClass(OrganismSetMember::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(ReferenceSourceFile::class)]
#[UsesClass(ReferenceTask::class)]
#[UsesClass(MysqlLoadDataBulkLoader::class)]
#[UsesClass(MysqlReferenceDataWriter::class)]
#[UsesClass(BlastBinaryLocator::class)]
#[UsesClass(BlastPlusDbBuilder::class)]
#[UsesClass(BlastProcessExecutor::class)]
#[UsesClass(BlastVersionReader::class)]
#[UsesClass(ProcessResult::class)]
#[UsesClass(SymfonyProcessRunner::class)]
#[UsesClass(ByteStream::class)]
#[UsesClass(GzipReader::class)]
#[UsesClass(PendingFile::class)]
#[UsesClass(StreamingArchiveExtractor::class)]
#[UsesClass(TarHeader::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(RunReleaseTaskHandler::class)]
#[UsesClass(RunReleaseTask::class)]
#[UsesClass(ReleaseTaskRunner::class)]
#[UsesClass(ReferenceReleaseRepository::class)]
#[UsesClass(ReferenceBlastDb::class)]
#[UsesClass(ReleaseCreator::class)]
#[UsesClass(ReleaseStorage::class)]
#[UsesClass(ReleaseLock::class)]
final class OrganismSetTest extends ReleaseTestCase
{
    public function testTheCatalogueIsSearchedByNameCodeAndTaxonId(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);
        $lookup = self::getContainer()->get(OrganismLookup::class);

        self::assertSame(['HUMAN'], array_map(static fn (OrganismSearchHit $hit): string => $hit->code, $lookup->search($id, 'sapiens')));
        self::assertSame(['HUMAN'], array_map(static fn (OrganismSearchHit $hit): string => $hit->code, $lookup->search($id, 'human')));
        self::assertSame('Homo sapiens', $lookup->search($id, '9606')[0]->officialName);
        self::assertSame([], $lookup->search($id, 'no such organism %'));
    }

    public function testProteinsAreCountedWithTheMatchModeOfTheSet(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);
        $lookup = self::getContainer()->get(OrganismLookup::class);

        self::assertSame(4, $lookup->countProteins($id, OrganismMatchMode::ExactName, 'Homo sapiens'));
        self::assertSame(0, $lookup->countProteins($id, OrganismMatchMode::ExactName, 'homo sapiens'), 'the legacy match is case sensitive');
        self::assertSame(4, $lookup->countProteins($id, OrganismMatchMode::CaseInsensitiveName, 'HOMO SAPIENS'));
        self::assertSame(4, $lookup->countProteins($id, OrganismMatchMode::TaxonId, '9606'));
        self::assertSame(
            ['sp|P31946|1433B_HUMAN', 'sp|P99998|EMPTY_HUMAN', 'sp|Q8NF67|A2012_HUMAN'],
            $this->sorted($lookup->sequenceIds($id, ProteinSource::SwissProt, OrganismMatchMode::TaxonId, ['9606'])),
        );
    }

    public function testASetKeepsTheProteinsOfItsOrganismsInRestrictedDatabases(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);

        $set = $this->creator()->create($this->reload($id), ' humans ', OrganismMatchMode::ExactName, ["Homo sapiens\r", '', ' Homo sapiens', 'Sulfolobus acidocaldarius', 'homo sapiens']);

        self::assertSame(['Homo sapiens' => 4, 'Sulfolobus acidocaldarius' => 1, 'homo sapiens' => 0], $this->matchedProteins($set));
        self::assertSame(1, $this->work(), 'one message builds every database of the set');

        $databases = $this->databasesOf($set);
        $number = (int) $set->getId();
        self::assertSame(["sp_set{$number}" => 3, "tr_set{$number}" => 2], $databases);
        self::assertFileDoesNotExist(self::getContainer()->get(ReleaseStorage::class)->databasePath($id, "sp_set{$number}.fasta"), 'the restricted FASTA is only a step to makeblastdb');
        self::assertFileExists(self::getContainer()->get(ReleaseStorage::class)->databasePath($id, "sp_set{$number}.pin"));

        // rieseguito sostituisce i database, non li raddoppia
        $this->bus()->dispatch(new BuildOrganismSetDatabases($number));
        $this->work();
        self::assertSame($databases, $this->databasesOf($set));
    }

    public function testASourceWithoutSequencesOfTheOrganismsGetsNoDatabase(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);

        $set = $this->creator()->create($this->reload($id), 'frog', OrganismMatchMode::TaxonId, ['654924']);
        $this->work();

        self::assertSame(['sp_set' . $set->getId() => 2], $this->databasesOf($set));
    }

    public function testASetWithoutOrganismsBuildsNothing(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);
        $set = new OrganismSet($this->reload($id), 'empty', OrganismMatchMode::ExactName, new DateTimeImmutable('2026-10-28 12:00:00'));
        $this->entityManager->persist($set);
        $this->entityManager->flush();

        self::assertSame([], self::getContainer()->get(OrganismSetDatabaseBuilder::class)->build($set));
    }

    public function testAUniRefReleaseRestrictsItsClusters(): void
    {
        $id = $this->importedRelease(ReleaseType::UniRef, UnirefVariant::UniRef90);

        $set = $this->creator()->create($this->reload($id), 'frog', OrganismMatchMode::TaxonId, ['654924']);
        $this->work();

        self::assertSame(['sp_set' . $set->getId() => 2, 'uniref_set' . $set->getId() => 1], $this->databasesOf($set));
    }

    public function testASetNeedsAReadyReleaseAValidNameAndMatchingProteins(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);
        $release = $this->reload($id);
        $creator = $this->creator();
        $creator->create($release, 'taken', OrganismMatchMode::TaxonId, ['9606']);

        foreach ([
            ['taken', OrganismMatchMode::TaxonId, ['9606'], 'already has an organism set called "taken"'],
            ['  ', OrganismMatchMode::TaxonId, ['9606'], 'not a valid set name'],
            [str_repeat('x', 129), OrganismMatchMode::TaxonId, ['9606'], 'not a valid set name'],
            ['empty', OrganismMatchMode::ExactName, [' ', ''], 'holds no organism'],
            ['words', OrganismMatchMode::TaxonId, ['9606', 'human'], '"human" is not a taxon id'],
            ['nothing', OrganismMatchMode::ExactName, ['Unicorn'], 'None of the organisms matches'],
        ] as [$name, $mode, $values, $message]) {
            try {
                $creator->create($release, $name, $mode, $values);
                self::fail('The set should be refused: ' . $message);
            } catch (OrganismSetNotCreatedException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }

        $pending = $this->reload((int) $this->creatorOfReleases()->create(ReleaseType::UniRef, UnirefVariant::UniRef90)->getId());

        $this->expectException(OrganismSetNotCreatedException::class);
        $this->expectExceptionMessageIsOrContains('import is complete');

        $creator->create($pending, 'early', OrganismMatchMode::TaxonId, ['9606']);
    }

    public function testNothingHappensForAMissingSetOrWhileAnotherWorkerBuildsIt(): void
    {
        $id = $this->importedRelease(ReleaseType::UniProtKb, null);
        $set = $this->creator()->create($this->reload($id), 'locked', OrganismMatchMode::TaxonId, ['9606']);
        $number = (int) $set->getId();
        $this->work();
        $handler = self::getContainer()->get(BuildOrganismSetDatabasesHandler::class);
        $before = $this->databasesOf($set);

        $handler(new BuildOrganismSetDatabases($number + 1000));
        $lock = self::getContainer()->get(LockFactory::class)->createLock('annosynf-organism-set-' . $number, 60.0);
        self::assertTrue($lock->acquire());

        try {
            $handler(new BuildOrganismSetDatabases($number));
        } finally {
            $lock->release();
        }

        self::assertSame($before, $this->databasesOf($set));
    }

    private function importedRelease(ReleaseType $type, ?UnirefVariant $variant): int
    {
        $id = (int) $this->creatorOfReleases()->create($type, $variant)->getId();
        $this->work();

        return $id;
    }

    /**
     * @param iterable<string> $ids
     *
     * @return list<string>
     */
    private function sorted(iterable $ids): array
    {
        $list = [...$ids];
        sort($list);

        return $list;
    }

    /**
     * @return array<string, int> nome → sequenze
     */
    private function databasesOf(OrganismSet $set): array
    {
        $databases = [];

        foreach ($this->entityManager->getRepository(ReferenceBlastDb::class)->findBy(['organismSet' => $set->getId()]) as $database) {
            $databases[$database->getName()] = $database->getNumSequences();
        }

        ksort($databases);

        return $databases;
    }

    /**
     * @return array<string, ?int>
     */
    private function matchedProteins(OrganismSet $set): array
    {
        $matches = [];

        foreach ($set->getMembers() as $member) {
            $matches[$member->getValue()] = $member->getMatchedProteins();
        }

        return $matches;
    }

    private function creator(): OrganismSetCreator
    {
        return self::getContainer()->get(OrganismSetCreator::class);
    }

    private function creatorOfReleases(): ReleaseCreator
    {
        return self::getContainer()->get(ReleaseCreator::class);
    }

    private function bus(): MessageBusInterface
    {
        return self::getContainer()->get(MessageBusInterface::class);
    }
}
