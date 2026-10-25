<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

enum IssueSeverity: string
{
    /** Il file non si può analizzare finché non viene corretto. */
    case Error = 'error';

    /** Il file si può analizzare, ma conviene controllarlo. */
    case Warning = 'warning';
}
