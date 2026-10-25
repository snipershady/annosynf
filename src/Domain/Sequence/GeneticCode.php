<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use InvalidArgumentException;

/**
 * Un codice genetico NCBI, costruito dalle due stringhe della tabella ufficiale come fa dna2pep
 * (mod_translate.parseNcbiTable): `ncbieaa` dà l'amminoacido di ogni codone all'interno della sequenza,
 * `sncbieaa` segna con `M` i codoni che, in prima posizione, si leggono come metionina (start).
 * I codoni sono nell'ordine NCBI (basi TCAG). Si traducono solo codoni esatti, maiuscoli, con T (non U):
 * normalizzazione e codoni degenerati IUPAC sono compito di chi traduce.
 */
final readonly class GeneticCode
{
    public const string BASES = 'TCAG';

    /** Tabella NCBI 1, "Standard" (ncbi_genetic_codes.py di dna2pep, versione 3.8 della tabella NCBI). */
    private const string STANDARD_AMINO_ACIDS = 'FFLLSSSSYY**CC*WLLLLPPPPHHQQRRRRIIIMTTTTNNKKSSRRVVVVAAAADDEEGGGG';

    private const string STANDARD_STARTS = '---M---------------M---------------M----------------------------';

    /**
     * @param array<string, string> $internal codone → amminoacido all'interno della sequenza
     * @param array<string, string> $first    codone → amminoacido come primo codone
     */
    private function __construct(
        public int $id,
        public string $name,
        private array $internal,
        private array $first,
    ) {
    }

    public static function standard(): self
    {
        return self::fromNcbi(1, 'Standard', self::STANDARD_AMINO_ACIDS, self::STANDARD_STARTS);
    }

    /**
     * @param string $aminoAcids campo `ncbieaa` della tabella NCBI (64 caratteri)
     * @param string $starts     campo `sncbieaa` (64 caratteri, `M` per gli start, `-` altrove)
     *
     * @throws InvalidArgumentException se le stringhe non hanno 64 caratteri
     */
    public static function fromNcbi(int $id, string $name, string $aminoAcids, string $starts): self
    {
        if (\strlen($aminoAcids) !== 64 || \strlen($starts) !== 64) {
            throw new InvalidArgumentException(\sprintf('An NCBI genetic code needs 64 amino acids and 64 start marks, got %d and %d.', \strlen($aminoAcids), \strlen($starts)));
        }

        $internal = [];
        $first = [];

        foreach (self::codons() as $index => $codon) {
            $internal[$codon] = $aminoAcids[$index];
            $first[$codon] = $starts[$index] === 'M' ? 'M' : $aminoAcids[$index];
        }

        return new self($id, $name, $internal, $first);
    }

    /**
     * I 64 codoni nell'ordine delle tabelle NCBI (TTT, TTC, TTA, TTG, TCT, …, GGG).
     *
     * @return list<string>
     */
    public static function codons(): array
    {
        $codons = [];

        foreach (str_split(self::BASES) as $first) {
            foreach (str_split(self::BASES) as $second) {
                foreach (str_split(self::BASES) as $third) {
                    $codons[] = $first . $second . $third;
                }
            }
        }

        return $codons;
    }

    /**
     * Amminoacido del codone all'interno della sequenza (`*` per gli stop); null se non è un codone esatto.
     */
    public function aminoAcid(string $codon): ?string
    {
        return $this->internal[$codon] ?? null;
    }

    /**
     * Amminoacido del codone in prima posizione: `M` per gli start, altrimenti come all'interno.
     */
    public function firstAminoAcid(string $codon): ?string
    {
        return $this->first[$codon] ?? null;
    }

    public function isStop(string $codon): bool
    {
        return ($this->internal[$codon] ?? null) === '*';
    }

    /**
     * Annotazione del codone (mod_translate.annotate) per un codone esatto; null altrimenti.
     */
    public function mark(string $codon): ?CodonMark
    {
        $first = $this->first[$codon] ?? null;

        return match (true) {
            $first === null => null,
            $first === '*' => CodonMark::Stop,
            $first !== 'M' => CodonMark::Other,
            $this->internal[$codon] === 'M' => CodonMark::PrimaryStart,
            default => CodonMark::AlternativeStart,
        };
    }
}
