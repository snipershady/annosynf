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
        return match ($this) {
            self::NonCodingRna => 'nucl',
            self::Cdd => 'rps',
            default => 'prot',
        };
    }
}
