<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Alfabeto rilevato su tutto un FASTA, con il dettaglio per sequenza.
 */
final readonly class AlphabetDetection
{
    public const int MAX_LOCATIONS = 20;

    /**
     * @param ?Alphabet                                             $alphabet              alfabeto della maggioranza; null se nessuna sequenza ha lettere
     * @param ?Alphabet                                             $firstSequenceAlphabet quello della prima sequenza, l'unico che guardava il legacy
     * @param array{dna: int, rna: int, protein: int, unknown: int} $counts                sequenze per alfabeto ("unknown": senza lettere)
     * @param list<FastaLocation>                                   $mismatches            prime sequenze di famiglia diversa (nucleotidi/proteine) dal file
     */
    public function __construct(
        public ?Alphabet $alphabet,
        public ?Alphabet $firstSequenceAlphabet,
        public array $counts,
        public array $mismatches,
    ) {
    }

    /**
     * Il file contiene sia sequenze nucleotidiche sia proteiche (DNA insieme a RNA non conta).
     */
    public function isMixed(): bool
    {
        return $this->counts['protein'] > 0 && $this->counts['dna'] + $this->counts['rna'] > 0;
    }
}
