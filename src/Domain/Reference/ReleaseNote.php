<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use DateTimeImmutable;

/**
 * Contenuto di `reldate.txt` (UniProtKB) o di `uniref*.release_note` (UniRef).
 */
final readonly class ReleaseNote
{
    /**
     * @param string             $version       `YYYY_MM`
     * @param ?DateTimeImmutable $releasedOn    data di uscita (mezzanotte UTC), se il file la riporta
     * @param ?UnirefVariant     $unirefVariant solo UniRef, se il file la riporta
     * @param ?int               $clusterCount  solo UniRef, se il file lo riporta
     */
    public function __construct(
        public ReleaseType $type,
        public string $version,
        public ?DateTimeImmutable $releasedOn = null,
        public ?UnirefVariant $unirefVariant = null,
        public ?int $clusterCount = null,
    ) {
    }
}
