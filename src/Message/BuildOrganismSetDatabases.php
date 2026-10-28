<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Costruisce i database BLAST di Swiss-Prot e TrEMBL (o UniRef) ristretti agli organismi di un insieme.
 */
final readonly class BuildOrganismSetDatabases
{
    public function __construct(
        public int $organismSetId,
    ) {
    }
}
