<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use Generator;

/**
 * Legge un FASTA riga per riga e restituisce una sequenza alla volta, a memoria costante rispetto al numero
 * di sequenze (sostituisce Bio::SeqIO). Le righe possono arrivare da qualunque fonte: file, gz, stringhe nei test.
 *
 * Come Bio::SeqIO: righe vuote ignorate, CR/LF tolti, spazi e tab tolti dalla sequenza, id = primo token
 * dell'header. Diversamente da Bio::SeqIO, un `>` dentro una riga di sequenza non viene cancellato: resta
 * nella sequenza perché la validazione lo segnali.
 */
final class FastaReader
{
    /**
     * @param iterable<string> $lines righe del file, con o senza terminatore
     *
     * @throws InvalidFastaException se c'è del testo prima del primo header
     *
     * @return Generator<int, FastaRecord> chiave = posizione della sequenza nel file, da 0
     */
    public function read(iterable $lines): Generator
    {
        $header = null;
        $headerLine = 0;
        $chunks = [];
        $longestLine = 0;
        $lineNumber = 0;

        foreach ($lines as $line) {
            ++$lineNumber;
            $line = rtrim($line, "\r\n");

            if (str_starts_with($line, '>')) {
                if ($header !== null) {
                    yield new FastaRecord($header, implode('', $chunks), $headerLine, $longestLine);
                }

                $header = substr($line, 1);
                $headerLine = $lineNumber;
                $chunks = [];
                $longestLine = 0;

                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if ($header === null) {
                throw InvalidFastaException::textBeforeFirstHeader($lineNumber);
            }

            $longestLine = max($longestLine, \strlen($line));
            $chunks[] = str_replace([' ', "\t", "\r"], '', $line);
        }

        if ($header !== null) {
            yield new FastaRecord($header, implode('', $chunks), $headerLine, $longestLine);
        }
    }
}
