<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Tests\Support\Golden\Cpc2Row;
use App\Tests\Support\Golden\CpcOrfRow;
use App\Tests\Support\Golden\Dna2PepRow;
use App\Tests\Support\Golden\GoldenFixtures;
use App\Tests\Support\Golden\ScaledVector;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integrità dei golden prodotti dal legacy (tests/Fixtures/golden/README.md): checksum, una riga per
 * sequenza nell'ordine del FASTA, invarianti dei formati. Se fallisce, i fixture sono stati modificati a mano
 * o rigenerati in modo diverso: i test di parità dei port non sarebbero più affidabili.
 */
#[CoversNothing]
final class GoldenFixturesTest extends TestCase
{
    public function testEveryFileMatchesItsChecksum(): void
    {
        $checksums = GoldenFixtures::checksums();

        foreach (GoldenFixtures::SETS as $set => $fasta) {
            foreach ([$fasta, ...array_map(static fn (string $file): string => \sprintf('golden/%s/%s', $set, $file), ['dna2pep_none.tab', 'dna2pep_strict.tab', 'dna2pep_any.tab', 'cpc2.tsv', 'cpc2_scaled.svm', 'cpc2_orf.tsv', 'atgc_percentages.tsv', 'atgc_percentages.tsv.averages', 'slices_3.tsv', 'slices_10.tsv'])] as $relative) {
                self::assertArrayHasKey($relative, $checksums, 'every golden file is listed in SHA256SUMS');
            }
        }

        foreach (['fasta/format_cases.fasta', 'fasta/example_subset_ids.txt', 'golden/format/bioperl_rewrite.fasta', 'golden/subset/example_subset.fasta', 'golden/genetic_code/table_1.tsv', 'golden/translation/codons.tsv', 'golden/trial/translation/sequences.tsv', 'golden/example/translation/sequences.tsv', 'fasta/orf_cases.fasta', 'golden/orf_cases/dna2pep_none.tab', 'golden/orf_cases/dna2pep_strict.tab', 'golden/orf_cases/dna2pep_any.tab', GoldenFixtures::CPC2_CASES, 'golden/cpc2_cases/cpc2_orf.tsv', 'golden/cpc2_cases/cpc2.tsv', 'golden/cpc2_cases/cpc2_scaled.svm', 'golden/cpc2_cases/codons.tsv', 'golden/reference/release_versions.tsv', 'golden/reference/uniprotkb_headers.tsv', 'golden/reference/uniref_headers.tsv', 'golden/reference/go_go-basic.tsv', 'golden/reference/enzymes_enzyme.tsv', 'golden/reference/pathways_pathway.tsv', 'golden/reference/pathways_pathway.tsv.mapping', 'golden/reference/pathways_pathway_without_proteins.tsv', 'golden/reference/pathways_pathway_without_proteins.tsv.mapping', 'reference/pathway/pathway.txt', 'reference/pathway/pathway_without_proteins.txt', 'golden/reference/enzymes_enzyme.tsv.mapping', 'golden/reference/enzymes_enzyme.tsv.version', 'golden/reference/enzymes_enzyme_last_without_dr.tsv', 'reference/enzyme/enzyme.dat', 'reference/enzyme/enzyme_last_without_dr.dat', 'golden/reference/go_typedef_between_terms.tsv', 'reference/go/go-basic.obo', 'reference/go/typedef_between_terms.obo', 'reference/headers/uniprotkb.headers', 'reference/headers/uniref.headers', 'reference/release/reldate.txt', 'reference/release/uniref90.release_note'] as $relative) {
            self::assertArrayHasKey($relative, $checksums, 'every golden file is listed in SHA256SUMS');
        }

        foreach ($checksums as $relative => $sha256) {
            self::assertSame($sha256, hash_file('sha256', GoldenFixtures::path($relative)), $relative);
        }
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function dna2pepFiles(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            foreach (['none', 'strict', 'any'] as $mode) {
                yield $set . ' ' . $mode => [$set, $mode];
            }
        }
    }

