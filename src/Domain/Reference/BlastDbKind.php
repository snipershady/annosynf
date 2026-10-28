<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Database BLAST costruito (o estratto) per una release.
 */
enum BlastDbKind: string
{
    case SwissProt = 'sp';
    case TrEmbl = 'tr';
    case UniRef = 'uniref';
    case NonCodingRna = 'ncrna';
    case Cdd = 'cdd';

    /**
     * Tipo di database BLAST: proteine per SP/TR/UniRef, nucleotidi per RNAcentral, RPS per i domini.
     */
    public function dbType(): string
    {
        return $this === self::Cdd ? 'rps' : $this->sequenceType();
    }

    /**
     * Tipo di sequenze, come lo chiedono `makeblastdb -dbtype` e `blastdbcmd -dbtype` (un DB RPS contiene proteine).
     */
    public function sequenceType(): string
    {
        return $this === self::NonCodingRna ? 'nucl' : 'prot';
    }
}
