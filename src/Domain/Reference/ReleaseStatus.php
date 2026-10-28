<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Stato di una release di riferimento (place del workflow Release, CLAUDE.md §B.5.1).
 */
enum ReleaseStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Importing = 'importing';
    case Mapping = 'mapping';
    case Indexing = 'indexing';
    case Ready = 'ready';
    case Failed = 'failed';
}
