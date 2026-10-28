<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Reference\ReleaseStatus;
use App\Entity\ReferenceRelease;
use App\Message\RunReleaseTask;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'annosynf:release:retry',
    description: 'Queues a failed reference release again, from its first task that did not complete',
)]
final readonly class ReleaseRetryCommand
{
    public function __construct(
        private ReferenceReleaseRepository $releases,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $id,
    ): int {
        $release = $this->releases->find($id);

        if (!$release instanceof ReferenceRelease || $release->getStatus() !== ReleaseStatus::Failed) {
            $io->error(\sprintf('Release %d does not exist or has not failed.', $id));

            return Command::FAILURE;
        }

        $this->bus->dispatch(new RunReleaseTask($id, resume: true));
        $io->success(\sprintf('Release %d is queued again: it resumes from its first task that did not complete.', $id));

        return Command::SUCCESS;
    }
}
