<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Una sequenza di un file FASTA: l'header così com'è (senza `>`) e la sequenza senza spazi né fine riga.
 * Il contenuto non è validato: lo fa il validatore, che usa anche numero di riga e riga più lunga.
 */
final readonly class FastaRecord
{
    /**
     * @param string $header      testo dopo `>`, senza fine riga (eventuali spazi iniziali compresi)
     * @param string $sequence    righe di sequenza unite, senza spazi, tab e CR; maiuscole/minuscole invariate
     * @param int    $lineNumber  riga dell'header nel file, da 1
     * @param int    $longestLine lunghezza della riga di sequenza più lunga, prima di togliere gli spazi
     */
    public function __construct(
        public string $header,
        public string $sequence,
        public int $lineNumber,
        public int $longestLine,
    ) {
    }

    /**
     * Primo token dell'header, saltando gli spazi iniziali (come Bio::SeqIO e il cleanHeader di Annocript).
     */
    public function id(): string
    {
        return $this->headerParts()[0];
    }

    /**
     * Il resto dell'header dopo l'id, senza spazi ai bordi.
     */
    public function description(): string
    {
        return $this->headerParts()[1];
    }

    public function length(): int
    {
        return \strlen($this->sequence);
    }

    /**
     * @return array{string, string}
     */
    private function headerParts(): array
    {
        $parts = preg_split('/\s+/', trim($this->header), 2);

        return [$parts[0] ?? '', trim($parts[1] ?? '')];
    }
}
