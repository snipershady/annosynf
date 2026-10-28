<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Release;

use App\Application\Port\TextLines;
use App\Application\Release\ImportOutcome;
use App\Application\Release\ImportReferenceData;
use App\Application\Release\UnexpectedReleaseDataException;
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
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\OboParser;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\SpeclistParser;
use App\Domain\Reference\UniProtDocumentHeader;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniProtHeaderParser;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UniRefHeaderParser;
use App\Domain\Reference\UnirefVariant;
use App\Tests\Support\RecordingReferenceDataWriter;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportReferenceData::class)]
#[CoversClass(ImportOutcome::class)]
#[CoversClass(UnexpectedReleaseDataException::class)]
#[UsesClass(ImportTask::class)]
#[UsesClass(MappingResult::class)]
#[UsesClass(ProteinRecord::class)]
#[UsesClass(CddDomain::class)]
#[UsesClass(CddIdParser::class)]
#[UsesClass(EnzymeDatParser::class)]
#[UsesClass(EnzymeEntry::class)]
#[UsesClass(GoNamespace::class)]
#[UsesClass(GoTerm::class)]
#[UsesClass(HeaderTags::class)]
#[UsesClass(IdMappingEntry::class)]
#[UsesClass(IdMappingParser::class)]
#[UsesClass(OboParser::class)]
#[UsesClass(Pathway::class)]
#[UsesClass(PathwayTxtParser::class)]
#[UsesClass(Pfam2GoMapping::class)]
#[UsesClass(Pfam2GoParser::class)]
#[UsesClass(SpeciesCode::class)]
#[UsesClass(SpeclistParser::class)]
#[UsesClass(UniProtDocumentHeader::class)]
#[UsesClass(UniProtHeader::class)]
#[UsesClass(UniProtHeaderParser::class)]
#[UsesClass(UniRefHeader::class)]
#[UsesClass(UniRefHeaderParser::class)]
final class ImportReferenceDataTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../Fixtures/reference/';

    private RecordingReferenceDataWriter $writer;

    private ImportReferenceData $import;

    #[Override]
    protected function setUp(): void
    {
        $this->writer = new RecordingReferenceDataWriter();
        $this->import = new ImportReferenceData(
            $this->lines(),
            $this->writer,
            new OboParser(),
            new EnzymeDatParser(),
            new PathwayTxtParser(),
            new CddIdParser(),
            new Pfam2GoParser(),
            new SpeclistParser(),
            new UniProtHeaderParser(),
            new UniRefHeaderParser(),
            new IdMappingParser(),
        );
    }

    public function testEachTaskReadsItsFileAndRecordsTheSourceVersion(): void
    {
        $go = $this->importFixture(ImportTask::GoTerms, 'go/go-basic.obo');
        $enzymes = $this->importFixture(ImportTask::Enzymes, 'enzyme/enzyme.dat');
        $pathways = $this->importFixture(ImportTask::Pathways, 'pathway/pathway.txt');
        $domains = $this->importFixture(ImportTask::Domains, 'cdd/cddid_all.tbl');
        $catalogue = $this->importFixture(ImportTask::OrganismCatalog, 'speclist/speclist.txt');
        $proteinEnzymes = $this->importFixture(ImportTask::ProteinEnzymes, 'enzyme/enzyme.dat');
        $proteinPathways = $this->importFixture(ImportTask::ProteinPathways, 'pathway/pathway.txt');
        $domainGo = $this->importFixture(ImportTask::DomainGo, 'cdd/pfam2go');

        self::assertSame(['8 rows loaded', ['go' => 'releases/2026-07-26']], [$go->summary(), $go->sourceVersions]);
        self::assertSame(['12 rows loaded', ['enzyme' => '02-Sep-2026']], [$enzymes->summary(), $enzymes->sourceVersions]);
        self::assertSame('0 associations loaded, 1 skipped (protein, domain or term not in the release)', new ImportOutcome(0, 1)->summary());
        self::assertSame($enzymes->rows, $proteinEnzymes->rows, 'the same entries are mapped');
        self::assertSame($pathways->rows, $proteinPathways->rows);
        self::assertSame(0, $proteinEnzymes->unmatched);
        self::assertSame(1, $domainGo->unmatched);
        self::assertArrayHasKey('pfam2go', $domainGo->sourceVersions);
        self::assertArrayHasKey('pathway', $pathways->sourceVersions);
        self::assertArrayHasKey('speclist', $catalogue->sourceVersions);
        self::assertSame([], $domains->sourceVersions);
        self::assertSame([7], array_values(array_unique(array_column($this->writer->calls, 1))));
    }

    public function testProteinsComeFromTheHeadersOfTheirSource(): void
    {
        self::assertSame(13, $this->import->run(ImportTask::SwissProtProteins, 7, 'sp')->rows);
        self::assertSame(ProteinSource::SwissProt, $this->writer->calls[0][3]);
        self::assertSame(5, $this->import->run(ImportTask::TrEmblProteins, 7, 'tr')->rows);
        self::assertSame(7, $this->import->run(ImportTask::UniRefProteins, 7, 'uniref90', UnirefVariant::UniRef90)->rows);

        $cluster = $this->writer->items('writeProteins')[0];
        self::assertInstanceOf(ProteinRecord::class, $cluster);
        self::assertSame(ProteinSource::UniRef, $cluster->source);
        self::assertNull($cluster->entryId);
    }

    public function testProteinGoUsesTheReleaseVariant(): void
    {
        $outcome = $this->import->run(ImportTask::ProteinGo, 7, self::FIXTURES . 'idmapping/idmapping_selected.tab', UnirefVariant::UniRef50);

        self::assertSame(12, $outcome->rows);
        self::assertSame(UnirefVariant::UniRef50, $this->writer->calls[0][3]);
    }

    public function testAFileOfTheWrongSourceIsRejected(): void
    {
        foreach ([
            [ImportTask::SwissProtProteins, self::FIXTURES . 'headers/uniprotkb.headers', null, 'is not a sp entry'],
            [ImportTask::UniRefProteins, self::FIXTURES . 'headers/uniref.headers', UnirefVariant::UniRef90, 'does not belong to UniRef90'],
            [ImportTask::UniRefProteins, 'uniref90', null, 'only be imported into a uniref release'],
        ] as [$task, $path, $variant, $message]) {
            try {
                $this->import->run($task, 7, $path, $variant);
                self::fail($message);
            } catch (UnexpectedReleaseDataException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    private function importFixture(ImportTask $task, string $file): ImportOutcome
    {
        return $this->import->run($task, 7, self::FIXTURES . $file);
    }

    /**
     * File di fixture; `sp`, `tr` e `uniref90` sono gli header di una sola sorgente.
     */
    private function lines(): TextLines
    {
        return new readonly class(self::FIXTURES) implements TextLines {
            public function __construct(private string $fixtures)
            {
            }

            #[Override]
            public function lines(string $path): iterable
            {
                $prefix = match ($path) {
                    'sp' => '>sp|',
                    'tr' => '>tr|',
                    'uniref90' => '>UniRef90_',
                    default => null,
                };

                $file = $prefix === null ? $path : $this->fixtures . ($prefix === '>UniRef90_' ? 'headers/uniref.headers' : 'headers/uniprotkb.headers');

                $content = file($file);

                foreach (\is_array($content) ? $content : [] as $line) {
                    if ($prefix === null || str_starts_with($line, $prefix)) {
                        yield $line;
                    }
                }
            }
        };
    }
}
