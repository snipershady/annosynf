<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\Cpc2Translator;
use App\Domain\Sequence\GeneticCode;
use App\Tests\Support\Golden\CodonRow;
use App\Tests\Support\Golden\CpcOrfRow;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Traduzione identica a `mRNA_translate` di CPC2 (Biopython 1.76): tutti i codoni su ACGTRYMKWSBDHVNX- e i
 * peptidi degli ORF di CPC2 su trial, example e casi limite, la cui lunghezza è la feature peptide_length.
 */
#[CoversClass(Cpc2Translator::class)]
#[UsesClass(GeneticCode::class)]
final class Cpc2TranslatorParityTest extends TestCase
{
    public function testEveryCodonMatchesBiopython(): void
    {
        $translator = new Cpc2Translator();
        $comparison = new GoldenComparison();
        $errors = 0;

        foreach (GoldenFixtures::lines('golden/cpc2_cases/codons.tsv') as $line) {
            $row = CodonRow::fromLine($line);
            $actual = $translator->translate($row->codon);

            if ($row->aminoAcid === CodonRow::ERROR) {
                // Differenza voluta: Biopython solleva TranslationError (CPC2 si ferma), il port scrive X.
                ++$errors;
                $comparison->same($row->codon, 'untranslatable codon', 'X', $actual);

                continue;
            }

            $comparison->same($row->codon, 'amino acid', $row->aminoAcid, $actual);
        }

        self::assertSame(17 ** 3, $comparison->comparisons());
        self::assertSame(1170, $errors, 'every codon with "-" and those with X that could be a stop');
        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function orfFiles(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            yield $set => ['golden/' . $set . '/cpc2_orf.tsv'];
        }

        yield 'cpc2_cases' => ['golden/cpc2_cases/cpc2_orf.tsv'];
    }

    #[DataProvider('orfFiles')]
    public function testEveryOrfPeptideMatchesBiopython(string $golden): void
    {
        $translator = new Cpc2Translator();
        $comparison = new GoldenComparison();

        foreach (GoldenFixtures::lines($golden) as $line) {
            $row = CpcOrfRow::fromLine($line);
            $comparison->same($row->id, 'peptide', $row->peptide, $translator->translate($row->dna));
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
