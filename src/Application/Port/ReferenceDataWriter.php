<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Reference\CddDomain;
use App\Domain\Reference\EnzymeEntry;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Shared\AnnosynfException;

/**
 * Dati di riferimento di una release (tabelle `ref_*`, ex database `<db_type>_<YYYY_MM>`). Ogni `write*` e `map*`
 * sostituisce quello che la release aveva già in quella tabella, così un task si può ripetere; le associazioni si
 * risolvono con join sul database (accession, numero EC, GO id), mai con una query per riga.
 *
 * @throws AnnosynfException da ogni metodo, se il database rifiuta i dati
 */
interface ReferenceDataWriter
{
    /**
     * Crea lo spazio della release in ogni tabella (se non c'è già).
     */
    public function prepare(int $releaseId): void;

    /**
     * Cancella tutti i dati della release.
     */
    public function drop(int $releaseId): void;

    /**
     * @return array<string, int> tabella → righe della release
     */
    public function counts(int $releaseId): array;

    /**
     * Ogni termine e, con lo stesso nome, ognuno dei suoi `alt_id`.
     *
     * @param iterable<GoTerm> $terms
     */
    public function writeGoTerms(int $releaseId, iterable $terms): int;

    /**
     * @param iterable<EnzymeEntry> $entries
     */
    public function writeEnzymes(int $releaseId, iterable $entries): int;

    /**
     * @param iterable<Pathway> $pathways numerati nell'ordine: {@see self::mapProteinPathways()} deve ricevere gli
     *                                    stessi, nello stesso ordine
     */
    public function writePathways(int $releaseId, iterable $pathways): int;

    /**
     * @param iterable<CddDomain> $domains
     */
    public function writeDomains(int $releaseId, iterable $domains): int;

    /**
     * @param iterable<SpeciesCode> $codes
     */
    public function writeOrganismCatalog(int $releaseId, iterable $codes): int;

    /**
     * Proteine di una sorgente, con i dizionari di descrizioni, organismi e nomi di gene condivisi tra le sorgenti.
     *
     * @param iterable<ProteinRecord> $proteins tutte della sorgente `$source`
     */
    public function writeProteins(int $releaseId, ProteinSource $source, iterable $proteins): int;

    /**
     * GO delle proteine da idmapping, nell'ordine di prima apparizione. Senza variante si legano alle accession
     * UniProtKB; con una variante anche ai cluster UniRef, che ricevono l'unione dei GO dei loro membri (§A.7.5).
     *
     * @param iterable<IdMappingEntry> $entries
     */
    public function mapProteinGo(int $releaseId, iterable $entries, ?UnirefVariant $variant): MappingResult;

    /**
     * @param iterable<EnzymeEntry> $entries
     */
    public function mapProteinEnzymes(int $releaseId, iterable $entries): MappingResult;

    /**
     * @param iterable<Pathway> $pathways gli stessi di {@see self::writePathways()}, nello stesso ordine
     */
    public function mapProteinPathways(int $releaseId, iterable $pathways): MappingResult;

    /**
     * @param iterable<Pfam2GoMapping> $mappings
     */
    public function mapDomainGo(int $releaseId, iterable $mappings): MappingResult;
}