    #[DataProvider('dna2pepFiles')]
    public function testDna2PepHasOneOrfPerSequenceInFastaOrder(string $set, string $mode): void
    {
        $ids = [];

        foreach (GoldenFixtures::lines(\sprintf('golden/%s/dna2pep_%s.tab', $set, $mode)) as $line) {
            $row = Dna2PepRow::fromLine($line);
            $ids[] = $row->id;

            self::assertSame($mode, $row->mode);
            self::assertSame($row->length(), \strlen($row->annotation), $row->id . ': one annotation mark per codon');
            self::assertSame(3 * $row->length(), \strlen($row->dna), $row->id . ': the peptide is the translation of the ORF');
        }

        self::assertSame(GoldenFixtures::sequenceIds($set), $ids);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function sets(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            yield $set => [$set];
        }
    }

    #[DataProvider('sets')]
    public function testCpc2HasOneConsistentPredictionPerSequence(string $set): void
    {
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2.tsv', $set)), false);

        self::assertSame(Cpc2Row::HEADER, array_shift($lines));

        $rows = array_map(Cpc2Row::fromLine(...), $lines);
        self::assertSame(GoldenFixtures::sequenceIds($set), array_map(static fn (Cpc2Row $row): string => $row->id, $rows));

        foreach ($rows as $row) {
            self::assertGreaterThanOrEqual(0.0, $row->codingProbability);
            self::assertLessThanOrEqual(1.0, $row->codingProbability);
            // ">=": svm-predict stampa la probabilità con %g, quindi 0.5000x diventa "0.5" (Transcript_135 di example).
            self::assertSame($row->codingProbability >= 0.5 ? 'coding' : 'noncoding', $row->label, $row->id);

            if ($row->peptideLength === 0) {
                self::assertSame(-1, $row->orfIntegrity, $row->id . ': no peptide, no complete ORF');
                self::assertSame(0.0, $row->isoelectricPoint, $row->id);
            }
        }
    }

    #[DataProvider('sets')]
    public function testScaledVectorsAreTheSvmInputOfEveryPrediction(string $set): void
    {
        $count = 0;

        foreach (GoldenFixtures::lines(\sprintf('golden/%s/cpc2_scaled.svm', $set)) as $line) {
            $vector = ScaledVector::fromLine($line);
            ++$count;

            self::assertSame('999', $vector->label, 'the placeholder label written by CPC2');
            self::assertSame([1, 2, 3, 4], array_keys($vector->values));

            foreach ($vector->values as $value) {
                self::assertGreaterThanOrEqual(-1.0, $value);
                self::assertLessThanOrEqual(1.0, $value);
            }
        }

        self::assertCount($count, GoldenFixtures::sequenceIds($set));
    }

    /**
     * @return Generator<string, array{string, string}>
     */
    public static function cpc2OrfFiles(): Generator
    {
        foreach (GoldenFixtures::SETS as $set => $fasta) {
            yield $set => [$set, $fasta];
        }

        yield 'cpc2_cases' => ['cpc2_cases', GoldenFixtures::CPC2_CASES];
    }

    #[DataProvider('cpc2OrfFiles')]
    public function testTheCpc2OrfIsTheOneBehindTheCpc2Output(string $directory, string $fasta): void
    {
        $orfs = array_map(CpcOrfRow::fromLine(...), iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2_orf.tsv', $directory)), false));
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2.tsv', $directory)), false);
        array_shift($lines);
        $predictions = array_map(Cpc2Row::fromLine(...), $lines);

        self::assertSame(GoldenFixtures::fastaIds($fasta), array_map(static fn (CpcOrfRow $row): string => $row->id, $orfs));
        self::assertCount(\count($orfs), $predictions);

        foreach ($orfs as $index => $orf) {
            self::assertSame(0, \strlen($orf->dna) % 3, $orf->id . ': whole codons');
            self::assertSame(\strlen($orf->dna) / 3, \strlen($orf->peptide), $orf->id . ': one amino acid per codon');
            self::assertSame($orf->dna === '', $orf->integrity === 0, $orf->id . ': integrity 0 only without ORF');
            self::assertSame($predictions[$index]->peptideLength, \strlen($orf->peptide), $orf->id . ': peptide_length of CPC2');
            self::assertSame($orf->integrity === 0 ? -1 : $orf->integrity, $predictions[$index]->orfIntegrity, $orf->id . ': ORF_integrity of CPC2');
        }
    }
}
