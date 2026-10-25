<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * File in scrittura con un nome temporaneo nella stessa directory; {@see self::commit()} lo rinomina (operazione
 * atomica sullo stesso filesystem). Se non si arriva al commit il temporaneo viene cancellato.
 */
final class PendingFile
{
    /** @var resource|null */
    private $handle;

    /**
     * @param resource $handle
     */
    private function __construct(
        private readonly string $path,
        private readonly string $temporaryPath,
        $handle,
    ) {
        $this->handle = $handle;
    }

    public function __destruct()
    {
        $this->discard();
    }

    /**
     * @throws FileNotWritableException
     */
    public static function create(string $path): self
    {
        $temporaryPath = \sprintf('%s.%s.tmp', $path, bin2hex(random_bytes(6)));
        $handle = @fopen($temporaryPath, 'x');

        if ($handle === false) {
            throw FileNotWritableException::at($path);
        }

        return new self($path, $temporaryPath, $handle);
    }

    /**
     * @throws FileNotWritableException
     */
    public function write(string $data): void
    {
        if (!\is_resource($this->handle) || fwrite($this->handle, $data) !== \strlen($data)) {
            throw FileNotWritableException::at($this->path);
        }
    }

    /**
     * Abbandona il file: chiude e cancella il temporaneo (nessun effetto dopo il commit).
     */
    public function discard(): void
    {
        if (\is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
            @unlink($this->temporaryPath);
        }
    }

    /**
     * @throws FileNotWritableException
     */
    public function commit(): void
    {
        if (!\is_resource($this->handle) || !fclose($this->handle)) {
            throw FileNotWritableException::at($this->path);
        }

        $this->handle = null;

        if (!@rename($this->temporaryPath, $this->path)) {
            @unlink($this->temporaryPath);

            throw FileNotWritableException::at($this->path);
        }
    }
}
