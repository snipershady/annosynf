<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Domain\Blast\BlastProgram;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Unico punto che conosce dove stanno i binari NCBI BLAST+ (env BLAST_BIN_DIR). I binari non sono
 * nel repository: aggiornare BLAST+ significa cambiare la directory, non il codice.
 */
final readonly class BlastBinaryLocator
{
    private string $binDir;

    public function __construct(
        #[Autowire(env: 'BLAST_BIN_DIR')]
        string $binDir,
    ) {
        $this->binDir = rtrim($binDir, '/');
    }

    public function directory(): string
    {
        return $this->binDir;
    }

    /**
     * @throws BlastBinaryNotFoundException se il binario non esiste o non è eseguibile
     */
    public function path(BlastProgram|BlastTool $binary): string
    {
        $path = $this->binDir . '/' . $binary->value;

        if (!is_file($path) || !is_executable($path)) {
            throw BlastBinaryNotFoundException::at($path);
        }

        return $path;
    }
}
