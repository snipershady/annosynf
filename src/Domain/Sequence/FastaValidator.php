<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Controlla un FASTA prima dell'analisi (CLAUDE.md §B.6, sostituisce check_input_fasta_file e checkFastaFormat).
 * Diversamente dal legacy, che dava solo avvisi, header vuoti o con spazio dopo `>`, nomi duplicati,
 * sequenze vuote e caratteri non IUPAC sono errori bloccanti: renderebbero ambigui o sbagliati i risultati.
 */
final readonly class FastaValidator
{
    /** Lunghezza massima consigliata di header e righe di sequenza (maxFastaSeqLen del legacy). */
    public const int MAX_LINE_LENGTH = 80;

    /** Frazione di N oltre la quale una sequenza nucleotidica merita un avviso. */
    public const float HIGH_N_FRACTION = 0.1;

    public function __construct(
        private float $highNFraction = self::HIGH_N_FRACTION,
    ) {
    }

    /**
     * @param iterable<FastaRecord> $records di norma da {@see FastaReader::read()}
     */
    public function validate(iterable $records, Alphabet $alphabet): FastaValidationReport
    {
        /** @var array<string, array{int, list<FastaLocation>}> $found */
        $found = [];
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var array<string, true> $invalid */
        $invalid = [];
        $allowed = str_split($alphabet->allowedCharacters());
        $sequences = 0;

        $add = static function (FastaIssue $issue, int $lineNumber, string $id) use (&$found): void {
            [$count, $locations] = $found[$issue->value] ?? [0, []];

            if (\count($locations) < FastaValidationReport::MAX_LOCATIONS) {
                $locations[] = new FastaLocation($lineNumber, $id);
            }

            $found[$issue->value] = [$count + 1, $locations];
        };

        try {
            foreach ($records as $record) {
                ++$sequences;
                $id = $record->id();
                $line = $record->lineNumber;

                if ($id === '') {
                    $add(FastaIssue::EmptyHeader, $line, $id);
                } elseif (ctype_space($record->header[0] ?? '')) {
                    $add(FastaIssue::SpaceAfterGreaterThan, $line, $id);
                }

                if ($id !== '' && isset($seen[$id])) {
                    $add(FastaIssue::DuplicateId, $line, $id);
                }

                $seen[$id] = true;

                if ($record->length() === 0) {
                    $add(FastaIssue::EmptySequence, $line, $id);
                }

                $upper = strtoupper($record->sequence);
                $wrong = array_diff(str_split(count_chars($upper, 3)), $allowed);

                if ($wrong !== []) {
                    $add(FastaIssue::InvalidCharacters, $line, $id);
                    $invalid += array_fill_keys($wrong, true);
                }

                if (1 + \strlen($record->header) > self::MAX_LINE_LENGTH) {
                    $add(FastaIssue::LongHeader, $line, $id);
                }

                if ($record->longestLine > self::MAX_LINE_LENGTH) {
                    $add(FastaIssue::LongSequenceLine, $line, $id);
                }

                if ($alphabet->isNucleotide() && $record->length() > 0 && substr_count($upper, 'N') / $record->length() > $this->highNFraction) {
                    $add(FastaIssue::HighNContent, $line, $id);
                }
            }
        } catch (InvalidFastaException $invalidFastaException) {
            $add(FastaIssue::NotFasta, $invalidFastaException->lineNumber, '');
        }

        if ($sequences === 0 && $found === []) {
            $add(FastaIssue::EmptyFile, 0, '');
        }

        return new FastaValidationReport($sequences, $this->summaries($found), $this->sorted(array_keys($invalid)));
    }

    /**
     * @param array<string, array{int, list<FastaLocation>}> $found
     *
     * @return array<string, FastaIssueSummary>
     */
    private function summaries(array $found): array
    {
        $summaries = [];

        foreach (FastaIssue::cases() as $issue) {
            if (isset($found[$issue->value])) {
                [$count, $locations] = $found[$issue->value];
                $summaries[$issue->value] = new FastaIssueSummary($issue, $count, $locations);
            }
        }

        return $summaries;
    }

    /**
     * @param list<int|string> $characters
     *
     * @return list<string>
     */
    private function sorted(array $characters): array
    {
        $characters = array_map(strval(...), $characters);
        sort($characters, \SORT_STRING);

        return $characters;
    }
}
