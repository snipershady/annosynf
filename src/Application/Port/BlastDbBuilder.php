<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Blast\BlastDbInfo;

/**
 * Costruzione e verifica dei database BLAST (ex `makeblastdb` + `blastdbcmd -info` di Annocript).
 */
interface BlastDbBuilder
{
    /**
     * Indicizza un FASTA (anche .gz, decompresso in streaming) e verifica il risultato.
     *
     * @param string $fastaPath    FASTA di partenza
     * @param string $databasePath percorso del database da creare, senza estensione
     * @param string $dbType       `prot` o `nucl`
     */
    public function build(string $fastaPath, string $databasePath, string $title, string $dbType): BlastDbInfo;

    /**
     * Legge un database esistente (anche un DB RPS estratto da un tar). Fallisce se manca o è danneggiato.
     *
     * @param string $dbType `prot` o `nucl`; per un DB RPS `prot`
     */
    public function inspect(string $databasePath, string $dbType): BlastDbInfo;
}
