<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Proteina di riferimento come si salva in `ref_protein`: una entry Swiss-Prot/TrEMBL o un cluster UniRef.
 */
final readonly class ProteinRecord
{
    /**
     * @param string  $accession        `P31946` oppure `UniRef90_P31946`
     * @param ?string $entryId          `sp|P31946|1433B_HUMAN` (solo UniProtKB)
     * @param ?string $organism         `OS` (UniProtKB) o `Tax` (UniRef)
     * @param ?int    $memberCount      `n` (solo UniRef)
     * @param ?string $representativeId `RepID` (solo UniRef)
     */
    public function __construct(
        public ProteinSource $source,
        public string $accession,
        public string $description,
        public ?string $entryId = null,
        public ?string $organism = null,
        public ?int $taxonId = null,
        public ?string $geneName = null,
        public ?int $proteinExistence = null,
        public ?int $sequenceVersion = null,
        public ?int $memberCount = null,
        public ?string $representativeId = null,
    ) {
    }

    public static function fromUniProt(UniProtHeader $header): self
    {
        return new self(
            $header->source,
            $header->accession,
            $header->description,
            entryId: $header->entryId,
            organism: $header->organism,
            taxonId: $header->taxonId,
            geneName: $header->geneName,
            proteinExistence: $header->proteinExistence,
            sequenceVersion: $header->sequenceVersion,
        );
    }

    public static function fromUniRef(UniRefHeader $header): self
    {
        return new self(
            ProteinSource::UniRef,
            $header->clusterId,
            $header->description,
            organism: $header->taxonomy,
            taxonId: $header->taxonId,
            memberCount: $header->memberCount,
            representativeId: $header->representativeId,
        );
    }
}
