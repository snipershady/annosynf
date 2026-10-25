<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Annotazione di un codone come la scrive dna2pep (mod_translate.annotate), un carattere per codone.
 */
enum CodonMark: string
{
    /** Almeno una traduzione possibile è uno stop. */
    case Stop = '*';

    /** Start "stretto": metionina sia come primo codone sia all'interno (ATG nel codice standard). */
    case PrimaryStart = 'M';

    /** Start alternativo: metionina solo come primo codone (TTG, CTG nel codice standard). */
    case AlternativeStart = 'm';

    case Other = '.';
}
