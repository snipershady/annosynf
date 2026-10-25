<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\FastaSubset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastaSubset::class)]
#[UsesClass(FastaReader::class)]
#[UsesClass(FastaRecord::class)]
final class FastaSubsetTest extends TestCase
{
    private const array FASTA = ['>a first', 'AC', '>b', 'GT', '>c', 'TT', '>a again', 'GG'];

    public function testRecordsAreSelectedByIdInFileOrder(): void
    {
        self::assertSame(['b', 'c'], $this->ids(new FastaSubset(['c', 'b', 'c', 'missing'])));
        self::assertSame(['a first', 'a again'], array_map(static fn (FastaRecord $record): string => $record->header, $this->select(new FastaSubset(['a']))), 'every record with the id, like faSomeRecords');
    }

    public function testTheListCanBeExcludedInstead(): void
    {
        self::assertSame(['b', 'c'], $this->ids(new FastaSubset(['a'], exclude: true)));
    }

    public function testIdsAreExactAndCaseSensitive(): void
    {
        self::assertSame([], $this->ids(new FastaSubset(['A', 'a first', ''])));
    }

    /**
     * @return list<FastaRecord>
     */
    private function select(FastaSubset $subset): array
    {
        return iterator_to_array($subset->select(new FastaReader()->read(self::FASTA)), false);
    }

    /**
     * @return list<string>
     */
    private function ids(FastaSubset $subset): array
    {
        return array_map(static fn (FastaRecord $record): string => $record->id(), $this->select($subset));
    }
}
