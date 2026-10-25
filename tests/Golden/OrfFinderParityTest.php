<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\Dna2PepLineFormatter;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\GeneticCode;
use App\Domain\Sequence\Orf;
use App\Domain\Sequence\OrfFinder;
use App\Domain\Sequence\OrfMode;
use App\Domain\Sequence\ReadingFrame;
use App\Domain\Sequence\Translator;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\Dna2PepRow;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * ORF identici a dna2pep 1.1 (`-O tab -o <mode> -r all`) su trial, example e sui casi limite di orf_cases.fasta:
 * frame, peptide, annotazione e DNA, i campi che ne ricava Annocript (lunghezza, strand, frame 0..2) e la riga
 * di dna2pepOut byte per byte. Sequenze scartate da dna2pep (caratteri non accettati) e "NO ORF FOUND" (riga
 * vuota sul frame 1) compresi. Le coordinate, che dna2pep non scrive, devono delimitare il DNA dell'ORF.
 */
#[CoversClass(OrfFinder::class)]
#[CoversClass(Dna2PepLineFormatter::class)]
#[UsesClass(CodonMark::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(Orf::class)]
#[UsesClass(OrfMode::class)]
#[UsesClass(ReadingFrame::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(Translator::class)]
final class OrfFinderParityTest extends TestCase
{
    /**
     * Differenze volute: dna2pep scambia S e W nel complemento (corretto in {@see OrfFinder}).
     */
    private const array ALLOWED = [
        'orf_cases none degenerate_codons' => 'the winning ORF is on the reverse strand of a sequence with S: dna2pep complemented S as W',
    ];

    /**
     * @return Generator<string, array{string, string, OrfMode}>
     */
    public static function inputs(): Generator
    {
        $inputs = ['orf_cases' => ['fasta/orf_cases.fasta', 'golden/orf_cases/dna2pep_%s.tab']];

        foreach (GoldenFixtures::SETS as $set => $fasta) {
            $inputs[$set] = [$fasta, 'golden/' . $set . '/dna2pep_%s.tab'];
        }

        foreach ($inputs as $name => [$fasta, $golden]) {
            foreach (OrfMode::cases() as $mode) {
                yield \sprintf('%s %s', $name, $mode->value) => [$fasta, \sprintf($golden, $mode->value), $mode];
            }
        }
    }

    #[DataProvider('inputs')]
    public function testEveryOrfMatchesDna2Pep(string $fasta, string $golden, OrfMode $mode): void
    {
        $finder = new OrfFinder();
        $formatter = new Dna2PepLineFormatter();
        $output = '';
        $rows = array_map(Dna2PepRow::fromLine(...), iterator_to_array(GoldenFixtures::lines($golden), false));
        $comparison = new GoldenComparison();
        $prefix = \sprintf('%s %s ', basename($fasta, '.fasta') === 'orf_cases' ? 'orf_cases' : 'set', $mode->value);

        foreach (self::ALLOWED as $key => $reason) {
            if (str_starts_with($key, $prefix)) {
                $comparison->allow(substr($key, \strlen($prefix)), $reason);
            }
        }

        foreach (new FastaReader()->read(new TextFileReader()->lines(GoldenFixtures::path($fasta))) as $record) {
            if (!$finder->accepts($record->sequence)) {
                // dna2pep salta la sequenza: nessuna riga.
                continue;
            }

            $row = array_shift($rows);
            self::assertNotNull($row, \sprintf('dna2pep has no line for %s', $record->id()));
            $orf = $finder->find($record->sequence, $mode);
            $id = $record->id();

            $comparison->same($id, 'id', $row->id, $id);
            $comparison->same($id, 'frame', $row->frame, $orf->frame->value ?? ReadingFrame::Plus1->value);
            $comparison->same($id, 'peptide', $row->peptide, $orf->peptide ?? '');
            $comparison->same($id, 'annotation', $row->annotation, $orf->annotation ?? '');
            $comparison->same($id, 'dna', $row->dna, $orf->dna ?? '');
            $comparison->same($id, 'length', $row->length(), $orf?->length() ?? 0);
            $comparison->same($id, 'strand', $row->strand(), $orf?->strand() ?? '+');
            $comparison->same($id, 'legacy frame', $row->legacyFrame(), $orf?->frameOffset() ?? 0);
            $comparison->same($id, 'coordinates', $orf->dna ?? '', $orf instanceof Orf ? $this->dnaAt($record->sequence, $orf) : '');

            $line = $formatter->format($id, $mode, $orf);
            $comparison->same($id, 'line', $row->line, $line);
            $output .= $line . "\n";
        }

        self::assertSame([], $rows, 'every line of dna2pep has its sequence');
        self::assertSame([], $comparison->failures(), $comparison->report());

        if (!$comparison->usedAllowances()) {
            self::assertSame(file_get_contents(GoldenFixtures::path($golden)), $output, 'dna2pepOut is identical byte for byte');
        }
    }

    /**
     * Le basi tra start ed end, nel verso del frame dell'ORF.
     */
    private function dnaAt(string $sequence, Orf $orf): string
    {
        $bases = substr($sequence, $orf->start - 1, $orf->end - $orf->start + 1);

        return $orf->frame->isForward() ? $bases : OrfFinder::reverseComplement($bases);
    }
}
