<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\CodingPotential;

use App\Domain\CodingPotential\InvalidLibsvmFileException;
use App\Domain\CodingPotential\LibsvmModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LibsvmModel::class)]
#[CoversClass(InvalidLibsvmFileException::class)]
final class LibsvmModelTest extends TestCase
{
    private const array HEADER = [
        'svm_type c_svc', 'kernel_type rbf', 'gamma 0.5', 'nr_class 2', 'total_sv 2', 'rho 0.2', 'label 0 1',
        'probA 1.5', 'probB -0.3', 'nr_sv 1 1',
    ];

    public function testAModelIsReadAsSvmLoadModel(): void
    {
        $model = LibsvmModel::parse([...self::HEADER, "SV\r", '1.25 1:0.5 3:-0.25 ', '', '-1 ']);

        self::assertSame([0.5, 0.2, [0, 1], 1.5, -0.3], [$model->gamma, $model->rho, $model->labels, $model->probA, $model->probB]);
        self::assertSame([1.25, -1.0], $model->coefficients);
        self::assertSame([[1 => 0.5, 3 => -0.25], []], $model->supportVectors, 'a vector may have no features');
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidModels(): iterable
    {
        $vectors = ['SV', '1 1:0.5', '-1 2:1'];
        $replace = static fn (string $line, ?string $with): array => [...array_values(array_filter(array_map(static fn (string $header): ?string => str_starts_with($header, explode(' ', $line)[0] . ' ') ? $with : $header, self::HEADER), is_string(...))), ...$vectors];

        yield 'repeated header' => [['svm_type c_svc', ...self::HEADER, ...$vectors], 'libsvm model, line 2: unexpected header line "svm_type c_svc".'];
        yield 'blank header line' => [['', ...self::HEADER, ...$vectors], 'unexpected header line ""'];
        yield 'other svm type' => [$replace('svm_type', 'svm_type nu_svc'), 'only svm_type c_svc kernel_type rbf nr_class 2 models are supported'];
        yield 'three classes' => [$replace('nr_class', 'nr_class 3'), 'only svm_type c_svc'];
        yield 'unknown header' => [[...self::HEADER, 'degree 3', ...$vectors], 'unsupported header "degree"'];
        yield 'missing rho' => [$replace('rho', null), '"rho" needs 1 value(s)'];
        yield 'one label' => [$replace('label', 'label 1'), '"label" needs 2 value(s)'];
        yield 'label not an integer' => [$replace('label', 'label 1 x'), '"x" is not an integer'];
        yield 'gamma not a number' => [$replace('gamma', 'gamma fast'), '"gamma" is not a number: "fast"'];
        yield 'too few vectors' => [[...self::HEADER, 'SV', '1 1:0.5'], '1 support vectors found, total_sv 2, nr_sv 2'];
        yield 'no vectors' => [[...self::HEADER], '0 support vectors found'];
        yield 'bad pair' => [[...self::HEADER, 'SV', '1 1:0.5', '-1 0:1'], 'line 13: "0:1" is not an index:value pair'];
        yield 'bad coefficient' => [[...self::HEADER, 'SV', 'one 1:0.5', '-1 2:1'], 'line 12: "one" is not a number'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('invalidModels')]
    public function testInvalidModelsAreRejected(array $lines, string $message): void
    {
        $this->expectException(InvalidLibsvmFileException::class);
        $this->expectExceptionMessageIsOrContains($message);

        LibsvmModel::parse($lines);
    }
}
