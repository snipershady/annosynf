<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Sequence\CheckFasta;
use App\Application\Sequence\FastaCheck;
use App\Domain\Sequence\Alphabet;
use App\Domain\Sequence\AlphabetDetection;
use App\Domain\Sequence\CompositionSummary;
use App\Domain\Sequence\FastaIssueSummary;
use App\Domain\Sequence\FastaLocation;
use App\Domain\Shared\AnnosynfException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Controlla un FASTA prima di un'analisi: struttura, nomi, caratteri, alfabeto e composizione (ex
 * check_input_fasta_file di Annocript, CLAUDE.md §B.6).
 */
#[AsCommand(
    name: 'annosynf:fasta:check',
    description: 'Checks a FASTA file (plain or gzip) before an analysis: blocking errors, warnings, alphabet and base composition',
)]
final readonly class FastaCheckCommand
{
    /** Posizioni mostrate per ogni problema (il report ne conserva di più). */
    private const int SHOWN_LOCATIONS = 5;

    public function __construct(
        private CheckFasta $checker,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path of the FASTA file, plain or compressed with gzip')]
        string $file,
    ): int {
        try {
            $check = $this->checker->check($file);
        } catch (AnnosynfException $annosynfException) {
            $io->error($annosynfException->getMessage());

            return Command::FAILURE;
        }

        $io->title(\sprintf('FASTA check: %s', $file));
        $this->summary($io, $check);
        $this->issues($io, 'Blocking errors', $check->validation->errors());
        $this->issues($io, 'Warnings', $check->validation->warnings());

        if ($check->validation->invalidCharacters !== []) {
            $io->text(\sprintf('Characters that are not IUPAC codes for %s: <comment>%s</comment>', $check->alphabet->value, implode(' ', $check->validation->invalidCharacters)));
        }

        if (!$check->isValid()) {
            $io->error(\sprintf('The file cannot be analysed: fix the %d blocking problem(s) listed above.', \count($check->validation->errors())));

            return Command::FAILURE;
        }

        $io->success($check->validation->warnings() === [] ? 'The file can be analysed.' : 'The file can be analysed; check the warnings above.');

        return Command::SUCCESS;
    }

    private function summary(SymfonyStyle $io, FastaCheck $check): void
    {
        $rows = [
            ['Sequences', (string) $check->validation->sequences],
            ['Alphabet', $this->alphabet($check)],
        ];

        if ($check->composition instanceof CompositionSummary) {
            $rows = [...$rows, ...$this->composition($check->composition)];
        }

        $this->table($io, [], $rows);

        if ($check->detection instanceof AlphabetDetection && $check->detection->isMixed()) {
            $io->warning(\sprintf(
                'The file mixes nucleotide and protein sequences (%d DNA, %d RNA, %d protein). First sequences unlike the rest: %s',
                $check->detection->counts['dna'],
                $check->detection->counts['rna'],
                $check->detection->counts['protein'],
                $this->locations($check->detection->mismatches, \count($check->detection->mismatches)),
            ));
        }
    }

    private function alphabet(FastaCheck $check): string
    {
        $detection = $check->detection;

        if (!$detection instanceof AlphabetDetection || !$detection->alphabet instanceof Alphabet) {
            return \sprintf('%s (not detectable, assumed)', $check->alphabet->value);
        }

        if ($detection->firstSequenceAlphabet instanceof Alphabet && $detection->firstSequenceAlphabet !== $detection->alphabet) {
            return \sprintf('%s (Annocript, which looked only at the first sequence, would have used %s)', $detection->alphabet->value, $detection->firstSequenceAlphabet->value);
        }

        return $detection->alphabet->value;
    }

    /**
     * @return list<list<string>> righe "etichetta, valore"
     */
    private function composition(CompositionSummary $composition): array
    {
        $percentage = static fn (?float $value): string => $value === null ? '–' : \sprintf('%.2f%%', $value);

        return [
            ['Bases', (string) $composition->bases],
            ['Mean length', $composition->sequences === 0 ? '–' : \sprintf('%.1f', $composition->bases / $composition->sequences)],
            ['Average A / C / G / T / N', implode(' / ', array_map($percentage, [$composition->averageAdenine, $composition->averageCytosine, $composition->averageGuanine, $composition->averageThymine, $composition->averageN]))],
            ['Average GC', $percentage($composition->averageGc)],
            ['Overall GC', $percentage($composition->overallGc)],
        ];
    }

    /**
     * @param list<FastaIssueSummary> $summaries
     */
    private function issues(SymfonyStyle $io, string $title, array $summaries): void
    {
        if ($summaries === []) {
            return;
        }

        $io->section($title);
        $this->table($io, ['Problem', 'Count', 'Where'], array_map(
            fn (FastaIssueSummary $summary): array => [$summary->issue->description(), (string) $summary->count, $this->locations($summary->locations, $summary->count)],
            $summaries,
        ));
    }

    /**
     * Tabella nello stile di SymfonyStyle, scritta attraverso l'oggetto di stile: SymfonyStyle::table() apre una
     * "console section", che l'output dei test dei comandi (TestOutput di Symfony 8.1) non supporta.
     *
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    private function table(SymfonyStyle $io, array $headers, array $rows): void
    {
        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');
        new Table($io)->setStyle($style)->setHeaders($headers)->setRows($rows)->render();
        $io->newLine();
    }

    /**
     * @param list<FastaLocation> $locations
     */
    private function locations(array $locations, int $total): string
    {
        $shown = array_map(
            static fn (FastaLocation $location): string => $location->id === '' ? \sprintf('line %d', $location->lineNumber) : \sprintf('line %d (%s)', $location->lineNumber, $location->id),
            \array_slice($locations, 0, self::SHOWN_LOCATIONS),
        );

        return implode(', ', $shown) . ($total > \count($shown) ? \sprintf(' and %d more', $total - \count($shown)) : '');
    }
}
