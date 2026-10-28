<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Task che caricano i dati di riferimento di una release nelle tabelle `ref_*` (CLAUDE.md §B.5.1), ciascuno da un
 * solo file sorgente. Gli `import_*` riempiono le tabelle dei dati, i `map_*` le associazioni, che richiedono le
 * proteine e i termini già caricati.
 */
enum ImportTask: string
{
    case GoTerms = 'import_go';
    case Enzymes = 'import_enzymes';
    case Pathways = 'import_pathways';
    case Domains = 'import_domains';
    case OrganismCatalog = 'import_organism_catalog';
    case SwissProtProteins = 'import_proteins:sp';
    case TrEmblProteins = 'import_proteins:tr';
    case UniRefProteins = 'import_proteins:uniref';
    case ProteinGo = 'map_protein_go';
    case ProteinEnzymes = 'map_protein_enzyme';
    case ProteinPathways = 'map_protein_pathway';
    case DomainGo = 'map_domain_go';

    /**
     * Task di una release, nell'ordine in cui vanno eseguiti.
     *
     * @return non-empty-list<self>
     */
    public static function planFor(ReleaseType $type): array
    {
        return [
            self::GoTerms, self::Enzymes, self::Pathways, self::Domains, self::OrganismCatalog, self::SwissProtProteins,
            $type === ReleaseType::UniRef ? self::UniRefProteins : self::TrEmblProteins,
            self::ProteinGo, self::ProteinEnzymes, self::ProteinPathways, self::DomainGo,
        ];
    }

    public function source(): SourceKind
    {
        return match ($this) {
            self::GoTerms => SourceKind::GeneOntology,
            self::Enzymes, self::ProteinEnzymes => SourceKind::Enzyme,
            self::Pathways, self::ProteinPathways => SourceKind::Pathway,
            self::Domains => SourceKind::CddTable,
            self::OrganismCatalog => SourceKind::Speclist,
            self::SwissProtProteins => SourceKind::SwissProt,
            self::TrEmblProteins => SourceKind::TrEmbl,
            self::UniRefProteins => SourceKind::UniRef,
            self::ProteinGo => SourceKind::IdMapping,
            self::DomainGo => SourceKind::Pfam2Go,
        };
    }

    /**
     * Stato della release mentre il task gira.
     */
    public function phase(): ReleaseStatus
    {
        return str_starts_with($this->value, 'map_') ? ReleaseStatus::Mapping : ReleaseStatus::Importing;
    }
}
