<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

use App\Domain\Sequence\GeneticCode;

/**
 * Traduzione dell'ORF come `mRNA_translate` di CPC2, cioè `Seq.translate()` di Biopython 1.76 con il codice
 * genetico standard e la tabella dei codoni ambigui (`CodonTable.ambiguous_generic_by_id[1]`).
 *
 * Ogni codone completo dà un carattere. Un codone ambiguo si espande nei codoni esatti che rappresenta: se sono
 * tutti stop si scrive `*`; se mescolano stop e amminoacidi `X`; se danno un solo amminoacido quello; altrimenti
 * la lettera ambigua più specifica che li copre tutti (B = D/N, Z = E/Q, J = I/L, X per il resto).
 *
 * Differenza voluta: Biopython solleva TranslationError per un codone con un carattere che non conosce (`-`, o
 * `X` quando il codone potrebbe essere uno stop) e CPC2 si interrompe senza output per l'intero file; qui il
 * codone diventa `X`, che il punto isoelettrico ignora, come gli altri codoni non traducibili.
 */
final class Cpc2Translator
{
    /** Basi rappresentate da ogni lettera (`ambiguous_dna_values` di Biopython, X compresa). */
    private const array BASES = [
        'A' => 'A', 'C' => 'C', 'G' => 'G', 'T' => 'T',
        'M' => 'AC', 'R' => 'AG', 'W' => 'AT', 'S' => 'CG', 'Y' => 'CT', 'K' => 'GT',
        'V' => 'ACG', 'H' => 'ACT', 'D' => 'AGT', 'B' => 'CGT', 'N' => 'ACGT', 'X' => 'ACGT',
    ];

    /** Lettere ambigue di `extended_protein_values` che coprono esattamente due amminoacidi. */
    private const array AMBIGUOUS = ['DN' => 'B', 'EQ' => 'Z', 'IL' => 'J'];

    private readonly GeneticCode $code;

    /** @var array<string, string> traduzioni dei codoni già calcolate */
    private array $translated = [];

    public function __construct()
    {
        $this->code = GeneticCode::standard();
    }

    /**
     * @param string $dna nucleotidi normalizzati come li prepara CPC2 (maiuscole, U → T); le basi oltre l'ultimo
     *                    codone completo si ignorano
     */
    public function translate(string $dna): string
    {
        $peptide = '';

        for ($position = 0; $position + 3 <= \strlen($dna); $position += 3) {
            $codon = substr($dna, $position, 3);
            $peptide .= $this->translated[$codon] ??= $this->aminoAcid($codon);
        }

        return $peptide;
    }

    private function aminoAcid(string $codon): string
    {
        $exact = $this->code->aminoAcid($codon);

        if ($exact !== null) {
            return $exact;
        }

        if (strspn($codon, implode('', array_keys(self::BASES))) !== 3) {
            return 'X';
        }

        $aminoAcids = [];

        foreach (str_split(self::BASES[$codon[0]]) as $first) {
            foreach (str_split(self::BASES[$codon[1]]) as $second) {
                foreach (str_split(self::BASES[$codon[2]]) as $third) {
                    $aminoAcids[(string) $this->code->aminoAcid($first . $second . $third)] = true;
                }
            }
        }

        if (isset($aminoAcids['*'])) {
            return \count($aminoAcids) === 1 ? '*' : 'X';
        }

        $aminoAcids = array_keys($aminoAcids);
        sort($aminoAcids);

        return \count($aminoAcids) === 1 ? $aminoAcids[0] : self::AMBIGUOUS[implode('', $aminoAcids)] ?? 'X';
    }
}
