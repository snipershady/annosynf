<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\Shared\AnnosynfException;

/**
 * Potenziale codificante di una sequenza nucleotidica (CPC2, §B.8.2). L'adapter decide come: port nativo o,
 * per validazione, il programma esterno.
 */
interface CodingPotentialPredictor
{
    /**
     * @throws AnnosynfException se il modello non si può caricare
     */
    public function predict(string $sequence): Cpc2Prediction;
}
