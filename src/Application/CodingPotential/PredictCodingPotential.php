<?php

declare(strict_types=1);

namespace App\Application\CodingPotential;

use App\Application\Port\CodingPotentialPredictor;
use App\Application\Port\TextLines;
use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\Sequence\FastaReader;
use App\Domain\Shared\AnnosynfException;
use Generator;

/**
 * Potenziale codificante di ogni sequenza di un FASTA, nell'ordine del file e in streaming.
 */
final readonly class PredictCodingPotential
{
    public function __construct(
        private TextLines $source,
        private FastaReader $reader,
        private CodingPotentialPredictor $predictor,
    ) {
    }

    /**
     * @throws AnnosynfException se il file non si può leggere o non è un FASTA
     *
     * @return Generator<string, Cpc2Prediction> id della sequenza (primo token dell'header) → predizione; un id
     *                                           ripetuto nel file compare più volte
     */
    public function predict(string $path): Generator
    {
        foreach ($this->reader->read($this->source->lines($path)) as $record) {
            yield $record->id() => $this->predictor->predict($record->sequence);
        }
    }
}
