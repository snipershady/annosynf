<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Generator;
use InflateContext;

/**
 * Contenuto di un file in blocchi, decompresso se è un gzip (riconosciuto dai primi due byte) e così com'è
 * altrimenti.
 *
 * Non usa il wrapper `compress.zlib://`, che legge un gzip troncato o senza il trailer CRC come se fosse completo:
 * un TrEMBL scaricato a metà finirebbe importato a metà senza errori. Con inflate_* ogni membro deve arrivare in
 * fondo (zlib verifica CRC e lunghezza); i membri concatenati (`cat a.gz b.gz`) si leggono uno dopo l'altro, come
 * fa gzip; byte che non sono un nuovo membro dopo la fine di uno sono un errore.
 */
final readonly class GzipReader
{
    private const string MAGIC = "\x1f\x8b";

    /**
     * @param positive-int $blockSize byte compressi letti alla volta: un blocco di zeri si espande fino a ~1000
     *                                volte, quindi 64 KiB tengono la memoria sotto i 64 MiB anche nel caso peggiore
     */
    public function __construct(
        private int $blockSize = 65_536,
    ) {
    }

    /**
     * @throws FileNotReadableException
     * @throws CorruptArchiveException  se il gzip è troncato o danneggiato
     *
     * @return Generator<int, string> blocchi non vuoti del contenuto
     */
    public function chunks(string $path): Generator
    {
        $handle = is_file($path) ? @fopen($path, 'r') : false;

        if ($handle === false) {
            throw FileNotReadableException::at($path);
        }

        try {
            $pending = (string) fread($handle, max(2, $this->blockSize));

            if (!str_starts_with($pending, self::MAGIC)) {
                while ($pending !== '') {
                    yield $pending;
                    $pending = (string) fread($handle, $this->blockSize);
                }

                return;
            }

            $context = null;

            while ($pending !== '') {
                if (!$context instanceof InflateContext) {
                    if (\strlen($pending) < 2) {
                        $pending .= (string) fread($handle, $this->blockSize);
                    }

                    $context = $this->member($path, $pending);
                }

                $before = inflate_get_read_len($context);
                $data = @inflate_add($context, $pending, \ZLIB_SYNC_FLUSH);

                if ($data === false) {
                    throw CorruptArchiveException::corrupt($path, 'invalid deflate data');
                }

                if ($data !== '') {
                    yield $data;
                }

                if (inflate_get_status($context) === \ZLIB_STREAM_END) {
                    // fine del membro: quello che resta del blocco appartiene al successivo
                    $pending = substr($pending, inflate_get_read_len($context) - $before);
                    $context = null;
                } else {
                    $pending = '';
                }

                if ($pending === '') {
                    $pending = (string) fread($handle, $this->blockSize);
                }
            }

            if ($context instanceof InflateContext) {
                throw CorruptArchiveException::truncated($path);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @throws CorruptArchiveException
     */
    private function member(string $path, string $data): InflateContext
    {
        if (!str_starts_with($data, self::MAGIC)) {
            throw CorruptArchiveException::corrupt($path, 'unexpected data after the end of the gzip stream');
        }

        $context = inflate_init(\ZLIB_ENCODING_GZIP);

        // false solo con opzioni non valide
        return $context instanceof InflateContext ? $context : throw CorruptArchiveException::corrupt($path, 'zlib cannot be initialised');
    }
}
