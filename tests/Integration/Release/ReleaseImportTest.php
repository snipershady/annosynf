<?php

declare(strict_types=1);

namespace App\Tests\Integration\Release;

use App\Application\Release\ImportOutcome;
use App\Application\Release\ImportReferenceData;
use App\Application\Release\UnexpectedReleaseDataException;
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
use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ReleaseNote;
use App\Domain\Reference\ReleaseNoteParser;
use App\Domain\Reference\ReleasePlan;
use App\Domain\Reference\ReleaseStatus;
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
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Infrastructure\Persistence\MysqlLoadDataBulkLoader;
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
use App\Message\RunReleaseTask;
use App\MessageHandler\RunReleaseTaskHandler;
use App\Release\BlastDatabaseRegistry;
use App\Release\ReleaseCreator;
use App\Release\ReleaseLock;
use App\Release\ReleaseNotCreatedException;
use App\Release\ReleaseStorage;
use App\Release\ReleaseTaskRunner;
use App\Repository\ReferenceReleaseRepository;
use App\Tests\Support\FakeSourceDownloader;
use App\Tests\Support\ReleaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ReleaseCreator::class)]
#[CoversClass(ReleaseNotCreatedException::class)]
#[CoversClass(ReleaseStorage::class)]
#[CoversClass(ReleaseTaskRunner::class)]
#[CoversClass(RunReleaseTaskHandler::class)]
#[CoversClass(RunReleaseTask::class)]
#[UsesClass(TarHeader::class)]
#[UsesClass(StreamingArchiveExtractor::class)]
#[UsesClass(ByteStream::class)]
#[UsesClass(SymfonyProcessRunner::class)]
#[UsesClass(ProcessResult::class)]
#[UsesClass(BlastVersionReader::class)]
#[UsesClass(BlastProcessExecutor::class)]
#[UsesClass(BlastPlusDbBuilder::class)]
#[UsesClass(BlastBinaryLocator::class)]
#[UsesClass(ReferenceBlastDb::class)]
#[UsesClass(IndexTask::class)]
#[UsesClass(BlastDbKind::class)]
#[UsesClass(BlastDbInfoParser::class)]
#[UsesClass(BlastDbInfo::class)]
#[UsesClass(ImportReferenceData::class)]
#[UsesClass(ImportOutcome::class)]
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
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(ReferenceSourceFile::class)]
#[UsesClass(ReferenceTask::class)]
#[UsesClass(MysqlLoadDataBulkLoader::class)]
#[UsesClass(MysqlReferenceDataWriter::class)]
#[UsesClass(GzipReader::class)]
#[UsesClass(PendingFile::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(ReferenceReleaseRepository::class)]
#[UsesClass(UnexpectedReleaseDataException::class)]
#[UsesClass(BlastDatabaseRegistry::class)]
#[UsesClass(ReleaseLock::class)]
final class ReleaseImportTest extends ReleaseTestCase
{
    public function testAUniRefReleaseIsImportedTaskByTask(): void
    {
        $release = $this->creator()->create(ReleaseType::UniRef, UnirefVariant::UniRef90);
        $id = (int) $release->getId();

        self::assertSame('2026_03', $release->getVersion());
        self::assertSame(ReleaseStatus::Pending, $release->getStatus());
        self::assertSame(28, $this->work(), 'one message per task');

        $release = $this->reload($id);

        self::assertSame(ReleaseStatus::Ready, $release->getStatus());
        self::assertNull($release->getError());
        self::assertNotNull($release->getReadyAt());
        self::assertSame(array_fill_keys(ReleasePlan::tasks(ReleaseType::UniRef), ['done', 1]), $this->tasks($release));
        self::assertSame(['enzyme' => '02-Sep-2026', 'go' => 'releases/2026-07-26', 'pathway' => '2026_03', 'pfam2go' => '2026/07/06', 'speclist' => '2026_03'], $release->getSourceVersions());
        self::assertSame([
            'ref_description' => 17, 'ref_domain' => 13, 'ref_domain_go' => 1, 'ref_enzyme' => 13, 'ref_gene_name' => 7,
            'ref_go_term' => 21, 'ref_organism' => 11, 'ref_organism_catalog' => 25, 'ref_pathway' => 7, 'ref_protein' => 18,
            'ref_protein_enzyme' => 2, 'ref_protein_go' => 4, 'ref_protein_pathway' => 2,
        ], $release->getCounts());
        self::assertSame([['P31946', 'GO:0003674', 1], ['P31946', 'GO:0000001', 2], ['UniRef90_Q6GZX4', 'GO:0003674', 1], ['UniRef90_Q6GZX4', 'GO:0000001', 2]], array_map(
            static fn (array $row): array => [$row[0], $row[1], is_numeric($row[2]) ? (int) $row[2] : $row[2]],
            self::getContainer()->get(Connection::class)->fetchAllNumeric(<<<'SQL'
                SELECT p.accession, g.go_id, x.position FROM ref_protein_go x
                JOIN ref_protein p ON p.release_id = x.release_id AND p.id = x.protein_id
                JOIN ref_go_term g ON g.release_id = x.release_id AND g.id = x.go_term_id
                WHERE x.release_id = ? ORDER BY p.accession, x.position
                SQL, [$id]),
        ));
        self::assertFileExists(self::getContainer()->get(ReleaseStorage::class)->directory($id) . '/uniref90.fasta.gz');

        $databases = [];

        foreach ($this->entityManager->getRepository(ReferenceBlastDb::class)->findBy(['release' => $release]) as $database) {
            $databases[$database->getName()] = [$database->getKind()->value, $database->getDbType(), $database->getNumSequences(), $database->getNumLetters()];
        }

        ksort($databases);
        self::assertSame(['Cdd' => ['cdd', 'rps', 1, 348], 'ncRNA' => ['ncrna', 'nucl', 2, 80], 'uniprot_sprot' => ['sp', 'prot', 11, 297], 'uniref90' => ['uniref', 'prot', 7, 70]], $databases);
        self::assertFileExists(self::getContainer()->get(ReleaseStorage::class)->databasePath($id, 'uniref90.pin'));

        $this->bus()->dispatch(new RunReleaseTask($id));
        self::assertSame(1, $this->work(), 'a late message finds nothing left to do');
        self::assertSame(ReleaseStatus::Ready, $this->reload($id)->getStatus());
    }

