<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\CodingLabel;
use App\Domain\CodingPotential\Cpc2OutputFormatter;
use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\CodingPotential\Cpc2Predictor;
use App\Domain\CodingPotential\Cpc2Translator;
use App\Domain\CodingPotential\CpcOrf;
use App\Domain\CodingPotential\CpcOrfFinder;
use App\Domain\CodingPotential\FickettScore;
use App\Domain\CodingPotential\IsoelectricPoint;
use App\Domain\CodingPotential\LegacyNumberFormat;
use App\Domain\CodingPotential\LibsvmModel;
use App\Domain\CodingPotential\LibsvmPrediction;
use App\Domain\CodingPotential\LibsvmPredictor;
use App\Domain\CodingPotential\LibsvmScaler;
use App\Domain\CodingPotential\OrfIntegrity;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\GeneticCode;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\Cpc2OutputComparison;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Output di CPC2-beta (`CPC2.py -i <fasta> -o <out>`, come lo lancia Annocript) su trial, example e casi
 * limite: identico byte per byte salvo coding_probability, uguale entro 1e-6 (dipende da exp() della libm,
 * {@see Cpc2OutputComparison}); compresi i casi del README dei golden (Transcript_135 di example stampato
 * 0.5 ma coding, la sequenza di una base coding).
 */
#[CoversClass(Cpc2Predictor::class)]
#[CoversClass(Cpc2OutputFormatter::class)]
#[UsesClass(CodingLabel::class)]
#[UsesClass(Cpc2Prediction::class)]
#[UsesClass(Cpc2Translator::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(CpcOrfFinder::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FickettScore::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(IsoelectricPoint::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(LibsvmModel::class)]
#[UsesClass(LibsvmPrediction::class)]
#[UsesClass(LibsvmPredictor::class)]
#[UsesClass(LibsvmScaler::class)]
#[UsesClass(OrfIntegrity::class)]
#[UsesClass(TextFileReader::class)]
#[UsesClass(GzipReader::class)]
final class Cpc2PredictorParityTest extends TestCase
{
    /**
     * @return Generator<string, array{string, string}>
     */
    public static function inputs(): Generator
    {
        foreach (GoldenFixtures::SETS as $set => $fasta) {
            yield $set => [$fasta, \sprintf('golden/%s/cpc2.tsv', $set)];
        }

        yield 'cpc2_cases' => [GoldenFixtures::CPC2_CASES, 'golden/cpc2_cases/cpc2.tsv'];
    }

    #[DataProvider('inputs')]
    public function testTheOutputIsIdenticalToCpc2(string $fasta, string $golden): void
    {
        $files = new TextFileReader();
        $resources = __DIR__ . '/../../resources/cpc2/';
        $predictor = new Cpc2Predictor(LibsvmScaler::parse($files->lines($resources . 'cpc2.range')), new LibsvmPredictor(LibsvmModel::parse($files->lines($resources . 'cpc2.model'))));
        $formatter = new Cpc2OutputFormatter();
        $expected = iterator_to_array(GoldenFixtures::lines($golden), false);
        $comparison = new GoldenComparison();
        self::assertSame(Cpc2OutputFormatter::HEADER, array_shift($expected));
        $lines = [];

        foreach (new FastaReader()->read($files->lines(GoldenFixtures::path($fasta))) as $record) {
            $lines[] = $formatter->line($record->id(), $predictor->predict($record->sequence));
        }

        self::assertCount(\count($expected), $lines, 'one line per sequence');

        foreach ($lines as $index => $line) {
            Cpc2OutputComparison::compare($comparison, $expected[$index], $line);
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
