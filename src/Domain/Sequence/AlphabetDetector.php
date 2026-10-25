<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Riconosce se le sequenze sono DNA, RNA o proteine con la stessa euristica di Bio::PrimarySeq
 * (`_guess_alphabet_from_string`, BioPerl 1.7.2), ma su tutto il file: il legacy guardava solo la prima
 * sequenza (detect_fasta_type).
 */
final class AlphabetDetector
{
    /** Lettere che compaiono solo nelle proteine (IUPAC non ambigue). */
    private const string PROTEIN_ONLY = 'EFIJLOPQXZ';

    /** Lettere che BioPerl conta come nucleotidiche (comprese W, S, K, M). */
    private const string NUCLEOTIDE_LIKE = 'ATUGCNWSKM';

    /** Oltre questa frazione di lettere nucleotidiche la sequenza è DNA o RNA. */
    private const float NUCLEOTIDE_FRACTION = 0.7;

    /**
     * Alfabeto di una sola sequenza; null se, tolti `-`, `.` e `?`, non resta nulla.
     */
    public function guess(string $sequence): ?Alphabet
    {
        $letters = strtoupper(str_replace(['-', '.', '?'], '', $sequence));
        $total = \strlen($letters);

        if ($total === 0) {
            return null;
        }

        if (strpbrk($letters, self::PROTEIN_ONLY) !== false) {
            return Alphabet::Protein;
        }

        $nucleotides = $total - \strlen(str_replace(str_split(self::NUCLEOTIDE_LIKE), '', $letters));

        if ($nucleotides / $total <= self::NUCLEOTIDE_FRACTION) {
            return Alphabet::Protein;
        }

        return str_contains($letters, 'U') ? Alphabet::Rna : Alphabet::Dna;
    }

    /**
     * @param iterable<FastaRecord> $records di norma da {@see FastaReader::read()}
     */
    public function detect(iterable $records): AlphabetDetection
    {
        $counts = ['dna' => 0, 'rna' => 0, 'protein' => 0, 'unknown' => 0];
        // Prime posizioni per famiglia: alla fine servono solo quelle della famiglia in minoranza.
        $locations = ['nucleotide' => [], 'protein' => []];
        $first = null;
        $isFirst = true;

        foreach ($records as $record) {
            $alphabet = $this->guess($record->sequence);

            if ($isFirst) {
                $first = $alphabet;
                $isFirst = false;
            }

            if (!$alphabet instanceof Alphabet) {
                ++$counts['unknown'];

                continue;
            }

            ++$counts[$alphabet->value];
            $family = $alphabet->isNucleotide() ? 'nucleotide' : 'protein';

            if (\count($locations[$family]) < AlphabetDetection::MAX_LOCATIONS) {
                $locations[$family][] = new FastaLocation($record->lineNumber, $record->id());
            }
        }

        $alphabet = $this->majority($counts);
        $mismatches = match (true) {
            !$alphabet instanceof Alphabet => [],
            $alphabet->isNucleotide() => $locations['protein'],
            default => $locations['nucleotide'],
        };

        return new AlphabetDetection($alphabet, $first, $counts, $mismatches);
    }

    /**
     * @param array{dna: int, rna: int, protein: int, unknown: int} $counts
     */
    private function majority(array $counts): ?Alphabet
    {
        $nucleotides = $counts['dna'] + $counts['rna'];

        if ($nucleotides === 0 && $counts['protein'] === 0) {
            return null;
        }

        if ($counts['protein'] > $nucleotides) {
            return Alphabet::Protein;
        }

        return $counts['rna'] > $counts['dna'] ? Alphabet::Rna : Alphabet::Dna;
    }
}
