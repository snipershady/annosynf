<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Port\TextLines;
use Generator;
use Override;

/**
 * Righe di un file di testo, in streaming e senza limiti di lunghezza, sia in chiaro sia compresso con gzip
 * (sostituisce gunzip/IO::Uncompress del legacy). Il formato si riconosce dal contenuto ({@see GzipReader}), che
 * rifiuta anche un gzip troncato.
 */
final readonly class TextFileReader implements TextLines
{
    public function __construct(
        private GzipReader $reader = new GzipReader(),
    ) {
    }

    /**
     * @throws FileNotReadableException se il file non esiste o non si può aprire
     * @throws CorruptArchiveException  se il gzip è troncato o danneggiato
     *
     * @return Generator<int, string> righe senza "\n" finale (un eventuale "\r" resta: lo toglie chi legge)
     */
    #[Override]
    public function lines(string $path): Generator
    {
        $rest = '';

        foreach ($this->reader->chunks($path) as $chunk) {
            $end = strrpos($chunk, "\n");

            if ($end === false) {
                // riga più lunga di un blocco: si accumula senza riconcatenare a ogni giro
                $rest .= $chunk;

                continue;
            }

            $lines = explode("\n", $rest . substr($chunk, 0, $end));
            $rest = substr($chunk, $end + 1);

            // una riga alla volta, perché le chiavi restino progressive come con fgets
            foreach ($lines as $line) {
                yield $line;
            }
        }

        if ($rest !== '') {
            yield $rest;
        }
    }
}
