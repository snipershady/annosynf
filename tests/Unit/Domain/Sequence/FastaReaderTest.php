<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sequence;

use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaRecord;
use App\Domain\Sequence\InvalidFastaException;
use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FastaReader::class)]
#[CoversClass(InvalidFastaException::class)]
#[CoversClass(FastaRecord::class)]
final class FastaReaderTest extends TestCase
{
    public function testRecordsAreReadInFileOrderWithTheirSequenceJoined(): void
    {
        $records = $this->read([
            '>comp53_c1_seq1 len=582 path=[1:0-581]',
            'GGGAAAAGGA',
            'AACATAG',
            '>comp131_c1_seq1',
            'ACGT',
        ]);

        self::assertCount(2, $records);
        self::assertSame([0, 1], array_keys($records), 'the keys are the ordinals of the records');
        self::assertSame('comp53_c1_seq1', $records[0]->id());
        self::assertSame('len=582 path=[1:0-581]', $records[0]->description());
        self::assertSame('GGGAAAAGGAAACATAG', $records[0]->sequence);
        self::assertSame(17, $records[0]->length());
        self::assertSame(1, $records[0]->lineNumber);
        self::assertSame('comp131_c1_seq1', $records[1]->id());
        self::assertSame('', $records[1]->description());
        self::assertSame(4, $records[1]->lineNumber);
    }

    public function testLineEndingsBlankLinesAndInnerWhitespaceAreIgnored(): void
    {
        $records = $this->read(["\r\n", "   \n", ">seq1  first  read \r\n", "AC GT\tAC\r\n", "\r\n", "gt\n", ">seq2\r", 'NNNN']);

        self::assertCount(2, $records);
        self::assertSame('seq1', $records[0]->id());
        self::assertSame('first  read', $records[0]->description(), 'like BioPerl: the rest of the header, here trimmed');
        self::assertSame('ACGTACgt', $records[0]->sequence, 'the case is kept: validation and alphabet detection decide');
        self::assertSame(3, $records[0]->lineNumber);
        self::assertSame('NNNN', $records[1]->sequence);
    }

    public function testTheRawHeaderIsKeptForValidation(): void
    {
        $records = $this->read(['> spaced id', 'ACGT', '>', 'ACGT', '>empty']);

        self::assertSame(' spaced id', $records[0]->header);
        self::assertSame('spaced', $records[0]->id(), 'like BioPerl: leading spaces are skipped');
        self::assertSame('id', $records[0]->description());
        self::assertSame('', $records[1]->id(), 'an empty header gives an empty id');
        self::assertSame('', $records[2]->sequence, 'a record without sequence lines is still a record');
        self::assertSame(0, $records[2]->length());
    }

    public function testTheLongestSequenceLineIsMeasuredBeforeRemovingWhitespace(): void
    {
        $records = $this->read(['>seq1', str_repeat('A', 60), str_repeat('C', 79) . ' ', 'GT', '>seq2']);

        self::assertSame(80, $records[0]->longestLine);
        self::assertSame(0, $records[1]->longestLine);
    }

    public function testAGreaterThanSignInsideASequenceLineIsLeftForTheValidator(): void
    {
        self::assertSame('AC>GT', $this->read(['>seq1', 'AC>GT'])[0]->sequence);
    }

    public function testNoInputMeansNoRecords(): void
    {
        self::assertSame([], $this->read([]));
        self::assertSame([], $this->read(['', "\r\n", '  ']));
    }

    public function testTextBeforeTheFirstHeaderIsNotFasta(): void
    {
        $this->expectException(InvalidFastaException::class);
        $this->expectExceptionMessageIsOrContains('Line 2 comes before the first ">" header: this is not a FASTA file.');

        $this->read(['', 'ACGT', '>seq1', 'ACGT']);
    }

    public function testRecordsAreStreamedOneAtATime(): void
    {
        $consumed = new ArrayObject();
        $lines = (static function () use ($consumed): iterable {
            foreach (['>seq1', 'AC', '>seq2', 'GT', '>seq3', 'TT'] as $line) {
                $consumed->append($line);

                yield $line;
            }
        })();

        $first = new FastaReader()->read($lines)->current();

        self::assertSame('seq1', $first->id());
        self::assertCount(3, $consumed, 'a record is complete as soon as the next header arrives');
    }

    /**
     * @param iterable<string> $lines
     *
     * @return array<int, FastaRecord>
     */
    private function read(iterable $lines): array
    {
        return iterator_to_array(new FastaReader()->read($lines));
    }
}
