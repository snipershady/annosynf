<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\CodingPotential;

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
use App\Infrastructure\CodingPotential\NativeCpc2Predictor;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\GzipReader;
use App\Infrastructure\Storage\TextFileReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(NativeCpc2Predictor::class)]
#[UsesClass(CodingLabel::class)]
#[UsesClass(Cpc2Prediction::class)]
#[UsesClass(Cpc2Predictor::class)]
#[UsesClass(Cpc2Translator::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(CpcOrfFinder::class)]
#[UsesClass(FickettScore::class)]
#[UsesClass(FileNotReadableException::class)]
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
final class NativeCpc2PredictorTest extends KernelTestCase
{
    public function testTheContainerPredictsWithTheVersionedModel(): void
    {
        $predictor = self::getContainer()->get(NativeCpc2Predictor::class);

        // Esiti di CPC2-beta (tests/Fixtures/golden/cpc2_cases/cpc2.tsv); la seconda predizione riusa il modello.
        self::assertSame('0.00443655', LegacyNumberFormat::c($predictor->predict('GCCATGAAACCCTAAGGG')->codingProbability));
        self::assertSame(CodingLabel::Coding, $predictor->predict('A')->label);
    }

    public function testAMissingModelIsReported(): void
    {
        $this->expectException(FileNotReadableException::class);

        new NativeCpc2Predictor(new TextFileReader(), '/nonexistent/cpc2')->predict('ACGT');
    }
}
