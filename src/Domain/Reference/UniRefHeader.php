<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Header FASTA di un cluster UniRef: `>UniRef90_P12345 descrizione n=12 Tax=… TaxID=… RepID=…`.
 */
final readonly class UniRefHeader
{
    /**
     * @param string  $clusterId        `UniRef90_P12345` (seqId del legacy)
     * @param ?int    $memberCount      `n`
     * @param ?string $taxonomy         `Tax`, senza `TaxID` (correzione §A.12.2)
     * @param ?int    $taxonId          `TaxID`
     * @param ?string $representativeId `RepID`
     */
    public function __construct(
        public string $clusterId,
        public UnirefVariant $variant,
        public string $description,
        public ?int $memberCount = null,
        public ?string $taxonomy = null,
        public ?int $taxonId = null,
        public ?string $representativeId = null,
    ) {
    }
}
