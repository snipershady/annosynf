<?php

declare(strict_types=1);

namespace App\Application\Release;

use App\Application\Port\ReferenceDataWriter;
use App\Application\Port\TextLines;
use App\Domain\Reference\CddIdParser;
use App\Domain\Reference\EnzymeDatParser;
use App\Domain\Reference\IdMappingParser;
use App\Domain\Reference\ImportTask;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\OboParser;
use App\Domain\Reference\PathwayTxtParser;
use App\Domain\Reference\Pfam2GoParser;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SpeclistParser;
use App\Domain\Reference\UniProtHeaderParser;
use App\Domain\Reference\UniRefHeaderParser;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Shared\AnnosynfException;
use Generator;

/**
 * Esegue un task di import di una release (§B.5.1): legge in streaming il file sorgente con il parser di
 * `Domain/Reference` e passa quello che ne esce a {@see ReferenceDataWriter}. Sostituisce createUniprotKBDB,
 * createUnirefDB, createGOTable, createEnzymeTable, createPathwayTableAndMapping, createCDTable, createPfamGO e
 * createMappingTable di db_creator.pm.
 */
final readonly class ImportReferenceData
{
    public function __construct(
        private TextLines $source,
        private ReferenceDataWriter $writer,
        private OboParser $obo,
        private EnzymeDatParser $enzymes,
        private PathwayTxtParser $pathways,
        private CddIdParser $domains,
        private Pfam2GoParser $pfam2go,
        private SpeclistParser $speclist,
        private UniProtHeaderParser $uniprot,
        private UniRefHeaderParser $uniref,
        private IdMappingParser $idMapping,
    ) {
    }

    /**
     * @param string $path file sorgente del task ({@see ImportTask::source()}), compresso con gzip o no
     *
     * @throws AnnosynfException se il file non si legge, non è valido o il database rifiuta i dati
     */
    public function run(ImportTask $task, int $releaseId, string $path, ?UnirefVariant $variant = null): ImportOutcome
    {
        return match ($task) {
            ImportTask::GoTerms => new ImportOutcome(
                $this->writer->writeGoTerms($releaseId, $this->obo->terms($this->source->lines($path))),
                sourceVersions: $this->version('go', $this->obo->dataVersion($this->source->lines($path))),
            ),
            ImportTask::Enzymes => new ImportOutcome(
                $this->writer->writeEnzymes($releaseId, $this->enzymes->entries($this->source->lines($path))),
                sourceVersions: $this->version('enzyme', $this->enzymes->release($this->source->lines($path))),
            ),
            ImportTask::Pathways => new ImportOutcome(
                $this->writer->writePathways($releaseId, $this->pathways->pathways($this->source->lines($path))),
                sourceVersions: $this->version('pathway', $this->pathways->release($this->source->lines($path))),
            ),
            ImportTask::Domains => new ImportOutcome($this->writer->writeDomains($releaseId, $this->domains->domains($this->source->lines($path)))),
            ImportTask::OrganismCatalog => new ImportOutcome(
                $this->writer->writeOrganismCatalog($releaseId, $this->speclist->codes($this->source->lines($path))),
                sourceVersions: $this->version('speclist', $this->speclist->release($this->source->lines($path))),
            ),
            ImportTask::SwissProtProteins => $this->uniProtProteins($releaseId, ProteinSource::SwissProt, $path),
            ImportTask::TrEmblProteins => $this->uniProtProteins($releaseId, ProteinSource::TrEmbl, $path),
            ImportTask::UniRefProteins => new ImportOutcome($this->writer->writeProteins(
                $releaseId,
                ProteinSource::UniRef,
                $this->uniRefProteins($path, $variant ?? throw UnexpectedReleaseDataException::missingVariant()),
            )),
            ImportTask::ProteinGo => $this->mapped($this->writer->mapProteinGo($releaseId, $this->idMapping->entries($this->source->lines($path)), $variant)),
            ImportTask::ProteinEnzymes => $this->mapped($this->writer->mapProteinEnzymes($releaseId, $this->enzymes->entries($this->source->lines($path)))),
            ImportTask::ProteinPathways => $this->mapped($this->writer->mapProteinPathways($releaseId, $this->pathways->pathways($this->source->lines($path)))),
            ImportTask::DomainGo => $this->mapped($this->writer->mapDomainGo($releaseId, $this->pfam2go->mappings($this->source->lines($path))), $this->version('pfam2go', $this->pfam2go->version($this->source->lines($path)))),
        };
    }

    private function uniProtProteins(int $releaseId, ProteinSource $source, string $path): ImportOutcome
    {
        return new ImportOutcome($this->writer->writeProteins($releaseId, $source, $this->uniProtRecords($path, $source)));
    }

    /**
     * @return Generator<int, ProteinRecord>
     */
    private function uniProtRecords(string $path, ProteinSource $source): Generator
    {
        foreach ($this->uniprot->headers($this->source->lines($path)) as $header) {
            yield $header->source === $source
                ? ProteinRecord::fromUniProt($header)
                : throw UnexpectedReleaseDataException::proteinSource($header->entryId, $source);
        }
    }

    /**
     * @return Generator<int, ProteinRecord>
     */
    private function uniRefProteins(string $path, UnirefVariant $variant): Generator
    {
        foreach ($this->uniref->headers($this->source->lines($path)) as $header) {
            yield $header->variant === $variant
                ? ProteinRecord::fromUniRef($header)
                : throw UnexpectedReleaseDataException::unirefVariant($header->clusterId, $variant);
        }
    }

    /**
     * @param array<string, string> $sourceVersions
     */
    private function mapped(MappingResult $result, array $sourceVersions = []): ImportOutcome
    {
        return new ImportOutcome($result->loaded, $result->unmatched, $sourceVersions);
    }

    /**
     * @return array<string, string>
     */
    private function version(string $source, ?string $version): array
    {
        return $version === null ? [] : [$source => $version];
    }
}
