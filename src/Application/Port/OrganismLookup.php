<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Reference\OrganismSearchHit;
use App\Domain\Reference\ProteinSource;

/**
 * Organismi e proteine di una release, per gli insiemi di organismi (ex `blastedOrganism`, §A.7.10).
 */
interface OrganismLookup
{
    /**
     * Cerca nel catalogo degli organismi (nome ufficiale, nome comune, sinonimo, codice di specie o taxon id).
     *
     * @return list<OrganismSearchHit> in ordine di nome
     */
    public function search(int $releaseId, string $text, int $limit = 50): array;

    /**
     * Proteine della release (di ogni sorgente) il cui organismo corrisponde al valore.
     */
    public function countProteins(int $releaseId, OrganismMatchMode $mode, string $value): int;

    /**
     * Nomi con cui le proteine di una sorgente compaiono nel FASTA (primo token dell'header): `sp|P12345|NAME_HUMAN`
     * per Swiss-Prot e TrEMBL, `UniRef90_P12345` per UniRef.
     *
     * @param non-empty-list<string> $values organismi (nome o taxon id, secondo `$mode`); basta che ne corrisponda uno
     *
     * @return iterable<string>
     */
    public function sequenceIds(int $releaseId, ProteinSource $source, OrganismMatchMode $mode, array $values): iterable;
}
