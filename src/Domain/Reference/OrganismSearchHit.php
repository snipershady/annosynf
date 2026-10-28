<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Organismo trovato nel catalogo di `speclist.txt` (ex "Search organism" del menu `--utils` di Annocript).
 */
final readonly class OrganismSearchHit
{
    public function __construct(
        public string $code,
        public int $taxonId,
        public string $officialName,
        public ?string $commonName = null,
        public ?string $synonym = null,
    ) {
    }
}
