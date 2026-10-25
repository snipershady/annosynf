<?php

declare(strict_types=1);

namespace App\Application\Sequence;

use App\Application\Port\TextLines;
use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\AlphabetDetector;
use App\Domain\Sequence\CompositionSummarizer;
use App\Domain\Sequence\FastaIssue;
use App\Domain\Sequence\FastaIssueSummary;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaValidator;
use App\Domain\Sequence\InvalidFastaException;
use App\Domain\Sequence\SequenceComposition;
use App\Domain\Shared\AnnosynfException;
use Generator;

/**
 * Controlla un FASTA come prima fase di un'analisi (§B.6): rileva l'alfabeto su tutto il file, valida con
 * quell'alfabeto e, per i nucleotidi, calcola la composizione media. Il file si legge in streaming più volte:
 * la memoria non dipende dalla sua dimensione.
 */
final readonly class CheckFasta
{
    public function __construct(
        private TextLines $source,
        private FastaReader $reader,
        private AlphabetDetector $detector,
        private FastaValidator $validator,
        private CompositionSummarizer $summarizer,
    ) {
    }

    /**
     * @throws AnnosynfException se il file non si può leggere
     */
    public function check(string $path): FastaCheck
    {
        $detection = $this->detect($path);
        $alphabet = $detection->alphabet ?? Alphabet::Dna;
        $validation = $this->validator->validate($this->reader->read($this->source->lines($path)), $alphabet);
        $readable = !$validation->summary(FastaIssue::NotFasta) instanceof FastaIssueSummary && $validation->sequences > 0;
        $composition = $readable && $alphabet->isNucleotide() ? $this->summarizer->summarize($this->compositions($path)) : null;

        return new FastaCheck($path, $detection, $alphabet, $validation, $composition);
    }

    private function detect(string $path): ?AlphabetDetection
    {
        try {
            return $this->detector->detect($this->reader->read($this->source->lines($path)));
        } catch (InvalidFastaException) {
            // La validazione riporterà il problema con il numero di riga.
            return null;
        }
    }

    /**
     * @return Generator<int, SequenceComposition>
     */
    private function compositions(string $path): Generator
    {
        foreach ($this->reader->read($this->source->lines($path)) as $record) {
            yield SequenceComposition::of($record->sequence);
        }
    }
}
