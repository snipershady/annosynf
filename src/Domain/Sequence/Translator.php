<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Traduzione e annotazione dei codoni come mod_translate di dna2pep 1.1 (translate, annotate, decode).
 *
 * La sequenza si normalizza (maiuscole, U → T) e si legge a triplette dalla prima base. Un codone degenerato
 * IUPAC si espande in tutti i codoni che rappresenta: se danno un solo amminoacido si usa quello, se danno
 * D e N si scrive B, se danno E e Q si scrive Z, altrimenti X. Le triplette incomplete non producono nulla
 * (nemmeno nell'annotazione); lo stesso vale per una tripletta con caratteri non IUPAC, che dna2pep non vede
 * mai perché scarta l'intera sequenza.
 */
final class Translator
{
    /** Basi rappresentate da ogni codice IUPAC, nell'ordine di mod_translate (decide l'ordine delle espansioni). */
    private const array IUPAC = [
        'A' => 'A', 'C' => 'C', 'G' => 'G', 'T' => 'T',
        'R' => 'AG', 'Y' => 'CT', 'M' => 'CA', 'K' => 'TG', 'W' => 'AT', 'S' => 'GC',
        'B' => 'CGT', 'D' => 'AGT', 'H' => 'ACT', 'V' => 'ACG', 'N' => 'ACGT',
    ];

    private readonly GeneticCode $code;

    /** @var array<string, list<string>> traduzioni distinte per "codone|primo" già calcolate */
    private array $decoded = [];

    public function __construct(?GeneticCode $code = null)
    {
        $this->code = $code ?? GeneticCode::standard();
    }

    /**
     * Maiuscole e U → T (mod_translate.trim).
     */
    public static function normalize(string $dna): string
    {
        return str_replace('U', 'T', strtoupper($dna));
    }

    /**
     * @param bool $firstIsStart    il primo codone si legge come start (TTG → M); come `not -a` di dna2pep
     * @param bool $readThroughStop false per fermarsi al primo codone la cui prima traduzione possibile è uno stop
     */
    public function translate(string $dna, bool $firstIsStart = true, bool $readThroughStop = true): string
    {
        $peptide = '';
        $first = $firstIsStart;

        foreach (str_split(self::normalize($dna), 3) as $codon) {
            $aminoAcids = $this->decode($codon, $first);
            $first = false;

            if ($aminoAcids === []) {
                continue;
            }

            if ($aminoAcids[0] === '*' && !$readThroughStop) {
                break;
            }

            $peptide .= $this->ambiguityCode($aminoAcids);
        }

        return $peptide;
    }

    /**
     * Un carattere per codone completo: `*` se una traduzione possibile è uno stop, `M` se è solo metionina
     * anche all'interno, `m` se è metionina solo come primo codone, `.` altrimenti (mod_translate.annotate).
     */
    public function annotate(string $dna): string
    {
        return implode('', array_map(static fn (CodonMark $mark): string => $mark->value, $this->marks($dna)));
    }

    /**
     * @return list<CodonMark>
     */
    public function marks(string $dna): array
    {
        $marks = [];

        foreach (str_split(self::normalize($dna), 3) as $codon) {
            $asFirst = $this->decode($codon, true);

            if ($asFirst === []) {
                continue;
            }

            $marks[] = match (true) {
                \in_array('*', $asFirst, true) => CodonMark::Stop,
                $asFirst !== ['M'] => CodonMark::Other,
                $this->decode($codon, false) === ['M'] => CodonMark::PrimaryStart,
                default => CodonMark::AlternativeStart,
            };
        }

        return $marks;
    }

    /**
     * Traduzioni distinte del codone, nell'ordine di espansione (mod_translate.decode + condense).
     *
     * @return list<string>
     */
    private function decode(string $codon, bool $first): array
    {
        return $this->decoded[$codon . '|' . ($first ? '1' : '0')] ??= array_values(array_unique($this->expand($codon, $first)));
    }

    /**
     * @return list<string>
     */
    private function expand(string $codon, bool $first): array
    {
        if (\strlen($codon) !== 3 || strspn($codon, implode('', array_keys(self::IUPAC))) !== 3) {
            return [];
        }

        // Prima posizione degenerata; se non ce n'è, è un codone esatto e lo traduce la tabella.
        $position = strspn($codon, 'ACGT');

        if ($position === 3) {
            $aminoAcid = $first ? $this->code->firstAminoAcid($codon) : $this->code->aminoAcid($codon);

            return $aminoAcid === null ? [] : [$aminoAcid];
        }

        $aminoAcids = [];

        foreach (str_split(self::IUPAC[$codon[$position]]) as $base) {
            $aminoAcids = [...$aminoAcids, ...$this->expand(substr_replace($codon, $base, $position, 1), $first)];
        }

        return $aminoAcids;
    }

    /**
     * @param non-empty-list<string> $aminoAcids
     */
    private function ambiguityCode(array $aminoAcids): string
    {
        if (\count($aminoAcids) === 1) {
            return $aminoAcids[0];
        }

        return match (implode('', $aminoAcids)) {
            'DN', 'ND' => 'B',
            'EQ', 'QE' => 'Z',
            default => 'X',
        };
    }
}
