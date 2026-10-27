<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Iterator;

/**
 * Letture di lunghezza esatta da una sequenza di blocchi di dimensione qualsiasi (i blocchi decompressi di
 * {@see GzipReader}), senza tenere in memoria più di un blocco oltre a quanto richiesto.
 */
final class ByteStream
{
    private string $buffer = '';

    private int $offset = 0;

    /**
     * @param Iterator<mixed, string> $chunks
     */
    public function __construct(
        private readonly Iterator $chunks,
    ) {
    }

    /**
     * @param positive-int $length
     *
     * @return string `$length` byte, meno solo alla fine dei dati ('' se sono finiti)
     */
    public function read(int $length): string
    {
        while (\strlen($this->buffer) - $this->offset < $length && $this->chunks->valid()) {
            $this->buffer = substr($this->buffer, $this->offset) . $this->chunks->current();
            $this->offset = 0;
            $this->chunks->next();
        }

        $data = substr($this->buffer, $this->offset, $length);
        $this->offset += \strlen($data);

        return $data;
    }
}
