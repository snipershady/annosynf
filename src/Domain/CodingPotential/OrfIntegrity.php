<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Completezza dell'ORF di CPC2 (colonna ORF_integrity, quarta feature del modello SVM).
 */
enum OrfIntegrity: int
{
    /** L'ORF termina con un codone di stop. */
    case Complete = 1;

    /** L'ORF arriva alla fine della sequenza senza stop; CPC2 usa questo valore anche quando non c'è ORF. */
    case Incomplete = -1;
}
