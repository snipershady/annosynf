<?php

declare(strict_types=1);

namespace App\Domain\Blast;

/**
 * Programmi BLAST+ usati dalla pipeline (§A.8): il valore è il nome del binario.
 */
enum BlastProgram: string
{
    case Blastx = 'blastx';
    case Blastp = 'blastp';
    case Blastn = 'blastn';
    case Tblastn = 'tblastn';
    case Rpstblastn = 'rpstblastn';
    case Rpsblast = 'rpsblast';
}
