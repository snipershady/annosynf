<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\CodingLabel;
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
use App\Domain\Sequence\GeneticCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Esiti di CPC2-beta sulle stesse sequenze (tests/Fixtures/golden/cpc2_cases/cpc2.tsv), con il modello di
 * resources/cpc2.
 */
#[CoversClass(Cpc2Predictor::class)]
#[CoversClass(Cpc2Prediction::class)]
#[CoversClass(CodingLabel::class)]
#[UsesClass(Cpc2Translator::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(CpcOrfFinder::class)]
#[UsesClass(FickettScore::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(IsoelectricPoint::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(LibsvmModel::class)]
#[UsesClass(LibsvmPrediction::class)]
#[UsesClass(LibsvmPredictor::class)]
#[UsesClass(LibsvmScaler::class)]
#[UsesClass(OrfIntegrity::class)]
final class Cpc2PredictorTest extends TestCase
{
    public function testFeaturesAndProbabilityOfAnOrf(): void
    {
        $prediction = $this->predictor()->predict('GCCATGAAACCCTAAGGG');

        self::assertSame(18, $prediction->transcriptLength);
        self::assertSame('ATGAAACCCTAA', $prediction->orf?->dna);
        self::assertSame(4, $prediction->peptideLength, 'the stop counts');
        self::assertSame('0.26962', LegacyNumberFormat::python($prediction->fickettScore));
        self::assertSame('8.50006103516', LegacyNumberFormat::python($prediction->isoelectricPoint));
        self::assertSame(OrfIntegrity::Complete, $prediction->integrity);
        self::assertSame('0.00443655', LegacyNumberFormat::c($prediction->codingProbability));
        self::assertSame(CodingLabel::Noncoding, $prediction->label);
    }

    public function testAmbiguousResiduesAreLeftOutOfTheIsoelectricPoint(): void
    {
        // Peptide MXXX*: il pI è quello di M.
        self::assertSame('5.27496337891', LegacyNumberFormat::python($this->predictor()->predict('ATGNNNRAAKMSTAG')->isoelectricPoint));
    }

    public function testWithoutOrfTheIntegrityIsIncomplete(): void
    {
        $prediction = $this->predictor()->predict('GCCGCCTAAGCC');

        self::assertNull($prediction->orf);
        self::assertSame([0, 0.0, OrfIntegrity::Incomplete], [$prediction->peptideLength, $prediction->isoelectricPoint, $prediction->integrity]);
        self::assertSame('7.32643e-07', LegacyNumberFormat::c($prediction->codingProbability));
    }

    public function testTheModelCallsASingleBaseCoding(): void
    {
        $prediction = $this->predictor()->predict('A');

        self::assertSame('0.999019', LegacyNumberFormat::c($prediction->codingProbability));
        self::assertSame(CodingLabel::Coding, $prediction->label);
    }

    private function predictor(): Cpc2Predictor
    {
        $resources = __DIR__ . '/../../../../resources/cpc2/';

        return new Cpc2Predictor(
            LibsvmScaler::parse($this->lines($resources . 'cpc2.range')),
            new LibsvmPredictor(LibsvmModel::parse($this->lines($resources . 'cpc2.model'))),
        );
    }

    /**
     * @return list<string>
     */
    private function lines(string $path): array
    {
        $lines = file($path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines, $path);

        return $lines;
    }
}
