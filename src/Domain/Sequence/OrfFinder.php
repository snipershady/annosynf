<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Cerca l'ORF più lungo di una sequenza nucleotidica nei 6 frame, come dna2pep 1.1 lanciato da Annocript
 * (`-O tab -o <mode> -r all`).
 *
 * Per ogni frame: annotazione dei codoni (leggendo attraverso gli stop), segmenti che iniziano dopo uno stop
 * (`none`) o al primo start (`strict`: M; `any`: M o m) e finiscono al primo `*` successivo o a fine frame.
 * Si tiene il segmento strettamente più lungo, visitando i frame nell'ordine di dna2pep
 * ({@see ReadingFrame::searchOrder()}); il peptide si ritraduce dal DNA dell'ORF con il primo codone come start.
 */
final readonly class OrfFinder
{
    /** Caratteri che dna2pep accetta: con un carattere diverso salta l'intera sequenza. */
    public const string ACCEPTED = 'ATUGCYRSWKMBDHVN';

    /**
     * Complemento IUPAC. Diversamente da dna2pep, che scambiava S e W, S (G/C) e W (A/T) sono complementari di
     * sé stesse.
     */
    private const array COMPLEMENT = [
        'A' => 'T', 'T' => 'A', 'U' => 'A', 'G' => 'C', 'C' => 'G', 'Y' => 'R', 'R' => 'Y', 'S' => 'S', 'W' => 'W',
        'K' => 'M', 'M' => 'K', 'B' => 'V', 'D' => 'H', 'H' => 'D', 'V' => 'B', 'N' => 'N',
    ];

    private Translator $translator;

    public function __construct(?Translator $translator = null)
    {
        $this->translator = $translator ?? new Translator();
    }

    /**
     * La sequenza contiene solo caratteri che dna2pep accetta (maiuscoli o minuscoli).
     */
    public function accepts(string $dna): bool
    {
        return strspn(strtoupper($dna), self::ACCEPTED) === \strlen($dna);
    }

    /**
     * Reverse complement IUPAC che conserva maiuscole e minuscole.
     */
    public static function reverseComplement(string $dna): string
    {
        $complement = self::COMPLEMENT;
        $lower = array_combine(array_map(strtolower(...), array_keys($complement)), array_map(strtolower(...), $complement));

        return strtr(strrev($dna), $complement + $lower);
    }

    /**
     * L'ORF più lungo; null se nessun frame ne contiene uno ("NO ORF FOUND" di dna2pep). La sequenza dovrebbe
     * superare {@see self::accepts()}: dna2pep non elabora le altre.
     */
    public function find(string $dna, OrfMode $mode): ?Orf
    {
        $reverse = self::reverseComplement($dna);
        $best = null;
        $bestLength = -1;

        foreach (ReadingFrame::searchOrder() as $frame) {
            $frameDna = substr($frame->isForward() ? $dna : $reverse, $frame->offset());
            $annotation = $this->translator->annotate($frameDna);

            foreach ($this->segments($annotation, $mode) as [$from, $to]) {
                if ($to - $from > $bestLength) {
                    $bestLength = $to - $from;
                    $best = [$frame, $frameDna, $annotation, $from, $to];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        [$frame, $frameDna, $annotation, $from, $to] = $best;
        $orfDna = substr($frameDna, 3 * $from, 3 * ($to - $from));
        [$start, $end] = $this->forwardCoordinates(\strlen($dna), $frame, $from, $to);

        return new Orf(
            $frame,
            $mode,
            $this->translator->translate($orfDna, firstIsStart: true, readThroughStop: false),
            substr($annotation, $from, $to - $from),
            $orfDna,
            $start,
            $end,
        );
    }

    /**
     * Segmenti candidati di un frame, come intervalli di codoni [inizio, fine) nell'annotazione.
     *
     * @return list<array{int, int}>
     */
    private function segments(string $annotation, OrfMode $mode): array
    {
        $length = \strlen($annotation);
        $segments = [];
        $stop = -1;

        while (true) {
            $start = $mode === OrfMode::None ? $stop + 1 : $this->nextStart($annotation, $stop + 1, $mode);

            if ($start === null || $start >= $length) {
                break;
            }

            $found = strpos($annotation, CodonMark::Stop->value, $start);
            $stop = $found === false ? $length : $found;
            $segments[] = [$start, $stop];
        }

        return $segments;
    }

    private function nextStart(string $annotation, int $from, OrfMode $mode): ?int
    {
        if ($from >= \strlen($annotation)) {
            return null;
        }

        $strict = strpos($annotation, CodonMark::PrimaryStart->value, $from);
        $alternative = $mode === OrfMode::Any ? strpos($annotation, CodonMark::AlternativeStart->value, $from) : false;
        $candidates = array_filter([$strict, $alternative], is_int(...));

        return $candidates === [] ? null : min($candidates);
    }

    /**
     * Prima e ultima base dell'ORF sul filamento diretto, da 1.
     *
     * @return array{int, int}
     */
    private function forwardCoordinates(int $sequenceLength, ReadingFrame $frame, int $from, int $to): array
    {
        $first = $frame->offset() + 3 * $from;
        $last = $frame->offset() + 3 * $to;

        return $frame->isForward() ? [$first + 1, $last] : [$sequenceLength - $last + 1, $sequenceLength - $first];
    }
}
