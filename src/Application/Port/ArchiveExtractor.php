<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Shared\AnnosynfException;

/**
 * Decompressione delle sorgenti scaricate, in streaming (sostituisce gunzip, tar e Archive::Extract del legacy).
 * Ogni file compare con il nome definitivo solo quando è completo.
 */
interface ArchiveExtractor
{
    /**
     * Decomprime un file gzip; un file non compresso viene copiato così com'è.
     *
     * @throws AnnosynfException se il gzip è troncato o danneggiato, o se non si può scrivere la destinazione
     *
     * @return int byte scritti
     */
    public function gunzip(string $source, string $destination): int;

    /**
     * Estrae un archivio tar, compresso con gzip o no (es. `Cdd_LE.tar.gz`, il database RPS di CDD).
     *
     * @throws AnnosynfException se l'archivio è troncato o danneggiato, contiene voci che non sono file o directory,
     *                           o percorsi che uscirebbero da `$directory`
     *
     * @return list<string> file estratti, relativi a `$directory`, nell'ordine dell'archivio
     */
    public function untar(string $archive, string $directory): array;
}
