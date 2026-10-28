<?php

declare(strict_types=1);

namespace App\Release;

use App\Application\Port\ReferenceDataWriter;
use App\Application\Port\SourceDownloader;
use App\Application\Release\ImportReferenceData;
use App\Domain\Reference\ImportTask;
use App\Domain\Reference\ReleasePlan;
use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\SourceKind;
use App\Domain\Reference\TaskStatus;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Repository\ReferenceReleaseRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Throwable;

/**
 * Esegue i task di una release uno alla volta, nell'ordine del piano ({@see ReleasePlan}): porta il workflow nella
 * fase del task, lo esegue e ne registra l'esito. Un errore segna il task e la release come falliti (con il messaggio,
 * visibile in `annosynf:release:show`) e ferma la catena; si riprende con `annosynf:release:retry`.
 */
final readonly class ReleaseTaskRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReferenceReleaseRepository $releases,
        #[Target('reference_release')]
        private WorkflowInterface $workflow,
        private SourceDownloader $downloader,
        private ImportReferenceData $import,
        private ReferenceDataWriter $writer,
        private ReleaseStorage $storage,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool true se restano task da eseguire
     */
    public function runNext(ReferenceRelease $release): bool
    {
        $task = $this->nextTask($release);

        if (!$task instanceof ReferenceTask) {
            return false;
        }

        try {
            $this->enter($release, ReleasePlan::phase($task->getTask()));
            $task->start($this->now());
            $this->entityManager->flush();
            [$rows, $message] = $this->execute($release, $task->getTask());
            $task->complete($this->now(), $rows, $message);

            if ($task->getTask() === ReleasePlan::FINALIZE) {
                $this->enter($release, ReleaseStatus::Ready);
            }
        } catch (Throwable $throwable) {
            $this->fail($release, $task, $throwable);
        }

        $this->entityManager->flush();

        return !\in_array($release->getStatus(), [ReleaseStatus::Ready, ReleaseStatus::Failed], true);
    }

    /**
     * @return array{?int, string} righe caricate e riassunto
     */
    private function execute(ReferenceRelease $release, string $task): array
    {
        $releaseId = $release->getId() ?? throw new LogicException('The release has not been saved yet.');
        $download = ReleasePlan::downloadedSource($task);

        if ($download instanceof SourceKind) {
            $source = $this->source($release, $download);
            $file = $this->downloader->download($source->getUrl(), $this->storage->sourcePath($releaseId, $source->getUrl()));
            $source->recordDownload($file, $this->now());

            return [null, \sprintf('%s %d bytes from %s', $file->reused ? 'already had' : 'downloaded', $file->bytes, $file->url)];
        }

        if ($task === ReleasePlan::FINALIZE) {
            $counts = $this->writer->counts($releaseId);
            $release->recordCounts($counts);

            return [array_sum($counts), 'row counts recorded'];
        }

        $import = ImportTask::from($task);
        $path = $this->source($release, $import->source())->getLocalPath() ?? throw new LogicException(\sprintf('"%s" has not been downloaded.', $import->source()->value));
        $this->writer->prepare($releaseId);
        $outcome = $this->import->run($import, $releaseId, $path, $release->getUnirefVariant());

        foreach ($outcome->sourceVersions as $source => $version) {
            $release->recordSourceVersion($source, $version);
        }

        return [$outcome->rows, $outcome->summary()];
    }

    /**
     * Porta il workflow alla fase del task: in avanti una fase alla volta, oppure direttamente dopo un errore.
     */
    private function enter(ReferenceRelease $release, ReleaseStatus $phase): void
    {
        // una release pronta non ha più task: se capitasse, il workflow rifiuterebbe la transizione
        while ($release->getStatus() !== $phase) {
            $transition = match ($release->getStatus()) {
                ReleaseStatus::Pending => 'download',
                ReleaseStatus::Downloading => 'import',
                ReleaseStatus::Importing => 'map',
                ReleaseStatus::Mapping => 'index',
                ReleaseStatus::Indexing, ReleaseStatus::Ready => 'complete',
                ReleaseStatus::Failed => 'resume_' . $phase->value,
            };
            $this->workflow->apply($release, $transition, ['at' => $this->now()]);
        }
    }

    private function fail(ReferenceRelease $release, ReferenceTask $task, Throwable $exception): void
    {
        $task->fail($this->now(), $exception->getMessage());

        if ($this->workflow->can($release, 'fail')) {
            $this->workflow->apply($release, 'fail', ['at' => $this->now(), 'error' => \sprintf('%s: %s', $task->getTask(), $exception->getMessage())]);
        }

        $this->logger->error('Release {release} failed at {task}: {message}', [
            'release' => $release->getId(),
            'task' => $task->getTask(),
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    private function nextTask(ReferenceRelease $release): ?ReferenceTask
    {
        foreach ($this->releases->tasks($release) as $task) {
            if ($task->getStatus() !== TaskStatus::Done) {
                return $task;
            }
        }

        return null;
    }

    private function source(ReferenceRelease $release, SourceKind $kind): ReferenceSourceFile
    {
        return $this->entityManager->getRepository(ReferenceSourceFile::class)->findOneBy(['release' => $release, 'kind' => $kind])
            ?? throw new LogicException(\sprintf('The release has no "%s" source.', $kind->value));
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
