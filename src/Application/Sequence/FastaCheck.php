<?php

declare(strict_types=1);

namespace App\Application\Sequence;

use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\CompositionSummary;
use App\Domain\Sequence\FastaValidationReport;

/**
 * Esito del controllo di un FASTA prima di un'analisi.
 */
final readonly class FastaCheck
{
    /**
     * @param ?AlphabetDetection  $detection   null se il file non ha la struttura di un FASTA
     * @param Alphabet            $alphabet    quello usato per la validazione: il rilevato, oppure DNA se non si può rilevare
     * @param ?CompositionSummary $composition solo per file nucleotidici leggibili
     */
    public function __construct(
        public string $path,
        public ?AlphabetDetection $detection,
        public Alphabet $alphabet,
        public FastaValidationReport $validation,
        public ?CompositionSummary $composition,
    ) {
    }

    public function isValid(): bool
    {
        return $this->validation->isValid();
    }
}
