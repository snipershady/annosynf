<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Port\TextLines;
use Generator;
use Override;

/**
 * Righe di un file di testo, in streaming e senza limiti di lunghezza, sia in chiaro sia compresso con gzip
 * (sostituisce gunzip/IO::Uncompress del legacy). Il wrapper `compress.zlib://` riconosce il formato dal
 * contenuto e legge i file non compressi così come sono.
 */
final class TextFileReader implements TextLines
{
    /**
     * @throws FileNotReadableException se il file non esiste o non si può aprire
     *
     * @return Generator<int, string> righe senza "\n" finale (un eventuale "\r" resta: lo toglie chi legge)
     */
    #[Override]
    public function lines(string $path): Generator
    {
        if (!is_file($path)) {
            throw FileNotReadableException::at($path);
        }

        $handle = @fopen('compress.zlib://' . $path, 'r');

        if ($handle === false) {
            throw FileNotReadableException::at($path);
        }

        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\n");
            }
        } finally {
            fclose($handle);
        }
    }
}
