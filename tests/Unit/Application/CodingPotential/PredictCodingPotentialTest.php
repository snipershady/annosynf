<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\CodingPotential;

use App\Application\CodingPotential\PredictCodingPotential;
use App\Application\Port\CodingPotentialPredictor;
use App\Application\Port\TextLines;
use App\Domain\CodingPotential\CodingLabel;
use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\CodingPotential\OrfIntegrity;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PredictCodingPotential::class)]
#[UsesClass(CodingLabel::class)]
#[UsesClass(Cpc2Prediction::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
#[UsesClass(OrfIntegrity::class)]
final class PredictCodingPotentialTest extends TestCase
{
    public function testEverySequenceIsPredictedInFileOrderUnderItsId(): void
    {
        $source = new readonly class implements TextLines {
            #[Override]
            public function lines(string $path): iterable
            {
                return ['>s1 first', 'ACGT', 'AC', '>s2', 'GGG', '>s1 again', 'T'];
            }
        };
        // Predittore finto: la lunghezza della sequenza ricevuta come transcript_length.
        $predictor = new readonly class implements CodingPotentialPredictor {
            #[Override]
            public function predict(string $sequence): Cpc2Prediction
            {
                return new Cpc2Prediction(\strlen($sequence), null, 0, 0.0, 0.0, OrfIntegrity::Incomplete, 0.0, CodingLabel::Noncoding);
            }
        };

        $lengths = [];

        foreach (new PredictCodingPotential($source, new FastaReader(), $predictor)->predict('in-memory.fasta') as $id => $prediction) {
            $lengths[] = [$id, $prediction->transcriptLength];
        }

        self::assertSame([['s1', 6], ['s2', 3], ['s1', 1]], $lengths, 'a repeated id is predicted again');
    }
}
