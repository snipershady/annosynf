<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Application\CodingPotential\PredictCodingPotential;
use App\Command\Cpc2PredictCommand;
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
use App\Infrastructure\CodingPotential\NativeCpc2Predictor;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\TextFileReader;
use App\Tests\Support\Golden\Cpc2OutputComparison;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

#[CoversClass(Cpc2PredictCommand::class)]
#[UsesClass(CodingLabel::class)]
#[UsesClass(Cpc2OutputFormatter::class)]
#[UsesClass(Cpc2Prediction::class)]
#[UsesClass(Cpc2Predictor::class)]
#[UsesClass(Cpc2Translator::class)]
#[UsesClass(CpcOrf::class)]
#[UsesClass(CpcOrfFinder::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(FickettScore::class)]
#[UsesClass(FileNotReadableException::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(IsoelectricPoint::class)]
#[UsesClass(LegacyNumberFormat::class)]
#[UsesClass(LibsvmModel::class)]
#[UsesClass(LibsvmPrediction::class)]
#[UsesClass(LibsvmPredictor::class)]
#[UsesClass(LibsvmScaler::class)]
#[UsesClass(NativeCpc2Predictor::class)]
#[UsesClass(OrfIntegrity::class)]
#[UsesClass(PredictCodingPotential::class)]
#[UsesClass(TextFileReader::class)]
final class Cpc2PredictCommandTest extends KernelTestCase
{
    public function testTheOutputIsTheOneOfCpc2(): void
    {
        $result = self::runCommand('annosynf:cpc2:predict', ['file' => GoldenFixtures::path(GoldenFixtures::fasta('trial'))], interactive: false);

        self::assertSame(Command::SUCCESS, $result->statusCode);

        $expected = iterator_to_array(GoldenFixtures::lines('golden/trial/cpc2.tsv'), false);
        $actual = explode("\n", $result->getDisplay());
        self::assertSame('', array_pop($actual), 'every line ends with a newline');
        self::assertSame(array_shift($expected), array_shift($actual), 'the header of CPC2');
        self::assertCount(\count($expected), $actual);

        $comparison = new GoldenComparison();

        foreach ($expected as $index => $line) {
            Cpc2OutputComparison::compare($comparison, $line, $actual[$index]);
        }

        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    public function testAnUnreadableFileFails(): void
    {
        $result = self::runCommand('annosynf:cpc2:predict', ['file' => '/nonexistent.fasta'], interactive: false);

        self::assertSame(Command::FAILURE, $result->statusCode);
        self::assertStringContainsString('/nonexistent.fasta', $result->getErrorOutput());
    }
}
