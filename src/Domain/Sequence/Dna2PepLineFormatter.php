<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use InvalidArgumentException;

/**
 * Riga dell'output tabellare di dna2pep 1.1 in modalità ORF (`-O tab -o <mode>`), il file dna2pepOut che
 * Annocript rilegge:
 * `<id>_rframe<±f>_ORF<TAB><peptide><TAB><annotazione><TAB>/orf_mode="<mode>"; /dna="<DNA dell'ORF>";`.
 *
 * Senza ORF ("NO ORF FOUND") dna2pep scrive comunque la riga, sul frame 1 e con peptide, annotazione e DNA
 * vuoti; Annocript la legge come un ORF di lunghezza 0 sul filamento `+`, frame 0.
 */
final readonly class Dna2PepLineFormatter
{
    /**
     * @param string $id id della sequenza (primo token dell'header, come dopo cleanHeader)
     *
     * @throws InvalidArgumentException se l'ORF è stato cercato con un'altra modalità
     */
    public function format(string $id, OrfMode $mode, ?Orf $orf): string
    {
        if ($orf instanceof Orf && $orf->mode !== $mode) {
            throw new InvalidArgumentException(\sprintf('The ORF was searched in "%s" mode, not "%s".', $orf->mode->value, $mode->value));
        }

        return \sprintf(
            "%s_rframe%d_ORF\t%s\t%s\t/orf_mode=\"%s\"; /dna=\"%s\";",
            $id,
            ($orf->frame ?? ReadingFrame::Plus1)->value,
            $orf->peptide ?? '',
            $orf->annotation ?? '',
            $mode->value,
            $orf->dna ?? '',
        );
    }
}
