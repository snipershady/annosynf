<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use Generator;
use InvalidArgumentException;

/**
 * Divide le sequenze in gruppi di basi simili, per eseguire un programma in parallelo (create_slices di
 * LIB/fasta_management.pm, usata da Annocript per rpstblastn). Regola del legacy: soglia = int(basi totali / n)
 * − 1; prima di scrivere una sequenza, se le basi già scritte nella slice superano la soglia se ne apre una nuova.
 * Le slice possono quindi essere più o meno di n.
 *
 * Correzione rispetto al legacy: una slice si chiude solo se contiene almeno una sequenza. Con meno basi che
 * slice la soglia è negativa e create_slices chiudeva subito la prima, lasciando slice_1.fasta vuoto.
 */
final class FastaSlicer
{
    /**
     * @param iterable<FastaRecord> $records    in ordine di file
     * @param int                   $totalBases somma delle lunghezze delle sequenze (un primo passaggio sul file)
     * @param int                   $count      numero di slice richiesto
     *
     * @throws InvalidArgumentException se $count è minore di 1
     *
     * @return Generator<int, FastaRecord> chiave = numero della slice, da 1 (si ripete per le sequenze della stessa slice)
     */
    public function slices(iterable $records, int $totalBases, int $count): Generator
    {
        if ($count < 1) {
            throw new InvalidArgumentException(\sprintf('At least one slice is needed, %d given.', $count));
        }

        $threshold = intdiv($totalBases, $count) - 1;
        $slice = 1;
        $written = 0;
        $empty = true;

        foreach ($records as $record) {
            if (!$empty && $written > $threshold) {
                ++$slice;
                $written = 0;
            }

            yield $slice => $record;

            $written += $record->length();
            $empty = false;
        }
    }
}
