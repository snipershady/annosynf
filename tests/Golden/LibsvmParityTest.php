<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\LegacyNumberFormat;
use App\Domain\CodingPotential\LibsvmModel;
use App\Domain\CodingPotential\LibsvmPrediction;
use App\Domain\CodingPotential\LibsvmPredictor;
use App\Domain\CodingPotential\LibsvmScaler;
use App\Tests\Support\Golden\Cpc2Row;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use App\Tests\Support\Golden\ScaledVector;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Le due fasi libsvm di CPC2 separatamente, con il modello di resources/cpc2: svm-scale (dalle feature come
 * le scrive CPC2 ai vettori di cpc2_scaled.svm, stampati con %g) e svm-predict -b 1 (dai vettori scalati a
 * probabilità ed etichetta).
 */
#[CoversClass(LibsvmScaler::class)]
#[CoversClass(LibsvmPredictor::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(LibsvmModel::class)]
#[UsesClass(LibsvmPrediction::class)]
final class LibsvmParityTest extends TestCase
{
    private const string RESOURCES = __DIR__ . '/../../resources/cpc2/';

    /**
     * @return Generator<string, array{string}>
     */
    public static function directories(): Generator
    {
        foreach (array_keys(GoldenFixtures::SETS) as $set) {
            yield $set => [$set];
        }

        yield 'cpc2_cases' => ['cpc2_cases'];
    }

    #[DataProvider('directories')]
    public function testScalingMatchesSvmScale(string $directory): void
    {
        $scaler = LibsvmScaler::parse($this->lines(self::RESOURCES . 'cpc2.range'));
        $vectors = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2_scaled.svm', $directory)), false);
        $comparison = new GoldenComparison();

        foreach ($this->predictions($directory) as $index => $row) {
            $scaled = array_map(static fn (float $value): string => LegacyNumberFormat::c($value), $scaler->scale([
                1 => (float) $row->peptideLength,
                2 => $row->fickettScore,
                3 => $row->isoelectricPoint,
                4 => (float) $row->orfIntegrity,
            ]));
            $expected = array_map(static fn (float $value): string => LegacyNumberFormat::c($value), ScaledVector::fromLine($vectors[$index] ?? '')->values);

            // svm-scale non scrive le feature scalate a 0.
            $comparison->same($row->id, 'scaled features', json_encode($expected, \JSON_THROW_ON_ERROR), json_encode(array_filter($scaled, static fn (string $value): bool => $value !== '0'), \JSON_THROW_ON_ERROR));
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    #[DataProvider('directories')]
    public function testProbabilitiesMatchSvmPredict(string $directory): void
    {
        $predictor = new LibsvmPredictor(LibsvmModel::parse($this->lines(self::RESOURCES . 'cpc2.model')));
        $predictions = $this->predictions($directory);
        $comparison = new GoldenComparison();

        foreach (GoldenFixtures::lines(\sprintf('golden/%s/cpc2_scaled.svm', $directory)) as $number => $line) {
            $row = $predictions[$number - 1];
            $prediction = $predictor->predict(ScaledVector::fromLine($line)->values);

            // Tolleranza: la probabilità dipende da exp() della libm (vedi Cpc2OutputComparison).
            $comparison->close($row->id, 'coding_probability', $row->codingProbability, (float) LegacyNumberFormat::c($prediction->probabilityOf(1)));
            $comparison->same($row->id, 'label', $row->label, $prediction->label === 1 ? 'coding' : 'noncoding');
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    /**
     * @return list<Cpc2Row>
     */
    private function predictions(string $directory): array
    {
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2.tsv', $directory)), false);
        array_shift($lines);

        return array_map(Cpc2Row::fromLine(...), $lines);
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
