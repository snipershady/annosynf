<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Reference\UnirefVariant;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'annosynf:release:show',
    description: 'Shows a reference release: status, tasks, source files and row counts',
)]
final readonly class ReleaseShowCommand
{
    public function __construct(
        private ReferenceReleaseRepository $releases,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $id,
    ): int {
        $release = $this->releases->find($id);

        if (!$release instanceof ReferenceRelease) {
            $io->error(\sprintf('Release %d does not exist.', $id));

            return Command::FAILURE;
        }

        $io->writeln(\sprintf('Release <info>%d</info>: %s %s%s, CDD %s, <info>%s</info>', $id, $release->getType()->value, $release->getVersion(), $release->getUnirefVariant() instanceof UnirefVariant ? ' ' . $release->getUnirefVariant()->value : '', $release->getCddName(), $release->getStatus()->value));

        if ($release->getError() !== null) {
            $io->error($release->getError());
        }

        $io->newLine();
        ConsoleTable::render($io, ['Task', 'Status', 'Attempts', 'Started', 'Finished', 'Rows', 'Message'], array_map(
            static fn (ReferenceTask $task): array => [
                $task->getTask(),
                $task->getStatus()->value,
                $task->getAttempts(),
                $task->getStartedAt()?->format('Y-m-d H:i:s') ?? '–',
                $task->getFinishedAt()?->format('Y-m-d H:i:s') ?? '–',
                $task->getRowsLoaded() ?? '–',
                $task->getMessage() ?? '',
            ],
            $this->releases->tasks($release),
        ));
        ConsoleTable::render($io, ['Source', 'Size', 'SHA-256', 'URL'], array_map(
            static fn (ReferenceSourceFile $source): array => [
                $source->getKind()->value,
                $source->getBytes() === null ? '–' : Helper::formatMemory($source->getBytes()),
                $source->getSha256() === null ? '–' : substr($source->getSha256(), 0, 12) . '…',
                $source->getUrl(),
            ],
            $this->releases->sources($release),
        ));

        if (($databases = $this->releases->blastDatabases($release)) !== []) {
            ConsoleTable::render($io, ['BLAST database', 'Kind', 'Sequences', 'Letters', 'Organism set', 'BLAST+'], array_map(
                static fn (ReferenceBlastDb $database): array => [
                    $database->getName(),
                    $database->getDbType(),
                    number_format($database->getNumSequences()),
                    number_format($database->getNumLetters()),
                    $database->getOrganismSet()?->getName() ?? '–',
                    $database->getBlastVersion(),
                ],
                $databases,
            ));
        }

        $versions = $release->getSourceVersions();
        $counts = $release->getCounts() ?? [];

        if ($versions !== [] || $counts !== []) {
            ConsoleTable::render($io, ['Item', 'Value'], [
                ...array_map(static fn (string $source, string $version): array => [$source . ' version', $version], array_keys($versions), $versions),
                ...array_map(static fn (string $table, int $rows): array => [$table, number_format($rows)], array_keys($counts), $counts),
            ]);
        }

        return Command::SUCCESS;
    }
}
