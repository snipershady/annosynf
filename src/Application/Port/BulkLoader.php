<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Shared\AnnosynfException;

/**
 * Caricamento in blocco di righe in una tabella (sostituisce `LOAD DATA` dei TSV in `tables/` e `mysqlimport` del
 * legacy). Le righe arrivano da un iterabile, quindi la memoria non dipende dal loro numero.
 */
interface BulkLoader
{
    /**
     * @param non-empty-string                           $table   nome interno, mai dalla request
     * @param non-empty-list<non-empty-string>           $columns
     * @param iterable<list<string|int|float|bool|null>> $rows    un valore per colonna, nell'ordine di `$columns`
     *
     * @throws AnnosynfException se il database rifiuta o altera anche una sola riga
     *
     * @return int righe caricate
     */
    public function load(string $table, array $columns, iterable $rows): int;
}
