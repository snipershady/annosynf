<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Task che rendono ricercabili i file di una release (fase `indexing`, CLAUDE.md §B.5.1): un database BLAST per
 * sorgente, ciascuno da un solo file. Vanno dopo l'import perché non dipendono dalle tabelle `ref_*`.
 */
enum IndexTask: string
{
    case ExtractCdd = 'extract_cdd';
    case SwissProtDatabase = 'build_blastdb:sp';
    case TrEmblDatabase = 'build_blastdb:tr';
    case UniRefDatabase = 'build_blastdb:uniref';
    case NonCodingRnaDatabase = 'build_ncrna';

    /**
     * @return non-empty-list<self>
     */
    public static function planFor(ReleaseType $type): array
    {
        return [
            self::ExtractCdd,
            self::SwissProtDatabase,
            $type === ReleaseType::UniRef ? self::UniRefDatabase : self::TrEmblDatabase,
            self::NonCodingRnaDatabase,
        ];
    }

    public function source(): SourceKind
    {
        return match ($this) {
            self::ExtractCdd => SourceKind::CddDatabase,
            self::SwissProtDatabase => SourceKind::SwissProt,
            self::TrEmblDatabase => SourceKind::TrEmbl,
            self::UniRefDatabase => SourceKind::UniRef,
            self::NonCodingRnaDatabase => SourceKind::RnaCentral,
        };
    }

    public function kind(): BlastDbKind
    {
        return match ($this) {
            self::ExtractCdd => BlastDbKind::Cdd,
            self::SwissProtDatabase => BlastDbKind::SwissProt,
            self::TrEmblDatabase => BlastDbKind::TrEmbl,
            self::UniRefDatabase => BlastDbKind::UniRef,
            self::NonCodingRnaDatabase => BlastDbKind::NonCodingRna,
        };
    }

    /**
     * Nome del database nella release: quello dei file di Annocript (`uniprot_sprot`, `ncRNA`, `Cdd`, …).
     */
    public function databaseName(?UnirefVariant $variant): string
    {
        return match ($this) {
            self::ExtractCdd => 'Cdd',
            self::SwissProtDatabase => 'uniprot_sprot',
            self::TrEmblDatabase => 'uniprot_trembl',
            self::UniRefDatabase => strtolower(($variant ?? UnirefVariant::UniRef90)->value),
            self::NonCodingRnaDatabase => 'ncRNA',
        };
    }
}
