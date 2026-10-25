<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Frame di lettura come li numera dna2pep: 1, 2, 3 sulla sequenza (dalla prima, seconda, terza base), −1, −2,
 * −3 sul reverse complement.
 */
enum ReadingFrame: int
{
    case Plus1 = 1;
    case Plus2 = 2;
    case Plus3 = 3;
    case Minus1 = -1;
    case Minus2 = -2;
    case Minus3 = -3;

    /**
     * Ordine in cui dna2pep visita i frame cercando l'ORF più lungo: quello delle chiavi del suo dict Python 2
     * (verificato nel container legacy-runner). A parità di lunghezza vince il frame visitato prima.
     *
     * @return list<self>
     */
    public static function searchOrder(): array
    {
        return [self::Plus1, self::Plus3, self::Plus2, self::Minus1, self::Minus3, self::Minus2];
    }

    public function isForward(): bool
    {
        return $this->value > 0;
    }

    public function strand(): string
    {
        return $this->isForward() ? '+' : '-';
    }

    /**
     * Basi saltate all'inizio della sequenza (o del reverse complement): |frame| − 1, il "frame" 0..2 di
     * Annocript e di BLAST.
     */
    public function offset(): int
    {
        return abs($this->value) - 1;
    }
}
