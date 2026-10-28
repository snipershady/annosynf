<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Esito del caricamento di un'associazione (proteina → GO, dominio → GO, …).
 */
final readonly class MappingResult
{
    /**
     * @param int $loaded    associazioni salvate, senza ripetizioni
     * @param int $unmatched associazioni del file scartate perché la proteina, il dominio o il termine non sono nella
     *                       release (il legacy le scartava in silenzio)
     */
    public function __construct(
        public int $loaded,
        public int $unmatched,
    ) {
    }
}
