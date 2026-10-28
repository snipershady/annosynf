<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use InvalidArgumentException;

/**
 * Task di una release nell'ordine di esecuzione (§B.5.1): download di ogni sorgente, import, associazioni e chiusura.
 * La fase di ogni task è lo stato del workflow `reference_release` mentre gira. L'indicizzazione BLAST (step 6.4)
 * si inserirà prima di `finalize`, nella fase `indexing`.
 */
final class ReleasePlan
{
    public const string FINALIZE = 'finalize';

    private const string DOWNLOAD = 'download:';

    /**
     * @return non-empty-list<string>
     */
    public static function tasks(ReleaseType $type): array
    {
        return [
            ...array_map(self::download(...), SourceCatalogue::sourcesFor($type)),
            ...array_map(static fn (ImportTask $task): string => $task->value, ImportTask::planFor($type)),
            self::FINALIZE,
        ];
    }

    public static function download(SourceKind $kind): string
    {
        return self::DOWNLOAD . $kind->value;
    }

    /**
     * Sorgente scaricata dal task, null se il task non è un download.
     */
    public static function downloadedSource(string $task): ?SourceKind
    {
        return str_starts_with($task, self::DOWNLOAD) ? SourceKind::tryFrom(substr($task, \strlen(self::DOWNLOAD))) : null;
    }

    /**
     * @throws InvalidArgumentException se il task non è nel piano di nessuna release
     */
    public static function phase(string $task): ReleaseStatus
    {
        return match (true) {
            self::downloadedSource($task) instanceof SourceKind => ReleaseStatus::Downloading,
            ImportTask::tryFrom($task) instanceof ImportTask => ImportTask::from($task)->phase(),
            $task === self::FINALIZE => ReleaseStatus::Indexing,
            default => throw new InvalidArgumentException(\sprintf('"%s" is not a release task.', $task)),
        };
    }
}
