<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\CodonMark;
use App\Domain\Sequence\Dna2PepLineFormatter;
use App\Domain\Sequence\GeneticCode;
use App\Domain\Sequence\Orf;
use App\Domain\Sequence\OrfFinder;
use App\Domain\Sequence\OrfMode;
use App\Domain\Sequence\ReadingFrame;
use App\Domain\Sequence\Translator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Le righe attese sono quelle di dna2pep 1.1 (tests/Fixtures/golden/orf_cases).
 */
#[CoversClass(Dna2PepLineFormatter::class)]
#[UsesClass(CodonMark::class)]
#[UsesClass(GeneticCode::class)]
#[UsesClass(Orf::class)]
#[UsesClass(OrfFinder::class)]
#[UsesClass(OrfMode::class)]
#[UsesClass(ReadingFrame::class)]
#[UsesClass(Translator::class)]
final class Dna2PepLineFormatterTest extends TestCase
{
    public function testForwardOrf(): void
    {
        self::assertSame(
            "strict_forward_rframe1_ORF\tMKP\tM..\t/orf_mode=\"strict\"; /dna=\"ATGAAACCC\";",
            $this->line('strict_forward', 'GCCATGAAACCCTAAGGG', OrfMode::Strict),
        );
    }

    public function testReverseOrfKeepsTheCaseOfTheInput(): void
    {
        self::assertSame(
            "lowercase_rframe-1_ORF\tLRVSW\t.....\t/orf_mode=\"none\"; /dna=\"cttagggtttcatgg\";",
            $this->line('lowercase', 'ccatgaaaccctaag', OrfMode::None),
        );
    }

    public function testWithoutOrfTheLineIsEmptyOnFrameOne(): void
    {
        self::assertSame(
            "no_start_rframe1_ORF\t\t\t/orf_mode=\"strict\"; /dna=\"\";",
            $this->line('no_start', 'GCCGCCTAAGCC', OrfMode::Strict),
        );
    }

    public function testTheOrfMustComeFromTheSameMode(): void
    {
        $orf = new OrfFinder()->find('GCCATGAAACCCTAAGGG', OrfMode::Strict);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The ORF was searched in "strict" mode, not "any".');
        self::assertInstanceOf(Orf::class, $orf);

        new Dna2PepLineFormatter()->format('strict_forward', OrfMode::Any, $orf);
    }

    private function line(string $id, string $dna, OrfMode $mode): string
    {
        return new Dna2PepLineFormatter()->format($id, $mode, new OrfFinder()->find($dna, $mode));
    }
}
