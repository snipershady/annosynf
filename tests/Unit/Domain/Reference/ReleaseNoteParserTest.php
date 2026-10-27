<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\InvalidReleaseNoteException;
use App\Domain\Reference\ReleaseNote;
use App\Domain\Reference\ReleaseNoteParser;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Formati dei file reali di UniProt 2026_03 (copie in tests/Fixtures/reference/release).
 */
#[CoversClass(ReleaseNoteParser::class)]
#[CoversClass(InvalidReleaseNoteException::class)]
#[CoversClass(ReleaseNote::class)]
final class ReleaseNoteParserTest extends TestCase
{
    public function testUniProtKbReldate(): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniProtKb, [
            "UniProt Knowledgebase Release 2026_03 consists of:\n",
            "UniProtKB/Swiss-Prot Release 2026_03 of 02-Sep-2026\n",
            "UniProtKB/TrEMBL Release 2026_03 of 02-Sep-2026\n",
        ]);

        self::assertSame(ReleaseType::UniProtKb, $note->type);
        self::assertSame('2026_03', $note->version);
        self::assertSame('2026-09-02T00:00:00+00:00', $note->releasedOn?->format(\DATE_ATOM));
        self::assertNull($note->unirefVariant);
        self::assertNull($note->clusterCount);
    }

    public function testTheUniProtKbVersionLineCanBeAnywhere(): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniProtKb, ["\r\n", "UniProt Knowledgebase Release 2026_03 consists of:\r\n"]);

        self::assertSame('2026_03', $note->version);
        self::assertNull($note->releasedOn, 'no Swiss-Prot line, no date');
    }

    public function testTheDateComesFromTheSwissProtLineOfTheSameRelease(): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniProtKb, [
            'UniProtKB/Swiss-Prot Release 2026_02 of 01-Aug-2026',
            'UniProt Knowledgebase Release 2026_03 consists of:',
            'UniProtKB/Swiss-Prot Release 2026_03 of 02-Sep-2026',
            'UniProtKB/Swiss-Prot Release 2026_03 of 03-Sep-2026',
        ]);

        self::assertSame('2026-09-02', $note->releasedOn?->format('Y-m-d'));
    }

    public function testUniRefReleaseNote(): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniRef, [
            "                  UniProt Reference Clusters - UniRef90\r\n",
            "\r\n",
            "                      Release: 2026_03, 02-Sep-2026\r\n",
            "                     Number of clusters: 121,494,192\r\n",
            "\r\n",
            " Copyrighted by the UniProt Consortium, see http://www.uniprot.org/terms.\r\n",
        ]);

        self::assertSame(ReleaseType::UniRef, $note->type);
        self::assertSame('2026_03', $note->version);
        self::assertSame('2026-09-02', $note->releasedOn?->format('Y-m-d'));
        self::assertSame(UnirefVariant::UniRef90, $note->unirefVariant);
        self::assertSame(121494192, $note->clusterCount);
    }

    public function testUniRefOptionalFields(): void
    {
        $note = new ReleaseNoteParser()->parse(ReleaseType::UniRef, ['Release: 2026_03', 'Number of clusters: 42', 'Release: 2026_03, 02-Sep-2026, extra']);

        self::assertSame('2026_03', $note->version);
        self::assertSame('2026-09-02', $note->releasedOn?->format('Y-m-d'), 'the first date wins, later fields are ignored');
        self::assertNull($note->unirefVariant);
        self::assertSame(42, $note->clusterCount);
    }

    /**
     * @return iterable<string, array{ReleaseType, list<string>, string}>
     */
    public static function invalidNotes(): iterable
    {
        yield 'empty reldate' => [ReleaseType::UniProtKb, [], 'No release version found in the uniprotkb release note.'];
        yield 'uniref line in reldate' => [ReleaseType::UniProtKb, ['Release: 2026_03, 02-Sep-2026'], 'No release version found in the uniprotkb release note.'];
        yield 'empty release note' => [ReleaseType::UniRef, ['UniProt Reference Clusters - UniRef50'], 'No release version found in the uniref release note.'];
        yield 'version with extra words' => [ReleaseType::UniProtKb, ['UniProt Knowledgebase Release 2026_03 of 2026 consists of:'], 'Malformed version in the uniprotkb release note: "2026_03 of 2026".'];
        yield 'month 13' => [ReleaseType::UniRef, ['Release: 2026_13, 02-Sep-2026'], 'Malformed version in the uniref release note: "2026_13".'];
        yield 'two releases' => [ReleaseType::UniRef, ['Release: 2026_02, 01-Aug-2026', 'Release: 2026_03, 02-Sep-2026'], 'The uniref release note names two releases: 2026_02 and 2026_03.'];
        yield 'two knowledgebase releases' => [ReleaseType::UniProtKb, ['UniProt Knowledgebase Release 2026_03 consists of:', 'UniProt Knowledgebase Release 2026_04 consists of:'], 'The uniprotkb release note names two releases: 2026_03 and 2026_04.'];
        yield 'date out of calendar' => [ReleaseType::UniRef, ['Release: 2026_03, 31-Feb-2026'], 'Malformed date in the uniref release note: "31-Feb-2026".'];
        yield 'date in another format' => [ReleaseType::UniProtKb, ['UniProt Knowledgebase Release 2026_03 consists of:', 'UniProtKB/Swiss-Prot Release 2026_03 of 2026-09-02'], 'Malformed date in the uniprotkb release note: "2026-09-02".'];
        yield 'unknown variant' => [ReleaseType::UniRef, ['UniProt Reference Clusters - UniRef75', 'Release: 2026_03'], 'Malformed UniRef variant in the uniref release note: "UniRef75".'];
        yield 'cluster count' => [ReleaseType::UniRef, ['Release: 2026_03', 'Number of clusters: 1,21,494'], 'Malformed cluster count in the uniref release note: "1,21,494".'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('invalidNotes')]
    public function testInvalidNotes(ReleaseType $type, array $lines, string $message): void
    {
        $this->expectException(InvalidReleaseNoteException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ReleaseNoteParser()->parse($type, $lines);
    }
}
