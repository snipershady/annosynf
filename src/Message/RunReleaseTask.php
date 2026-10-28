<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Esegue il prossimo task non completato di una release; se ne restano altri, l'handler accoda il messaggio
 * successivo. Un messaggio per volta per release: i task si eseguono in ordine.
 */
final readonly class RunReleaseTask
{
    /**
     * @param bool $resume true solo per riprendere una release fallita (annosynf:release:retry): un messaggio
     *                     qualsiasi non deve farla ripartire
     */
    public function __construct(
        public int $releaseId,
        public bool $resume = false,
    ) {
    }
}
