<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Unico punto da cui partono i processi BLAST+: stesso ambiente, stesso timeout, stessa
 * gestione degli errori per programmi di ricerca, makeblastdb e blastdbcmd.
 */
final readonly class BlastProcessExecutor
{
    /**
     * BLAST_USAGE_REPORT=false disattiva l'invio a NCBI dei dati d'uso (BLAST_PRIVACY nel
     * pacchetto NCBI): oltre alla privacy delle ricerche, evita circa 5 secondi di attesa di
     * rete a ogni invocazione.
     */
    public const array ENVIRONMENT = ['BLAST_USAGE_REPORT' => 'false'];

    public function __construct(
        private ProcessRunnerInterface $processRunner,
        #[Autowire('%env(float:BLAST_TIMEOUT)%')]
        private float $timeout,
    ) {
    }

    /**
     * @param non-empty-list<string> $command
     * @param list<int>              $acceptedExitCodes codici d'uscita considerati riusciti (es. blastdbcmd
     *                                                  esce con 1 se salta voci non trovate)
     * @param ?iterable<string>      $input             blocchi per lo stdin (es. FASTA decompresso al volo per makeblastdb)
     *
     * @throws BlastExecutionException se il processo esce con errore o supera il timeout
     */
    public function execute(string $tool, array $command, string $workingDirectory, array $acceptedExitCodes = [0], ?iterable $input = null): ProcessResult
    {
        $result = $this->processRunner->run($command, $workingDirectory, $this->timeout, self::ENVIRONMENT, $input);

        if ($result->timedOut) {
            throw BlastExecutionException::timedOut($tool, $this->timeout);
        }

        if (!\in_array($result->exitCode, $acceptedExitCodes, true)) {
            throw BlastExecutionException::fromResult($tool, $result);
        }

        return $result;
    }
}
