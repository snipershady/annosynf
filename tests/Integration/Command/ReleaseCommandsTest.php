<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Application\Release\ImportOutcome;
use App\Application\Release\ImportReferenceData;
use App\Command\ConsoleTable;
use App\Command\ReleaseCreateCommand;
use App\Command\ReleaseDeleteCommand;
use App\Command\ReleaseListCommand;
use App\Command\ReleaseRetryCommand;
use App\Command\ReleaseShowCommand;
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
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\SpeclistParser;
use App\Domain\Reference\UniProtDocumentHeader;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniProtHeaderParser;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UniRefHeaderParser;
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
use App\Release\ReleaseNotCreatedException;
use App\Release\ReleaseStorage;
use App\Release\ReleaseTaskRunner;
use App\Repository\ReferenceReleaseRepository;
use App\Tests\Support\FakeSourceDownloader;
use App\Tests\Support\ReleaseTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Console\Command\Command;

#[CoversClass(ReleaseCreateCommand::class)]
#[CoversClass(ReleaseListCommand::class)]
#[CoversClass(ReleaseShowCommand::class)]
#[CoversClass(ReleaseRetryCommand::class)]
#[CoversClass(ReleaseDeleteCommand::class)]
#[CoversClass(ConsoleTable::class)]
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
#[UsesClass(ReleaseCreator::class)]
#[UsesClass(ReleaseNotCreatedException::class)]
#[UsesClass(ReleaseStorage::class)]
#[UsesClass(ReleaseTaskRunner::class)]
#[UsesClass(RunReleaseTaskHandler::class)]
#[UsesClass(RunReleaseTask::class)]
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
#[UsesClass(BlastDatabaseRegistry::class)]
final class ReleaseCommandsTest extends ReleaseTestCase
{
    public function testAReleaseIsCreatedListedAndShown(): void
    {
        self::assertStringContainsString('No release yet', self::runCommand('annosynf:release:list', interactive: false)->getDisplay());

        $created = self::runCommand('annosynf:release:create', ['--type' => 'uniref', '--uniref-variant' => 'UniRef90'], interactive: false);
        self::assertSame(Command::SUCCESS, $created->statusCode, $created->getDisplay());
        self::assertMatchesRegularExpression('/Release (\d+) \(uniref 2026_03 UniRef90\) created/', $created->getDisplay());
        $id = (int) $this->onlyRelease()->getId();

        $this->work();

        self::assertMatchesRegularExpression(\sprintf('/%d\s+uniref\s+2026_03\s+UniRef90\s+Cdd\s+ready/', $id), self::runCommand('annosynf:release:list', interactive: false)->getDisplay());

        $shown = self::runCommand('annosynf:release:show', ['id' => $id], interactive: false)->getDisplay();
        self::assertStringContainsString(\sprintf('Release %d: uniref 2026_03 UniRef90, CDD Cdd, ready', $id), $shown);
        self::assertMatchesRegularExpression('/import_proteins:uniref\s+done\s+1\s+.+\s+7\s+7 rows loaded/', $shown);
        self::assertMatchesRegularExpression('/uniref\s+769 B\s+[0-9a-f]{12}…\s+https:\/\/ftp\.uniprot\.org\/.+uniref90\.fasta\.gz/', $shown);
        self::assertMatchesRegularExpression('/enzyme version\s+02-Sep-2026/', $shown);
        self::assertMatchesRegularExpression('/ref_protein\s+18/', $shown);
    }

    public function testInvalidOptionsOrAnExistingReleaseAreRefused(): void
    {
        foreach ([['--type' => 'swissprot'], ['--type' => 'uniref'], ['--type' => 'uniprotkb', '--uniref-variant' => 'UniRef90'], ['--type' => 'uniref', '--uniref-variant' => 'UniRef80']] as $options) {
            self::assertSame(Command::INVALID, self::runCommand('annosynf:release:create', $options, interactive: false)->statusCode, (string) json_encode($options));
        }

        self::runCommand('annosynf:release:create', ['--type' => 'uniprotkb'], interactive: false);
        $again = self::runCommand('annosynf:release:create', ['--type' => 'uniprotkb', '--expect-version' => '2026_03'], interactive: false);

        self::assertSame(Command::FAILURE, $again->statusCode);
        self::assertStringContainsString('already exists', $again->getDisplay());
    }

