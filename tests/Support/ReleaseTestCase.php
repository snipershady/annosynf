<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\ReferenceDataWriter;
use App\Entity\ReferenceRelease;
use App\Message\RunReleaseTask;
use App\MessageHandler\RunReleaseTaskHandler;
use App\Release\ReleaseStorage;
use App\Repository\ReferenceReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Test delle release sul database vero: l'import aggiunge e toglie partizioni (DDL, che chiude ogni transazione),
 * quindi niente rollback come in DatabaseTestCase. Prima e dopo ogni test si cancellano tutte le release del database
 * di test, con le loro partizioni e i loro file.
 */
abstract class ReleaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->purge();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    /**
     * Fa il lavoro del worker: consegna all'handler i messaggi della coda `import` finché ce ne sono.
     *
     * @return int messaggi gestiti
     */
    protected function work(): int
    {
        $transport = self::getContainer()->get('messenger.transport.import');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $handler = self::getContainer()->get(RunReleaseTaskHandler::class);
        $handled = 0;

        while (($envelopes = [...$transport->get()]) !== []) {
            foreach ($envelopes as $envelope) {
                $message = $envelope->getMessage();
                self::assertInstanceOf(RunReleaseTask::class, $message);
                $transport->ack($envelope);
                $handler($message);
                ++$handled;
            }
        }

        return $handled;
    }

    protected function reload(int $id): ReferenceRelease
    {
        $this->entityManager->clear();

        return $this->entityManager->find(ReferenceRelease::class, $id) ?? self::fail(\sprintf('release %d not found', $id));
    }

    /**
     * @return array<string, array{string, int}> task → stato e tentativi
     */
    protected function tasks(ReferenceRelease $release): array
    {
        $tasks = [];

        foreach (self::getContainer()->get(ReferenceReleaseRepository::class)->tasks($release) as $task) {
            $tasks[$task->getTask()] = [$task->getStatus()->value, $task->getAttempts()];
        }

        return $tasks;
    }

    private function purge(): void
    {
        $writer = self::getContainer()->get(ReferenceDataWriter::class);
        $storage = self::getContainer()->get(ReleaseStorage::class);

        foreach ($this->entityManager->getRepository(ReferenceRelease::class)->findAll() as $release) {
            $id = (int) $release->getId();
            $writer->drop($id);
            $storage->remove($storage->directory($id));
        }

        $this->entityManager->getConnection()->executeStatement('DELETE FROM reference_release');
        $this->entityManager->clear();
        FakeSourceDownloader::reset();
    }
}
