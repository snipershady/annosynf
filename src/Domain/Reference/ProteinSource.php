<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Banca dati da cui viene una proteina di riferimento (`ref_protein.source`).
 */
enum ProteinSource: string
{
    case SwissProt = 'sp';
    case TrEmbl = 'tr';
    case UniRef = 'uniref';
}
