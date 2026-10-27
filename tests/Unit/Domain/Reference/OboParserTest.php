<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\GoNamespace;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\InvalidOboException;
use App\Domain\Reference\OboParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OboParser::class)]
#[CoversClass(GoTerm::class)]
#[CoversClass(GoNamespace::class)]
#[CoversClass(InvalidOboException::class)]
final class OboParserTest extends TestCase
{
    public function testTermsWithTheirAlternativeIdentifiers(): void
    {
        $terms = new OboParser()->terms([
            "format-version: 1.2\r\n",
            "[Term]\r\n",
            "id: GO:0000124\r\n",
            "name: SAGA complex\r\n",
            "namespace: cellular_component\r\n",
            "alt_id: GO:0000125\r\n",
            "alt_id: GO:0030914\r\n",
            "def: \"A SAGA-type histone acetyltransferase complex.\" [GOC:mah]\r\n",
            "\r\n",
            "[Term]\n",
            "! a comment line\n",
            "id: GO:0000005\n",
            "name: obsolete ribosomal chaperone activity\n",
            "namespace: molecular_function\n",
            "is_obsolete: true\n",
        ]);

        $terms = iterator_to_array($terms);

        self::assertSame([2, 10], array_keys($terms), 'keyed by the line of the [Term] stanza');
        self::assertSame('GO:0000124', $terms[2]->goId);
        self::assertSame('SAGA complex', $terms[2]->name);
        self::assertSame(GoNamespace::CellularComponent, $terms[2]->namespace);
        self::assertSame(['GO:0000125', 'GO:0030914'], $terms[2]->altIds);
        self::assertFalse($terms[2]->isObsolete);
        self::assertTrue($terms[10]->isObsolete, 'the last stanza is not lost');
        self::assertSame(GoNamespace::MolecularFunction, $terms[10]->namespace);
    }

    public function testOtherStanzasAreIgnored(): void
    {
        $terms = new OboParser()->terms([
            '[Typedef]', 'id: part_of', 'name: part of', 'namespace: external',
            '[Term]', 'id: GO:0000001', 'name: mitochondrion inheritance', 'namespace: biological_process', 'is_obsolete: false',
            '[Instance]', 'id: something',
        ]);

        $terms = iterator_to_array($terms);

        self::assertSame([5], array_keys($terms));
        self::assertSame(['GO:0000001', 'mitochondrion inheritance', GoNamespace::BiologicalProcess, false, []], [$terms[5]->goId, $terms[5]->name, $terms[5]->namespace, $terms[5]->isObsolete, $terms[5]->altIds]);
    }

    public function testTheValueIsTheWholeTextAfterTheTag(): void
    {
        $terms = iterator_to_array(new OboParser()->terms(['[Term]', 'id: GO:0090430', 'name: caffeoyl-CoA: alcohol caffeoyl transferase activity  ', 'namespace: molecular_function']), false);

        self::assertSame('caffeoyl-CoA: alcohol caffeoyl transferase activity', $terms[0]->name);
    }

    public function testDataVersion(): void
    {
        $parser = new OboParser();

        self::assertSame('releases/2026-07-26', $parser->dataVersion(["format-version: 1.2\n", "data-version: releases/2026-07-26\r\n", '[Term]']));
        self::assertNull($parser->dataVersion(['format-version: 1.2', '[Term]', 'data-version: releases/2026-07-26']), 'only the header counts');
        self::assertNull($parser->dataVersion(['data-version:']));
        self::assertNull($parser->dataVersion([]));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'no id' => [['[Term]', 'name: a', 'namespace: biological_process'], 'OBO line 1: the term has no "id".'];
        yield 'no name' => [['[Term]', 'id: GO:0000001', 'namespace: biological_process'], 'OBO line 1: the term has no "name".'];
        yield 'no namespace' => [['', '[Term]', 'id: GO:0000001', 'name: a'], 'OBO line 2: the term has no "namespace".'];
        yield 'repeated id' => [['[Term]', 'id: GO:0000001', 'id: GO:0000002', 'name: a', 'namespace: biological_process'], 'OBO line 3: repeated "id" in the term at line 1.'];
        yield 'unknown namespace' => [['[Term]', 'id: GO:0000001', 'name: a', 'namespace: external'], 'OBO line 4: unknown GO namespace "external".'];
        yield 'malformed id' => [['[Term]', 'id: part_of', 'name: a', 'namespace: biological_process'], 'OBO line 2: malformed GO identifier in "id": "part_of".'];
        yield 'malformed alt_id' => [['[Term]', 'id: GO:0000001', 'alt_id: GO:123', 'name: a', 'namespace: biological_process'], 'OBO line 3: malformed GO identifier in "alt_id": "GO:123".'];
        yield 'obsolete flag' => [['[Term]', 'id: GO:0000001', 'name: a', 'namespace: biological_process', 'is_obsolete: yes'], 'OBO line 5: is_obsolete must be "true" or "false", found "yes".'];
        yield 'line without tag' => [['[Term]', 'id: GO:0000001', 'just text'], 'OBO line 3: expected "tag: value", found "just text".'];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('invalidFiles')]
    public function testInvalidFiles(array $lines, string $message): void
    {
        $this->expectException(InvalidOboException::class);
        $this->expectExceptionMessageIsOrContains($message);

        iterator_to_array(new OboParser()->terms($lines));
    }
}
