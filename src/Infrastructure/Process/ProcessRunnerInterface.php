<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

/**
 * Esecuzione di processi esterni. Il comando è sempre un argv (mai una stringa di shell).
 */
interface ProcessRunnerInterface
{
    /**
     * @param non-empty-list<string> $command     argv: il primo elemento è il path del binario
     * @param float                  $timeout     secondi massimi di esecuzione; allo scadere il processo viene terminato
     * @param array<string, string>  $environment variabili aggiunte all'ambiente ereditato
     * @param ?iterable<string>      $input       blocchi scritti sullo stdin del processo, a mano a mano che vengono prodotti
     */
    public function run(array $command, string $workingDirectory, float $timeout, array $environment = [], ?iterable $input = null): ProcessResult;
}
