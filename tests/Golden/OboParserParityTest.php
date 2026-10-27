<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\GoNamespace;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\OboParser;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Termini GO identici alla goTable di createGOTable (golden/reference/go_*.tsv: id progressivo, GO id, nome,
 * namespace; un termine seguito dai suoi alt_id) sugli OBO di tests/Fixtures/reference/go, salvo le correzioni
 * dichiarate di §A.12.3.
 */
#[CoversClass(OboParser::class)]
#[UsesClass(GoTerm::class)]
#[UsesClass(GoNamespace::class)]
final class OboParserParityTest extends TestCase
{
    /**
     * OBO → righe della goTable che il port scrive diversamente (id progressivo → riga), comprese quelle che il
     * legacy non scrive.
     */
    private const array CORRECTIONS = [
        'go-basic' => [
            // il legacy tronca il nome al secondo ": "
            19 => ['GO:0090430', 'caffeoyl-CoA: alcohol caffeoyl transferase activity', 'F'],
            20 => ['GO:0102593', 'obsolete UDP-glucose: N-methylanthranilate glucosyltransferase activity', 'F'],
            // l'ultima stanza [Term], seguita solo da [Typedef]: il legacy la perde
            21 => ['GO:7770088', 'endoplasmic reticulum-endolysosomal membrane contact site', 'C'],
        ],
        'typedef_between_terms' => [
            // il [Typedef] sovrascrive id e nome del termine aperto
            1 => ['GO:0000001', 'mitochondrion inheritance', 'P'],
            2 => ['GO:0000999', 'mitochondrion inheritance', 'P'],
            // l'ultima stanza [Term]
            4 => ['GO:0000005', 'obsolete ribosomal chaperone activity', 'F'],
        ],
    ];

    /**
     * @return Generator<string, array{string}>
     */
    public static function oboFiles(): Generator
    {
        foreach (array_keys(self::CORRECTIONS) as $obo) {
            yield $obo => [$obo];
        }
    }

    #[DataProvider('oboFiles')]
    public function testTheGoTableMatchesAnnocript(string $obo): void
    {
        $expected = [];

        foreach (GoldenFixtures::lines(\sprintf('golden/reference/go_%s.tsv', $obo)) as $line) {
            $fields = explode("\t", $line);
            $expected[(int) array_shift($fields)] = $fields;
        }

        foreach (self::CORRECTIONS[$obo] as $id => $row) {
            self::assertNotSame($expected[$id] ?? null, $row, 'a declared correction must differ from the legacy');
            $expected[$id] = $row;
        }

        $actual = [];

        foreach (new OboParser()->terms(GoldenFixtures::lines(\sprintf('reference/go/%s.obo', $obo))) as $term) {
            foreach ([$term->goId, ...$term->altIds] as $goId) {
                $actual[] = implode("\t", [$goId, $term->name, $term->namespace->value]);
            }
        }

        $comparison = new GoldenComparison();

        foreach ($expected as $id => $row) {
            $comparison->same((string) $id, 'row', implode("\t", $row), $actual[$id - 1] ?? '(missing)');
        }

        self::assertCount(\count($expected), $actual);
        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    public function testObsoleteTermsAndDataVersion(): void
    {
        $parser = new OboParser();
        $obsolete = [];

        foreach ($parser->terms(GoldenFixtures::lines('reference/go/go-basic.obo')) as $term) {
            $obsolete[$term->goId] = $term->isObsolete;
        }

        self::assertSame(['GO:0000161', 'GO:0102593'], array_keys(array_filter($obsolete)));
        self::assertCount(8, $obsolete);
        self::assertSame('releases/2026-07-26', $parser->dataVersion(GoldenFixtures::lines('reference/go/go-basic.obo')));
    }
}
