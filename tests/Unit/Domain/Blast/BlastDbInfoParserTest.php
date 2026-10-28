<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Blast;

use App\Domain\Blast\BlastDbInfo;
use App\Domain\Blast\BlastDbInfoParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlastDbInfoParser::class)]
#[CoversClass(BlastDbInfo::class)]
final class BlastDbInfoParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function summaries(): iterable
    {
        yield 'protein' => ["Database: t\n\t2 sequences; 42 total residues\n\nDate: Oct 28, 2026  9:12 PM\tLongest sequence: 27 residues\n", 2, 42];
        yield 'nucleotide with separators' => ["Database: RNAcentral\n\t1,234,567 sequences; 9,876,543,210 total bases\n", 1_234_567, 9_876_543_210];
    }

    #[DataProvider('summaries')]
    public function testTheSummaryLineGivesTheTotals(string $output, int $sequences, int $letters): void
    {
        $info = BlastDbInfoParser::parse($output);

        self::assertSame($sequences, $info->sequences);
        self::assertSame($letters, $info->letters);
        self::assertNull($info->version);
    }

    public function testOutputWithoutSummaryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlastDbInfoParser::parse("BLAST Database error: No alias or index file found\n");
    }
}
