<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

enum FastaHeaderStyle: string
{
    /** `>id descrizione`, come lo riscrive Bio::SeqIO. */
    case Full = 'full';

    /** Solo `>id`: il FASTA normalizzato passato ai programmi (§B.6, ex cleanHeader). */
    case IdOnly = 'id_only';
}
