<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Cerca l'ORF più lungo come FindCDS di CPC2-beta (derivato da CPAT 1.2.1), lanciato da Annocript senza `-r`:
 * solo sul filamento diretto, nei frame 0, 1, 2.
 *
 * In ogni frame un ORF parte da un ATG e si estende a codoni finché incontra TAA, TAG o TGA (compreso
 * nell'ORF, integrity 1) o finisce la sequenza (fino all'ultimo codone completo, integrity −1). La ricerca
 * riprende dopo la fine dell'ORF, quindi un ATG interno non apre un altro ORF. Vince il più lungo; a parità di
 * lunghezza quello che inizia prima nella sequenza, in qualunque frame.
 */
final readonly class CpcOrfFinder
{
    private const string START = 'ATG';

    private const array STOPS = ['TAA' => true, 'TAG' => true, 'TGA' => true];

    /**
     * Sequenza come la prepara CPC2: spazi esterni tolti, maiuscole, U → T.
     */
    public static function normalize(string $sequence): string
    {
        return str_replace('U', 'T', strtoupper(trim($sequence)));
    }

    /**
     * L'ORF più lungo; null se nessun frame contiene un ATG.
     */
    public function find(string $sequence): ?CpcOrf
    {
        $dna = self::normalize($sequence);
        $best = null;

        foreach ([0, 1, 2] as $frame) {
            foreach ($this->frameOrfs($dna, $frame) as [$start, $end, $integrity]) {
                if ($best === null || $end - $start > $best[1] - $best[0] || ($end - $start === $best[1] - $best[0] && $start < $best[0])) {
                    $best = [$start, $end, $integrity];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        [$start, $end, $integrity] = $best;

        return new CpcOrf(substr($dna, $start, $end - $start), $start + 1, $integrity);
    }

    /**
     * ORF di un frame come intervalli [inizio, fine) sulla sequenza.
     *
     * @return list<array{int, int, OrfIntegrity}>
     */
    private function frameOrfs(string $dna, int $frame): array
    {
        $length = \strlen($dna);
        $orfs = [];

        for ($position = $frame; $position + 3 <= $length; $position += 3) {
            if (substr($dna, $position, 3) !== self::START) {
                continue;
            }

            $start = $position;

            do {
                $position += 3;
            } while ($position + 3 <= $length && !isset(self::STOPS[substr($dna, $position, 3)]));

            if ($position + 3 > $length) {
                // Nessuno stop: l'ORF finisce con l'ultimo codone completo.
                $orfs[] = [$start, $position, OrfIntegrity::Incomplete];

                break;
            }

            $orfs[] = [$start, $position + 3, OrfIntegrity::Complete];
        }

        return $orfs;
    }
}