    public function testOnlyAFailedReleaseCanBeRetried(): void
    {
        self::assertSame(Command::FAILURE, self::runCommand('annosynf:release:retry', ['id' => 999_999], interactive: false)->statusCode);

        FakeSourceDownloader::$failures['enzyme.dat'] = 'Server error';
        self::runCommand('annosynf:release:create', ['--type' => 'uniprotkb'], interactive: false);
        $this->work();
        $id = (int) $this->onlyRelease()->getId();

        $shown = self::runCommand('annosynf:release:show', ['id' => $id], interactive: false)->getDisplay();
        self::assertStringContainsString('download:enzyme: Server error', $shown);

        FakeSourceDownloader::$failures = [];
        $retried = self::runCommand('annosynf:release:retry', ['id' => $id], interactive: false);
        self::assertSame(Command::SUCCESS, $retried->statusCode);
        $this->work();

        self::assertSame(ReleaseStatus::Ready, $this->reload($id)->getStatus());
        self::assertSame(Command::FAILURE, self::runCommand('annosynf:release:retry', ['id' => $id], interactive: false)->statusCode);
    }

    public function testAReleaseIsDeletedWithItsDataAndFiles(): void
    {
        self::assertSame(Command::FAILURE, self::runCommand('annosynf:release:delete', ['id' => 999_999], interactive: false)->statusCode);
        self::assertSame(Command::FAILURE, self::runCommand('annosynf:release:show', ['id' => 999_999], interactive: false)->statusCode);

        self::runCommand('annosynf:release:create', ['--type' => 'uniprotkb'], interactive: false);
        $this->work();
        $id = (int) $this->onlyRelease()->getId();
        $directory = self::getContainer()->get(ReleaseStorage::class)->directory($id);

        $kept = self::runCommand('annosynf:release:delete', ['id' => $id], interactive: false);
        self::assertStringContainsString('Nothing deleted.', $kept->getDisplay(), 'without --force it asks, and the default answer is no');
        self::assertDirectoryExists($directory);

        $deleted = self::runCommand('annosynf:release:delete', ['id' => $id], ['yes']);
        self::assertSame(Command::SUCCESS, $deleted->statusCode, $deleted->getDisplay());
        self::assertDirectoryDoesNotExist($directory);
        self::assertSame([], $this->entityManager->getRepository(ReferenceRelease::class)->findAll());
        self::assertSame([], $this->entityManager->getConnection()->fetchFirstColumn('SELECT TABLE_NAME FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = DATABASE() AND PARTITION_NAME = ?', ['p' . $id]));
    }

    public function testARunningReleaseIsOnlyDeletedByForce(): void
    {
        self::runCommand('annosynf:release:create', ['--type' => 'uniprotkb'], interactive: false);
        $release = $this->onlyRelease();
        self::getContainer()->get('state_machine.reference_release')->apply($release, 'download', ['at' => new DateTimeImmutable()]);
        $this->entityManager->flush();

        $refused = self::runCommand('annosynf:release:delete', ['id' => $release->getId()], interactive: false);
        self::assertSame(Command::FAILURE, $refused->statusCode);
        self::assertStringContainsString('is downloading', $refused->getDisplay());

        self::assertSame(Command::SUCCESS, self::runCommand('annosynf:release:delete', ['id' => $release->getId(), '--force' => true], interactive: false)->statusCode);
    }

    private function onlyRelease(): ReferenceRelease
    {
        $this->entityManager->clear();
        $releases = $this->entityManager->getRepository(ReferenceRelease::class)->findAll();
        self::assertCount(1, $releases);

        return $releases[0];
    }
}
