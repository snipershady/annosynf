<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Shared\AnnosynfException;

/**
 * Righe di un file di testo, in streaming (compresso o no: lo decide l'adapter).
 */
interface TextLines
{
    /**
     * @throws AnnosynfException se il file non si può leggere
     *
     * @return iterable<string> le righe, che possono ancora terminare con "\r"
     */
    public function lines(string $path): iterable;
}
