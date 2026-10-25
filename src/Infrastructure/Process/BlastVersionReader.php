<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Domain\Blast\BlastProgram;

/**
 * Versione di un binario BLAST+ (`<binario> -version` → "blastx: 2.17.0+"), da salvare con i
 * risultati: dipendono dalla versione e BLAST_BIN_DIR può cambiare tra un'analisi e l'altra.
 */
final readonly class BlastVersionReader
{
    public function __construct(
        private BlastBinaryLocator $locator,
        private BlastProcessExecutor $executor,
    ) {
    }

    /**
     * @throws BlastBinaryNotFoundException
     * @throws BlastExecutionException
     */
    public function read(BlastProgram|BlastTool $binary): ?string
    {
        $path = $this->locator->path($binary);
        $result = $this->executor->execute($binary->value, [$path, '-version'], \dirname($path));

        return preg_match('/^' . preg_quote($binary->value, '/') . ':\s*(\S+)/m', $result->output, $match) === 1 ? $match[1] : null;
    }
}
