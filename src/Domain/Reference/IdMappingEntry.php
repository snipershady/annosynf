<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Riga di `idmapping_selected.tab`: un'accession UniProtKB con i suoi termini GO e i cluster UniRef a cui
 * appartiene.
 */
final readonly class IdMappingEntry
{
    /**
     * @param list<string>          $goIds    nell'ordine del file
     * @param array<string, string> $clusters variante (`UniRef90`) → cluster (`UniRef90_P63038`)
     */
    public function __construct(
        public string $accession,
        public array $goIds = [],
        public array $clusters = [],
    ) {
    }

    public function cluster(UnirefVariant $variant): ?string
    {
        return $this->clusters[$variant->value] ?? null;
    }
}
