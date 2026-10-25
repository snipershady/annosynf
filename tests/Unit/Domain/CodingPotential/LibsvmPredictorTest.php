<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\LegacyNumberFormat;
use App\Domain\CodingPotential\LibsvmModel;
use App\Domain\CodingPotential\LibsvmPrediction;
use App\Domain\CodingPotential\LibsvmPredictor;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Attese di svm-predict -b 1 di libsvm 3.18 sul modello minimo di tests/Fixtures/libsvm (etichette in ordine
 * 0 1, un vettore di supporto senza feature): tests/Fixtures/golden/libsvm/tiny.predictions.
 */
#[CoversClass(LibsvmPredictor::class)]
#[CoversClass(LibsvmPrediction::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(LibsvmModel::class)]
final class LibsvmPredictorTest extends TestCase
{
    public function testProbabilitiesAsSvmPredict(): void
    {
        $predictor = new LibsvmPredictor(LibsvmModel::parse(GoldenFixtures::lines('libsvm/tiny.model')));

        self::assertSame([1, '0.418672', '0.581328'], $this->printed($predictor->predict([])), 'an empty vector');
        self::assertSame([1, '0.270146', '0.729854'], $this->printed($predictor->predict([1 => 0.5, 2 => -0.25])));
        self::assertSame([0, '0.760182', '0.239818'], $this->printed($predictor->predict([1 => -0.5, 3 => 1.0])));
        self::assertSame([0, '0.643562', '0.356438'], $this->printed($predictor->predict([1 => 0.1, 2 => 0.2, 3 => 0.3, 4 => 5.0])), 'a feature unknown to the model');
    }

    public function testAProbabilityOfAnUnknownLabelIsZero(): void
    {
        $prediction = new LibsvmPrediction(1, 0.5, [1 => 0.7, 0 => 0.3]);

        self::assertSame(0.7, $prediction->probabilityOf(1));
        self::assertSame(0.0, $prediction->probabilityOf(2));
    }

    /**
     * @return array{int, string, string}
     */
    private function printed(LibsvmPrediction $prediction): array
    {
        return [$prediction->label, LegacyNumberFormat::c($prediction->probabilityOf(0)), LegacyNumberFormat::c($prediction->probabilityOf(1))];
    }
}