    public function testAnIndexTaskRunAgainReplacesItsDatabaseRecord(): void
    {
        $id = (int) $this->creator()->create(ReleaseType::UniProtKb, null)->getId();
        $this->work();
        $connection = self::getContainer()->get(Connection::class);
        // come dopo un worker morto a metà: il database c'è già, ma il task non risulta completato
        $connection->executeStatement("UPDATE reference_task SET status = 'pending' WHERE release_id = ? AND task IN ('build_ncrna', 'finalize')", [$id]);
        $connection->executeStatement("UPDATE reference_release SET status = 'failed', error = 'worker died' WHERE id = ?", [$id]);

        $this->entityManager->clear();

        $this->bus()->dispatch(new RunReleaseTask($id, resume: true));
        $this->work();

        $release = $this->reload($id);
        self::assertSame(ReleaseStatus::Ready, $release->getStatus());
        self::assertCount(1, $this->entityManager->getRepository(ReferenceBlastDb::class)->findBy(['release' => $release, 'name' => 'ncRNA']));
    }

    public function testAFailedTaskStopsTheReleaseUntilItIsResumed(): void
    {
        FakeSourceDownloader::$failures['pfam2go'] = 'Idle timeout reached';
        $id = (int) $this->creator()->create(ReleaseType::UniProtKb, null)->getId();

        self::assertSame(9, $this->work(), 'the downloads before pfam2go and the failing one');

        $release = $this->reload($id);
        $tasks = $this->tasks($release);
        self::assertSame(ReleaseStatus::Failed, $release->getStatus());
        self::assertSame('download:pfam2go: Idle timeout reached', $release->getError());
        self::assertSame(['failed', 1], $tasks['download:pfam2go']);
        self::assertSame(['pending', 0], $tasks['download:idmapping']);

        $this->bus()->dispatch(new RunReleaseTask($id));
        $this->work();
        self::assertSame(['failed', 1], $this->tasks($this->reload($id))['download:pfam2go'], 'only a retry resumes a failed release');

        FakeSourceDownloader::$failures = [];
        $this->bus()->dispatch(new RunReleaseTask($id, resume: true));
        $this->work();

        $release = $this->reload($id);
        self::assertSame(ReleaseStatus::Ready, $release->getStatus());
        self::assertSame(['done', 2], $this->tasks($release)['download:pfam2go']);
        self::assertSame(16, $release->getCounts()['ref_protein'] ?? null, 'the 11 Swiss-Prot and 5 TrEMBL entries');
    }

    public function testAnImportErrorIsRecordedOnTheTask(): void
    {
        FakeSourceDownloader::$aliases['uniprot_trembl.fasta.gz'] = 'uniprot_sprot.fasta.gz';
        $id = (int) $this->creator()->create(ReleaseType::UniProtKb, null)->getId();
        $this->work();

        $release = $this->reload($id);
        self::assertSame(ReleaseStatus::Failed, $release->getStatus());
        self::assertStringStartsWith('import_proteins:tr: Entry "sp|', (string) $release->getError());
    }

    public function testTheSameReleaseCannotBeCreatedTwice(): void
    {
        $this->creator()->create(ReleaseType::UniProtKb, null);

        foreach ([
            'already exists' => static fn (ReleaseCreator $creator): ReferenceRelease => $creator->create(ReleaseType::UniProtKb, null),
            'not 2025_01' => static fn (ReleaseCreator $creator): ReferenceRelease => $creator->create(ReleaseType::UniRef, UnirefVariant::UniRef90, expectedVersion: '2025_01'),
            'describes UniRef90, not UniRef50' => static function (ReleaseCreator $creator): ReferenceRelease {
                FakeSourceDownloader::$aliases['uniref50.release_note'] = 'uniref90.release_note';

                return $creator->create(ReleaseType::UniRef, UnirefVariant::UniRef50);
            },
        ] as $message => $create) {
            try {
                $create($this->creator());
                self::fail($message);
            } catch (ReleaseNotCreatedException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }

        self::assertCount(1, $this->entityManager->getRepository(ReferenceRelease::class)->findAll());
        self::assertSame([], glob(self::getContainer()->get(ReleaseStorage::class)->directory(0) . '/../../tmp/release-*'), 'temporary files are removed');
    }

    public function testAReleaseLockedByAnotherWorkerIsLeftAlone(): void
    {
        $id = (int) $this->creator()->create(ReleaseType::UniProtKb, null)->getId();
        $lock = self::getContainer()->get(LockFactory::class)->createLock('annosynf-release-' . $id);
        self::assertTrue($lock->acquire());

        try {
            self::assertSame(1, $this->work());
            self::assertSame(ReleaseStatus::Pending, $this->reload($id)->getStatus());
        } finally {
            $lock->release();
        }

        $this->bus()->dispatch(new RunReleaseTask(999_999));
        self::assertSame(1, $this->work(), 'a deleted release is ignored');
    }

    private function creator(): ReleaseCreator
    {
        return self::getContainer()->get(ReleaseCreator::class);
    }

    private function bus(): MessageBusInterface
    {
        return self::getContainer()->get(MessageBusInterface::class);
    }
}
