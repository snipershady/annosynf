<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Output di CPC2.py (il file lncRNAPredOut di Annocript), byte per byte: intestazione e una riga TAB per
 * sequenza. Lunghezze e integrity interi, Fickett e pI con `str()` di Python 2 (il Fickett sotto le 2 basi è
 * l'intero 0), probabilità con il `%g` di svm-predict.
 */
final readonly class Cpc2OutputFormatter
{
    public const string HEADER = "#ID\ttranscript_length\tpeptide_length\tFickett_score\tpI\tORF_integrity\tcoding_probability\tlabel";

    public function line(string $id, Cpc2Prediction $prediction): string
    {
        return implode("\t", [
            $id,
            (string) $prediction->transcriptLength,
            (string) $prediction->peptideLength,
            $prediction->transcriptLength < 2 ? '0' : LegacyNumberFormat::python($prediction->fickettScore),
            LegacyNumberFormat::python($prediction->isoelectricPoint),
            (string) $prediction->integrity->value,
            LegacyNumberFormat::c($prediction->codingProbability),
            $prediction->label->value,
        ]);
    }
}
