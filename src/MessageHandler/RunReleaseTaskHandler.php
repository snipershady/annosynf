<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Domain\Reference\ReleaseStatus;
use App\Entity\ReferenceRelease;
use App\Message\RunReleaseTask;
use App\Release\ReleaseTaskRunner;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Un task per messaggio, poi il messaggio per il task successivo: così ogni task è un'unità di lavoro del worker e
 * la catena si interrompe al primo errore. Il lock impedisce che due worker lavorino sulla stessa release.
 */
#[AsMessageHandler]
final readonly class RunReleaseTaskHandler
{
    /**
     * Durata del lock (s): un task di import su TrEMBL dura ore. Se il worker muore, il lock su Redis scade da solo.
     */
    private const float LOCK_TTL = 12 * 3600.0;

    public function __construct(
        private ReferenceReleaseRepository $releases,
        private ReleaseTaskRunner $runner,
        private LockFactory $locks,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RunReleaseTask $message): void
    {
        $lock = $this->locks->createLock('annosynf-release-' . $message->releaseId, self::LOCK_TTL);

        if (!$lock->acquire()) {
            return;
        }

        try {
            $release = $this->releases->find($message->releaseId);

            if (!$release instanceof ReferenceRelease || ($release->getStatus() === ReleaseStatus::Failed && !$message->resume)) {
                return;
            }

            if ($this->runner->runNext($release)) {
                $this->bus->dispatch(new RunReleaseTask($message->releaseId));
            }
        } finally {
            $lock->release();
        }
    }
}
