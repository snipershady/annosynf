<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\CodingPotential\IsoelectricPoint;
use App\Domain\CodingPotential\LegacyNumberFormat;
use App\Tests\Support\Golden\Cpc2Row;
use App\Tests\Support\Golden\CpcOrfRow;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Punto isoelettrico identico a Biopython 1.76: esatto (repr) sui peptidi di isoelectric_cases.tsv, e come
 * lo scrive CPC2 sui peptidi dei suoi ORF (privati di X, B, Z, J, U e degli stop esterni).
 */
#[CoversClass(IsoelectricPoint::class)]
#[UsesClass(LegacyNumberFormat::class)]
final class IsoelectricPointParityTest extends TestCase
{
    public function testEveryPeptideMatchesBiopythonExactly(): void
    {
        $expected = [];

        foreach (GoldenFixtures::lines('golden/cpc2_cases/isoelectric.tsv') as $line) {
            [$name, $repr] = explode("\t", $line);
            $expected[$name] = (float) $repr;
        }

        $comparison = new GoldenComparison();

        foreach (GoldenFixtures::lines('peptides/isoelectric_cases.tsv') as $line) {
            [$name, $peptide] = explode("\t", $line);
            $comparison->same($name, 'pI', var_export($expected[$name] ?? null, true), var_export(new IsoelectricPoint()->of($peptide), true));
        }

        self::assertSame(80, $comparison->comparisons());
        self::assertSame([], $comparison->failures(), $comparison->report());
    }

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
    public function testEveryOrfPeptideMatchesCpc2(string $directory): void
    {
        $lines = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/%s/cpc2.tsv', $directory)), false);
        array_shift($lines);
        $predictions = array_map(Cpc2Row::fromLine(...), $lines);
        $comparison = new GoldenComparison();

        foreach (GoldenFixtures::lines(\sprintf('golden/%s/cpc2_orf.tsv', $directory)) as $index => $line) {
            $orf = CpcOrfRow::fromLine($line);
            $cleaned = trim(str_replace(['X', 'B', 'Z', 'J', 'U'], '', $orf->peptide), '*');

            if ($cleaned !== '') {
                $comparison->same($orf->id, 'pI', LegacyNumberFormat::python($predictions[$index - 1]->isoelectricPoint), LegacyNumberFormat::python(new IsoelectricPoint()->of($cleaned)));
            }
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
