<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Port\ArchiveExtractor;
use Override;

/**
 * Decompressione gzip ed estrazione tar in streaming, con memoria costante anche su archivi da GB (PharData
 * carica l'indice dell'archivio e non verifica un gzip troncato). Ogni file nasce con un nome temporaneo
 * ({@see PendingFile}); un'estrazione interrotta lascia solo file completi, che la successiva sovrascrive.
 *
 * Del tar si accettano solo file regolari e directory: link, dispositivi e FIFO non hanno motivo di stare in una
 * sorgente di riferimento e sono un errore, come i percorsi assoluti o con `..`.
 */
final readonly class StreamingArchiveExtractor implements ArchiveExtractor
{
    /** dimensione massima delle voci di metadati (nomi lunghi GNU, header pax) */
    private const int MAX_METADATA = 1_048_576;

    private const int COPY_BLOCK = 1_048_576;

    public function __construct(
        private GzipReader $reader = new GzipReader(),
    ) {
    }

    /**
     * @throws FileNotReadableException
     * @throws FileNotWritableException
     * @throws CorruptArchiveException
     */
    #[Override]
    public function gunzip(string $source, string $destination): int
    {
        $file = $this->create($destination);
        $written = 0;

        try {
            foreach ($this->reader->chunks($source) as $chunk) {
                $file->write($chunk);
                $written += \strlen($chunk);
            }

            $file->commit();
        } finally {
            $file->discard();
        }

        return $written;
    }

    /**
     * @throws FileNotReadableException
     * @throws FileNotWritableException
     * @throws CorruptArchiveException
     *
     * @return list<string>
     */
    #[Override]
    public function untar(string $archive, string $directory): array
    {
        $stream = new ByteStream($this->reader->chunks($archive));
        $extracted = [];
        $longName = null;
        $pax = [];

        while (($header = $this->header($stream, $archive)) instanceof TarHeader) {
            $name = $longName ?? $pax['path'] ?? $header->name;
            $size = isset($pax['size']) ? (int) $pax['size'] : $header->size;
            $longName = null;
            $pax = [];

            switch ($header->type) {
                case 'L':
                    $longName = rtrim($this->metadata($stream, $archive, $size), "\0");

                    break;

                case 'x':
                    $pax = $this->pax($this->metadata($stream, $archive, $size), $archive);

                    break;

                case 'g':
                    $this->metadata($stream, $archive, $size);

                    break;

                case '5':
                    $path = rtrim($directory . '/' . $this->relativePath($name, $archive, directory: true), '/');

                    if (!is_dir($path) && !@mkdir($path, 0o775, true) && !is_dir($path)) {
                        throw FileNotWritableException::at($path);
                    }

                    break;

                case '0':
                case '7':
                    $relative = $this->relativePath($name, $archive);
                    $this->extractFile($stream, $archive, $directory . '/' . $relative, $size);
                    $extracted[] = $relative;

                    break;

                default:
                    throw CorruptArchiveException::corrupt($archive, \sprintf('unsupported tar entry type "%s" for "%s"', $header->type, $name));
            }
        }

        return $extracted;
    }

    /**
     * @throws CorruptArchiveException se i dati finiscono prima del blocco di zeri che chiude l'archivio
     */
    private function header(ByteStream $stream, string $archive): ?TarHeader
    {
        $block = $stream->read(TarHeader::BLOCK);

        if (\strlen($block) < TarHeader::BLOCK) {
            throw CorruptArchiveException::truncated($archive);
        }

        return TarHeader::parse($block, $archive);
    }

    /**
     * @throws FileNotWritableException
     * @throws CorruptArchiveException
     */
    private function extractFile(ByteStream $stream, string $archive, string $path, int $size): void
    {
        $file = $this->create($path);

        try {
            for ($remaining = $size; $remaining > 0; $remaining -= \strlen($data)) {
                $data = $stream->read(min($remaining, self::COPY_BLOCK));

                if ($data === '') {
                    throw CorruptArchiveException::truncated($archive);
                }

                $file->write($data);
            }

            $this->skipPadding($stream, $archive, $size);
            $file->commit();
        } finally {
            $file->discard();
        }
    }

    /**
     * @throws CorruptArchiveException
     */
    private function metadata(ByteStream $stream, string $archive, int $size): string
    {
        if ($size > self::MAX_METADATA) {
            throw CorruptArchiveException::corrupt($archive, \sprintf('tar metadata entry of %d bytes', $size));
        }

        $data = $size > 0 ? $stream->read($size) : '';

        if (\strlen($data) < $size) {
            throw CorruptArchiveException::truncated($archive);
        }

        $this->skipPadding($stream, $archive, $size);

        return $data;
    }

    /**
     * @throws CorruptArchiveException
     */
    private function skipPadding(ByteStream $stream, string $archive, int $size): void
    {
        $padding = (TarHeader::BLOCK - $size % TarHeader::BLOCK) % TarHeader::BLOCK;

        if ($padding > 0 && \strlen($stream->read($padding)) < $padding) {
            throw CorruptArchiveException::truncated($archive);
        }
    }

    /**
     * Record pax `<lunghezza> <chiave>=<valore>\n`: servono solo `path` e `size`.
     *
     * @throws CorruptArchiveException
     *
     * @return array<string, string>
     */
    private function pax(string $data, string $archive): array
    {
        $records = [];

        for ($offset = 0; $offset < \strlen($data); $offset += $length) {
            if (preg_match('/\G(\d+) ([^=]+)=/', $data, $match, 0, $offset) !== 1) {
                throw CorruptArchiveException::corrupt($archive, 'invalid pax header');
            }

            $length = (int) $match[1];

            if ($length <= \strlen($match[0]) || $offset + $length > \strlen($data)) {
                throw CorruptArchiveException::corrupt($archive, 'invalid pax header');
            }

            $valueStart = $offset + \strlen($match[0]);
            $records[$match[2]] = substr($data, $valueStart, $offset + $length - 1 - $valueStart);
        }

        return $records;
    }

    /**
     * @param bool $directory una directory può essere la radice dell'archivio (`./`, risultato '')
     *
     * @throws CorruptArchiveException per percorsi assoluti, vuoti o con `..`
     */
    private function relativePath(string $name, string $archive, bool $directory = false): string
    {
        $segments = array_filter(explode('/', rtrim($name, '/')), static fn (string $segment): bool => $segment !== '.');

        if (($segments === [] && !$directory) || \in_array('', $segments, true) || \in_array('..', $segments, true)) {
            throw CorruptArchiveException::corrupt($archive, \sprintf('unsafe path "%s" in the tar archive', $name));
        }

        return implode('/', $segments);
    }

    /**
     * @throws FileNotWritableException
     */
    private function create(string $path): PendingFile
    {
        $directory = \dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw FileNotWritableException::at($path);
        }

        return PendingFile::create($path);
    }
}
