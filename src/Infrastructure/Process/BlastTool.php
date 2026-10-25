<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

/**
 * Binari BLAST+ di servizio (i programmi di ricerca sono in {@see \App\Domain\Blast\BlastProgram}).
 */
enum BlastTool: string
{
    case MakeBlastDb = 'makeblastdb';
    case BlastDbCmd = 'blastdbcmd';
}
