<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\CodingPotential\PredictCodingPotential;
use App\Domain\CodingPotential\Cpc2OutputFormatter;
use App\Domain\Shared\AnnosynfException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Potenziale codificante di un FASTA con il port nativo di CPC2: stampa lo stesso output di
 * `CPC2.py -i <fasta> -o <out>` (il file lncRNAPredOut di Annocript).
 */
#[AsCommand(
    name: 'annosynf:cpc2:predict',
    description: 'Predicts the coding potential of every sequence of a FASTA file (plain or gzip) and prints the output of CPC2',
)]
final readonly class Cpc2PredictCommand
{
    public function __construct(
        private PredictCodingPotential $predictCodingPotential,
        private Cpc2OutputFormatter $formatter,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument('Path of the FASTA file of nucleotide sequences, plain or compressed with gzip')]
        string $file,
    ): int {
        try {
            $output->writeln(Cpc2OutputFormatter::HEADER, OutputInterface::OUTPUT_RAW);

            foreach ($this->predictCodingPotential->predict($file) as $id => $prediction) {
                $output->writeln($this->formatter->line($id, $prediction), OutputInterface::OUTPUT_RAW);
            }
        } catch (AnnosynfException $annosynfException) {
            $io->getErrorStyle()->error($annosynfException->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
