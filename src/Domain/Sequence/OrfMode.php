<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Dove può iniziare un ORF (opzione `-o` di dna2pep, parametro d2pMode di Annocript).
 */
enum OrfMode: string
{
    /** Subito dopo uno stop (o dall'inizio del frame): qualunque segmento tra due stop. */
    case None = 'none';

    /** Al primo start "stretto" (ATG nel codice standard). */
    case Strict = 'strict';

    /** Al primo start, stretto o alternativo (ATG, TTG, CTG nel codice standard). */
    case Any = 'any';
}
