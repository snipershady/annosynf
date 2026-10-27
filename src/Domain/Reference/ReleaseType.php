<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Tipo di release di riferimento (`--db_type` di Annocript): Swiss-Prot + TrEMBL, oppure Swiss-Prot + UniRef.
 */
enum ReleaseType: string
{
    case UniProtKb = 'uniprotkb';
    case UniRef = 'uniref';
}
