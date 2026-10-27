<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Livello di identità dei cluster UniRef (`--uniref_db_ver` di Annocript).
 */
enum UnirefVariant: string
{
    case UniRef50 = 'UniRef50';
    case UniRef90 = 'UniRef90';
    case UniRef100 = 'UniRef100';
}
