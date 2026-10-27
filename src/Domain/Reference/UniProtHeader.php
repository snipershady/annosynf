<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Header FASTA di Swiss-Prot o TrEMBL: `>sp|P31946|1433B_HUMAN descrizione OS=… OX=… GN=… PE=… SV=…`.
 */
final readonly class UniProtHeader
{
    /**
     * @param string $entryId   `sp|P31946|1433B_HUMAN` (trId del legacy)
     * @param string $accession `P31946` (seqId del legacy)
     * @param string $entryName `1433B_HUMAN`
     * @param ?int   $taxonId   `OX`, scartato dal legacy
     */
    public function __construct(
        public ProteinSource $source,
        public string $entryId,
        public string $accession,
        public string $entryName,
        public string $description,
        public ?string $organism = null,
        public ?int $taxonId = null,
        public ?string $geneName = null,
        public ?int $proteinExistence = null,
        public ?int $sequenceVersion = null,
    ) {
    }
}
