<?php

declare(strict_types=1);

namespace App\Release;

use App\Application\Port\SourceDownloader;
use App\Application\Port\TextLines;
use App\Domain\Reference\ReleaseNoteParser;
use App\Domain\Reference\ReleasePlan;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceFile;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Shared\AnnosynfException;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use App\Message\RunReleaseTask;
use App\Repository\ReferenceReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Crea una release (ex fase `doDbCreation` di Annocript): legge la versione dalla release note corrente
 * (`resolve_version`, §B.5.1), registra le sorgenti e i task del piano e accoda il primo task. Le sorgenti UniProt
 * sono sempre quelle della release corrente: una versione diversa non si può scaricare.
 */
final readonly class ReleaseCreator
{
    public function __construct(
        private SourceCatalogue $catalogue,
        private SourceDownloader $downloader,
        private TextLines $lines,
        private ReleaseNoteParser $releaseNotes,
        private ReferenceReleaseRepository $releases,
        private EntityManagerInterface $entityManager,
        private ReleaseStorage $storage,
        private ClockInterface $clock,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param ?string $expectedVersion `YYYY_MM` atteso, null per la release corrente qualunque sia
     *
     * @throws AnnosynfException se la release note non si scarica o non è valida, se non corrisponde a quanto
     *                           richiesto o se la release esiste già
     */
    public function create(ReleaseType $type, ?UnirefVariant $variant, string $cddName = 'Cdd', ?string $expectedVersion = null): ReferenceRelease
    {
        $versionSource = SourceCatalogue::versionSource($type);
        $url = $this->catalogue->url($versionSource, $variant);
        $temporary = $this->storage->temporaryDirectory();

        try {
            $note = $this->downloader->download($url, $temporary . '/' . basename($url));
            $release = $this->register($type, $variant, $cddName, $expectedVersion, $note);
            $releaseId = $release->getId() ?? throw new LogicException('The release has not been saved.');
            $path = $this->storage->sourcePath($releaseId, $url);
            $this->storage->move($note->path, $path);
            $this->releaseSources($release)[$versionSource->value]->recordDownload(new SourceFile($note->url, $path, $note->bytes, $note->sha256, false), $this->clock->now());
            $this->entityManager->flush();
            $this->bus->dispatch(new RunReleaseTask($releaseId));
        } finally {
            $this->storage->remove($temporary);
        }

        return $release;
    }

    private function register(ReleaseType $type, ?UnirefVariant $variant, string $cddName, ?string $expectedVersion, SourceFile $note): ReferenceRelease
    {
        $parsed = $this->releaseNotes->parse($type, $this->lines->lines($note->path));

        if ($expectedVersion !== null && $expectedVersion !== $parsed->version) {
            throw ReleaseNotCreatedException::otherVersion($expectedVersion, $parsed->version);
        }

        if ($variant instanceof UnirefVariant && $parsed->unirefVariant instanceof UnirefVariant && $parsed->unirefVariant !== $variant) {
            throw ReleaseNotCreatedException::otherVariant($variant->value, $parsed->unirefVariant->value);
        }

        $existing = $this->releases->findOneByIdentity($type, $parsed->version, $variant);

        if ($existing instanceof ReferenceRelease) {
            throw ReleaseNotCreatedException::exists(\sprintf('%s %s%s', $type->value, $parsed->version, $variant instanceof UnirefVariant ? ' ' . $variant->value : ''), (int) $existing->getId());
        }

        $release = new ReferenceRelease($type, $parsed->version, $variant, $cddName, $this->clock->now());
        $this->entityManager->persist($release);

        foreach (SourceCatalogue::sourcesFor($type) as $kind) {
            $this->entityManager->persist(new ReferenceSourceFile($release, $kind, $this->catalogue->url($kind, $variant)));
        }

        foreach (ReleasePlan::tasks($type) as $task) {
            $this->entityManager->persist(new ReferenceTask($release, $task));
        }

        $this->entityManager->flush();

        return $release;
    }

    /**
     * @return array<string, ReferenceSourceFile> sorgenti della release per tipo
     */
    private function releaseSources(ReferenceRelease $release): array
    {
        $sources = [];

        foreach ($this->releases->sources($release) as $source) {
            $sources[$source->getKind()->value] = $source;
        }

        return $sources;
    }
}
