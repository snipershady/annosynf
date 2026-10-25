<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Potenziale codificante come CPC2-beta lanciato da Annocript (`CPC2.py -i <fasta> -o <out>`, solo strand +),
 * port nativo di §B.8.2.
 *
 * Feature: lunghezza del peptide dell'ORF più lungo, punteggio di Fickett della sequenza, punto isoelettrico
 * del peptide privato di X, B, Z, J, U e degli stop esterni, integrity dell'ORF (−1 anche senza ORF, con pI
 * 0). CPC2 le passa a svm-scale e svm-predict su file di testo: qui si riproducono gli stessi arrotondamenti,
 * 12 cifre significative (`str()` di Python) prima dello scaling e 6 (`%g`) dopo, perché cambiano la
 * probabilità. L'etichetta è quella più probabile per il modello, decisa sulla probabilità non arrotondata.
 */
final readonly class Cpc2Predictor
{
    /** Etichetta del modello di CPC2 per le sequenze codificanti. */
    public const int CODING_LABEL = 1;

    public function __construct(
        private LibsvmScaler $scaler,
        private LibsvmPredictor $predictor,
        private CpcOrfFinder $orfFinder = new CpcOrfFinder(),
        private Cpc2Translator $translator = new Cpc2Translator(),
        private FickettScore $fickett = new FickettScore(),
        private IsoelectricPoint $isoelectricPoint = new IsoelectricPoint(),
    ) {
    }

    public function predict(string $sequence): Cpc2Prediction
    {
        $orf = $this->orfFinder->find($sequence);
        $peptide = $orf instanceof CpcOrf ? $this->translator->translate($orf->dna) : '';
        $fickett = $this->fickett->of($sequence);
        $cleaned = trim(str_replace(['X', 'B', 'Z', 'J', 'U'], '', $peptide), '*');
        $isoelectricPoint = $cleaned === '' ? 0.0 : $this->isoelectricPoint->of($cleaned);
        $integrity = $orf instanceof CpcOrf && $peptide !== '' ? $orf->integrity : OrfIntegrity::Incomplete;

        $prediction = $this->predictor->predict(array_map(
            static fn (float $value): float => LegacyNumberFormat::roundTrip($value, 6),
            $this->scaler->scale([
                1 => (float) \strlen($peptide),
                2 => LegacyNumberFormat::roundTrip($fickett, 12),
                3 => LegacyNumberFormat::roundTrip($isoelectricPoint, 12),
                4 => (float) $integrity->value,
            ]),
        ));

        return new Cpc2Prediction(
            \strlen(CpcOrfFinder::normalize($sequence)),
            $orf,
            \strlen($peptide),
            $fickett,
            $isoelectricPoint,
            $integrity,
            $prediction->probabilityOf(self::CODING_LABEL),
            $prediction->label === self::CODING_LABEL ? CodingLabel::Coding : CodingLabel::Noncoding,
        );
    }
}
