<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Reference\SourceFile;
use App\Domain\Shared\AnnosynfException;

/**
 * Scarica una sorgente di riferimento (FASTA UniProt, idmapping, OBO, …) su disco, così com'è sul server: la
 * decompressione è un passo successivo.
 */
interface SourceDownloader
{
    /**
     * Un file già presente in `$destination` non viene riscaricato; un download interrotto riprende da dove si era
     * fermato.
     *
     * @throws AnnosynfException se nessun server fornisce il file completo
     */
    public function download(string $url, string $destination): SourceFile;
}
