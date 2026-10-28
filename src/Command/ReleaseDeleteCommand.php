<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Port\ReferenceDataWriter;
use App\Domain\Reference\ReleaseStatus;
use App\Entity\ReferenceRelease;
use App\Release\ReleaseStorage;
use App\Repository\ReferenceReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cancella una release: le sue partizioni `ref_*` (istantaneo), i file scaricati e il catalogo (task, sorgenti,
 * database BLAST e insiemi di organismi, a cascata).
 */
#[AsCommand(
    name: 'annosynf:release:delete',
    description: 'Deletes a reference release with its data and downloaded files',
)]
final readonly class ReleaseDeleteCommand
{
    private const array RUNNING = [ReleaseStatus::Downloading, ReleaseStatus::Importing, ReleaseStatus::Mapping, ReleaseStatus::Indexing];

    public function __construct(
        private ReferenceReleaseRepository $releases,
        private ReferenceDataWriter $writer,
        private ReleaseStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $id,
        #[Option('Delete without asking, even a release whose import looks still running (a worker that died)')]
        bool $force = false,
    ): int {
        $release = $this->releases->find($id);

        if (!$release instanceof ReferenceRelease) {
            $io->error(\sprintf('Release %d does not exist.', $id));

            return Command::FAILURE;
        }

        if (!$force && \in_array($release->getStatus(), self::RUNNING, true)) {
            $io->error(\sprintf('Release %d is %s: wait for its import to stop, or use --force if its worker died.', $id, $release->getStatus()->value));

            return Command::FAILURE;
        }

        if (!$force && !$io->confirm(\sprintf('Delete release %d (%s %s) with all its data?', $id, $release->getType()->value, $release->getVersion()), false)) {
            $io->writeln('Nothing deleted.');

            return Command::SUCCESS;
        }

        $this->writer->drop($id);
        $this->storage->remove($this->storage->directory($id));
        $this->storage->remove($this->storage->databaseDirectory($id));

        $this->entityManager->remove($release);
        $this->entityManager->flush();

        $io->success(\sprintf('Release %d deleted.', $id));

        return Command::SUCCESS;
    }
}
