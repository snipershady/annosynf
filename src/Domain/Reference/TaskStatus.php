<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Stato di un task di import di una release (`reference_task`) o del download di un file sorgente.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
