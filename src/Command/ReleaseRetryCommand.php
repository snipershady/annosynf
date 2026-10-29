<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Reference\ReleaseStatus;
use App\Entity\ReferenceRelease;
use App\Message\RunReleaseTask;
use App\Release\ReleaseLock;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'annosynf:release:retry',
    description: 'Queues a failed reference release again, from its first task that did not complete (--force: also one stopped by a dead worker)',
)]
final readonly class ReleaseRetryCommand
{
    public function __construct(
        private ReferenceReleaseRepository $releases,
        private MessageBusInterface $bus,
        private LockFactory $locks,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $id,
        #[Option('Queue the release even if it has not failed, because its worker died: refused while a worker holds its lock')]
        bool $force = false,
    ): int {
        $release = $this->releases->find($id);

        if (!$release instanceof ReferenceRelease || $release->getStatus() === ReleaseStatus::Ready) {
            $io->error(\sprintf('Release %d does not exist or is already ready.', $id));

            return Command::FAILURE;
        }

        if ($release->getStatus() !== ReleaseStatus::Failed && !$this->workerIsGone($io, $id, $force)) {
            return Command::FAILURE;
        }

        $this->bus->dispatch(new RunReleaseTask($id, resume: true));
        $io->success(\sprintf('Release %d is queued again: it resumes from its first task that did not complete.', $id));

        return Command::SUCCESS;
    }

    /**
     * Una release non fallita è ferma solo se il suo worker è morto: lo prova il lock per release, che il worker tiene
     * per tutta la durata di un task (ReleaseTaskRunner). Se il lock è libero, nessun worker ci sta lavorando.
     */
    private function workerIsGone(SymfonyStyle $io, int $id, bool $force): bool
    {
        if (!$force) {
            $io->error(\sprintf('Release %d has not failed: it is still running. If its worker died, use --force.', $id));

            return false;
        }

        $lock = $this->locks->createLock(ReleaseLock::name($id));

        if (!$lock->acquire()) {
            $io->error(\sprintf('A worker holds the lock of release %d: it is still running.', $id));

            return false;
        }

        $lock->release();

        return true;
    }
}
