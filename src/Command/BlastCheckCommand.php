<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Blast\BlastProgram;
use App\Domain\Shared\AnnosynfException;
use App\Infrastructure\Process\BlastBinaryLocator;
use App\Infrastructure\Process\BlastTool;
use App\Infrastructure\Process\BlastVersionReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Verifica l'installazione di BLAST+ indicata da BLAST_BIN_DIR: ogni binario usato dalla pipeline
 * deve esistere, essere eseguibile e rispondere a `-version`. Da lanciare dopo ogni aggiornamento.
 */
#[AsCommand(
    name: 'annosynf:blast:check',
    description: 'Checks that every NCBI BLAST+ binary used by the pipeline exists in BLAST_BIN_DIR and reports its version',
)]
final readonly class BlastCheckCommand
{
    public function __construct(
        private BlastBinaryLocator $locator,
        private BlastVersionReader $versions,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(\sprintf('BLAST_BIN_DIR: <info>%s</info>', $this->locator->directory()));

        $rows = [];
        $failed = false;

        foreach ([...BlastProgram::cases(), ...BlastTool::cases()] as $binary) {
            try {
                $rows[] = [$binary->value, $this->versions->read($binary) ?? 'unknown', 'ok'];
            } catch (AnnosynfException $exception) {
                $rows[] = [$binary->value, '–', $exception->getMessage()];
                $failed = true;
            }
        }

        $io->table(['Binary', 'Version', 'Status'], $rows);

        if ($failed) {
            $io->error('The BLAST+ installation is incomplete: fix BLAST_BIN_DIR (in .env.local) or install the official NCBI package.');

            return Command::FAILURE;
        }

        $io->success('Every BLAST+ binary is available.');

        return Command::SUCCESS;
    }
}
