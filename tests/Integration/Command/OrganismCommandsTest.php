<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Application\Release\ImportOutcome;
use App\Application\Release\ImportReferenceData;
use App\Command\ConsoleTable;
use App\Command\OrganismSearchCommand;
use App\Command\OrganismSetCreateCommand;
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
use App\Domain\Reference\OrganismSearchHit;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use App\Domain\Reference\ProteinRecord;
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
use App\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Console\Command\Command;

#[CoversClass(OrganismSearchCommand::class)]
#[CoversClass(OrganismSetCreateCommand::class)]
#[UsesClass(ImportOutcome::class)]
#[UsesClass(ImportReferenceData::class)]
#[UsesClass(ConsoleTable::class)]
#[UsesClass(ReleaseShowCommand::class)]
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
#[UsesClass(OrganismSearchHit::class)]
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
#[UsesClass(FastaFormatter::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FastaSubset::class)]
#[UsesClass(OrganismSet::class)]
#[UsesClass(OrganismSetMember::class)]
#[UsesClass(ReferenceBlastDb::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(ReferenceSourceFile::class)]
#[UsesClass(ReferenceTask::class)]
#[UsesClass(MysqlLoadDataBulkLoader::class)]
#[UsesClass(MysqlOrganismLookup::class)]
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
#[UsesClass(BuildOrganismSetDatabases::class)]
#[UsesClass(BuildOrganismSetDatabasesHandler::class)]
#[UsesClass(RunReleaseTaskHandler::class)]
#[UsesClass(RunReleaseTask::class)]
#[UsesClass(BlastDatabaseRegistry::class)]
#[UsesClass(OrganismSetCreator::class)]
#[UsesClass(OrganismSetDatabaseBuilder::class)]
#[UsesClass(OrganismSetNotCreatedException::class)]
#[UsesClass(ReleaseStorage::class)]
#[UsesClass(ReleaseTaskRunner::class)]
#[UsesClass(ReferenceReleaseRepository::class)]
#[UsesClass(ReleaseCreator::class)]
#[UsesClass(ReleaseLock::class)]
final class OrganismCommandsTest extends ReleaseTestCase
{
    public function testAnOrganismIsFoundInTheCatalogue(): void
    {
        $id = $this->readyRelease();

        $found = self::runCommand('annosynf:organism:search', ['release' => $id, 'text' => 'sapiens'], interactive: false);
        self::assertSame(Command::SUCCESS, $found->statusCode);
        self::assertMatchesRegularExpression('/HUMAN\s+9606\s+Homo sapiens\s+Human/', $found->getDisplay());

        $missing = self::runCommand('annosynf:organism:search', ['release' => $id, 'text' => 'unicorn'], interactive: false);
        self::assertSame(Command::FAILURE, $missing->statusCode);
        self::assertStringContainsString('No organism found', $missing->getDisplay());
    }

    public function testASetIsCreatedFromAFileAndItsDatabasesAreListedWithTheRelease(): void
    {
        $id = $this->readyRelease();
        $directory = new TemporaryDirectory();

        try {
            file_put_contents($directory->path . '/organisms.txt', "Homo sapiens\nUnicorn\n");

            $created = self::runCommand('annosynf:organism-set:create', ['release' => $id, 'name' => 'humans', '--file' => $directory->path . '/organisms.txt'], interactive: false);
            self::assertSame(Command::SUCCESS, $created->statusCode, $created->getDisplay());
            self::assertMatchesRegularExpression('/Homo sapiens\s+4/', $created->getDisplay());
            self::assertStringContainsString('"Unicorn" matches no protein', $created->getDisplay());

            $this->work();

            self::assertMatchesRegularExpression('/sp_set\d+\s+prot\s+3\s+\d+\s+humans/', self::runCommand('annosynf:release:show', ['id' => $id], interactive: false)->getDisplay());
        } finally {
            $directory->remove();
        }
    }

    public function testInvalidOrUnusableArgumentsAreRefused(): void
    {
        $id = $this->readyRelease();
        $directory = new TemporaryDirectory();

        try {
            file_put_contents($directory->path . '/organisms.txt', "Unicorn\n");
            $file = $directory->path . '/organisms.txt';

            self::assertSame(Command::INVALID, self::runCommand('annosynf:organism-set:create', ['release' => $id, 'name' => 'x'], interactive: false)->statusCode, 'no file');
            self::assertSame(Command::INVALID, self::runCommand('annosynf:organism-set:create', ['release' => $id, 'name' => 'x', '--file' => $file, '--match' => 'fuzzy'], interactive: false)->statusCode);
            self::assertSame(Command::FAILURE, self::runCommand('annosynf:organism-set:create', ['release' => $id + 1, 'name' => 'x', '--file' => $file], interactive: false)->statusCode, 'no such release');

            $nothing = self::runCommand('annosynf:organism-set:create', ['release' => $id, 'name' => 'x', '--file' => $file], interactive: false);
            self::assertSame(Command::FAILURE, $nothing->statusCode);
            self::assertStringContainsString('None of the organisms matches', $nothing->getDisplay());
        } finally {
            $directory->remove();
        }
    }

    private function readyRelease(): int
    {
        $id = (int) self::getContainer()->get(ReleaseCreator::class)->create(ReleaseType::UniProtKb, null)->getId();
        $this->work();

        return $id;
    }
}
