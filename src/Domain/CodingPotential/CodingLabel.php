<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Etichetta di CPC2 (colonna label): l'etichetta più probabile del modello, 1 = coding, 0 = noncoding.
 */
enum CodingLabel: string
{
    case Coding = 'coding';
    case Noncoding = 'noncoding';
}
