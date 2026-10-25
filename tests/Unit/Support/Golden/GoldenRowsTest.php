<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Golden;

use App\Tests\Support\Golden\AtgcRow;
use App\Tests\Support\Golden\Cpc2Row;
use App\Tests\Support\Golden\Dna2PepRow;
use App\Tests\Support\Golden\ScaledVector;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[CoversNothing]
final class GoldenRowsTest extends TestCase
{
    public function testADna2PepLineIsReadAsAnnocriptReadsIt(): void
    {
        $row = Dna2PepRow::fromLine("comp53_c1_seq1_rframe-3_ORF\tMKV\tM..\t/orf_mode=\"strict\"; /dna=\"ATGAAAGTT\";");

        self::assertSame('comp53_c1_seq1', $row->id);
        self::assertSame(-3, $row->frame);
        self::assertSame('-', $row->strand());
        self::assertSame(2, $row->legacyFrame());
        self::assertSame(3, $row->length());
        self::assertSame('M..', $row->annotation);
        self::assertSame('strict', $row->mode);
        self::assertSame('ATGAAAGTT', $row->dna);
        self::assertSame("comp53_c1_seq1_rframe-3_ORF\tMKV\tM..\t/orf_mode=\"strict\"; /dna=\"ATGAAAGTT\";", $row->line);

        $forward = Dna2PepRow::fromLine("id_with_rframe1_ORF_rframe1_ORF\tM\tM\t/orf_mode=\"none\"; /dna=\"ATG\";");
        self::assertSame('id_with_rframe1_ORF', $forward->id, 'only the last suffix is the frame');
        self::assertSame('+', $forward->strand());
        self::assertSame(0, $forward->legacyFrame());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDna2PepLines(): iterable
    {
        yield 'three fields' => ["id_rframe1_ORF\tM\tM"];
        yield 'no frame suffix' => ["id\tM\tM\t/orf_mode=\"none\"; /dna=\"ATG\";"];
        yield 'frame out of range' => ["id_rframe4_ORF\tM\tM\t/orf_mode=\"none\"; /dna=\"ATG\";"];
        yield 'unknown mode' => ["id_rframe1_ORF\tM\tM\t/orf_mode=\"loose\"; /dna=\"ATG\";"];
    }

    #[DataProvider('malformedDna2PepLines')]
    public function testMalformedDna2PepLinesAreRejected(string $line): void
    {
        $this->expectException(UnexpectedValueException::class);

        Dna2PepRow::fromLine($line);
    }

    public function testACpc2LineKeepsEveryColumn(): void
    {
        $row = Cpc2Row::fromLine("comp53_c1_seq1\t582\t43\t0.34424\t5.95758056641\t-1\t1.2e-05\tnoncoding");

        self::assertSame('comp53_c1_seq1', $row->id);
        self::assertSame(582, $row->transcriptLength);
        self::assertSame(43, $row->peptideLength);
        self::assertSame(0.34424, $row->fickettScore);
        self::assertSame(5.95758056641, $row->isoelectricPoint);
        self::assertSame(-1, $row->orfIntegrity);
        self::assertSame(1.2e-05, $row->codingProbability);
        self::assertSame('noncoding', $row->label);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedCpc2Lines(): iterable
    {
        yield 'header' => [Cpc2Row::HEADER];
        yield 'seven fields' => ["id\t1\t1\t0.1\t5\t1\t0.5"];
        yield 'bad integrity' => ["id\t1\t1\t0.1\t5\t0\t0.5\tcoding"];
        yield 'bad label' => ["id\t1\t1\t0.1\t5\t1\t0.5\tmaybe"];
        yield 'bad probability' => ["id\t1\t1\t0.1\t5\t1\thigh\tcoding"];
    }

    #[DataProvider('malformedCpc2Lines')]
    public function testMalformedCpc2LinesAreRejected(string $line): void
    {
        $this->expectException(UnexpectedValueException::class);

        Cpc2Row::fromLine($line);
    }

    public function testAScaledVectorKeepsTheFeatureIndexes(): void
    {
        $vector = ScaledVector::fromLine('999 1:-0.994072 2:-0.16389 4:1 ');

        self::assertSame('999', $vector->label);
        self::assertSame([1 => -0.994072, 2 => -0.16389, 4 => 1.0], $vector->values, 'svm-scale omits the features scaled to 0');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedVectors(): iterable
    {
        yield 'empty' => ['  '];
        yield 'not index:value' => ['999 1=0.5'];
    }

    #[DataProvider('malformedVectors')]
    public function testMalformedVectorsAreRejected(string $line): void
    {
        $this->expectException(UnexpectedValueException::class);

        ScaledVector::fromLine($line);
    }

    public function testAnAtgcLineKeepsEveryColumn(): void
    {
        $row = AtgcRow::fromLine("comp53_c1_seq1\t582\t30.9278350515464\t21.4776632302405\t18.5567010309278\t29.0378006872852\t0\t40.0343642611684\t0");

        self::assertSame('comp53_c1_seq1', $row->id);
        self::assertSame(582, $row->length);
        self::assertSame(30.9278350515464, $row->adenine);
        self::assertSame(21.4776632302405, $row->cytosine);
        self::assertSame(18.5567010309278, $row->guanine);
        self::assertSame(29.0378006872852, $row->thymine);
        self::assertSame(0.0, $row->n);
        self::assertSame(40.0343642611684, $row->gcContent);
        self::assertSame(0, $row->errors);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAtgcLines(): iterable
    {
        yield 'header' => [AtgcRow::HEADER];
        yield 'eight fields' => ["id\t4\t25\t25\t25\t25\t0\t50"];
        yield 'bad percentage' => ["id\t4\t25\tx\t25\t25\t0\t50\t0"];
    }

    #[DataProvider('malformedAtgcLines')]
    public function testMalformedAtgcLinesAreRejected(string $line): void
    {
        $this->expectException(UnexpectedValueException::class);

        AtgcRow::fromLine($line);
    }
}
