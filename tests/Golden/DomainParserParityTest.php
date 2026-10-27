<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\CddDomain;
use App\Domain\Reference\CddIdParser;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\Pfam2GoParser;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Domini e associazioni Pfam → GO identici a createCDTable e createPfamGO (golden/reference/domains_*.tsv):
 * la cdTable, il mapping dominio → GO e la versione di pfam2go, sui fixture di tests/Fixtures/reference/cdd,
 * salvo le correzioni dichiarate di §A.12.4.
 */
#[CoversClass(CddIdParser::class)]
#[CoversClass(CddDomain::class)]
#[CoversClass(Pfam2GoParser::class)]
#[CoversClass(Pfam2GoMapping::class)]
final class DomainParserParityTest extends TestCase
{
    /**
     * File pfam2go → righe del mapping (cd_id → GO) che il port scrive diverse; null = riga che il port non scrive.
     */
    private const array CORRECTIONS = [
        // pfam25178, pfam25299, pfam25312 e pfam25391 non sono nella cdTable: il legacy dà i loro GO a pfam25129 (id 10)
        'pfam2go' => ['10' => 'GO:0016829,'],
        // pfam25178 non è nella cdTable ed è la prima riga: il legacy dà il suo GO al dominio con id vuoto
        'pfam2go_missing_first' => ['' => null],
    ];

    /**
     * @return Generator<string, array{string}>
     */
    public static function pfam2goFiles(): Generator
    {
        foreach (array_keys(self::CORRECTIONS) as $file) {
            yield $file => [$file];
        }
    }

    #[DataProvider('pfam2goFiles')]
    public function testTheCdTableMatchesAnnocript(string $pfam2go): void
    {
        // createCDTable lascia il terminatore nell'ultimo campo e ne aggiunge un altro: le righe vuote sono di troppo.
        $expected = array_values(array_filter(
            iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/domains_%s.tsv', $pfam2go)), false),
            static fn (string $line): bool => $line !== '',
        ));
        $actual = [];

        foreach ($this->domains() as $index => $domain) {
            $actual[] = implode("\t", [$index + 1, $domain->pssmId, $domain->accession, $domain->shortName, $domain->description, $domain->pssmLength]);
        }

        $this->assertSameRows($expected, $actual);
    }

    #[DataProvider('pfam2goFiles')]
    public function testTheDomainGoMappingMatchesAnnocript(string $pfam2go): void
    {
        $expected = [];

        foreach (GoldenFixtures::lines(\sprintf('golden/reference/domains_%s.tsv.mapping', $pfam2go)) as $line) {
            [$domain, $goIds] = explode("\t", $line, 2);
            $expected[$domain] = $goIds;
        }

        foreach (self::CORRECTIONS[$pfam2go] as $domain => $goIds) {
            self::assertArrayHasKey($domain, $expected);
            self::assertNotSame($expected[$domain], $goIds, 'a declared correction must differ from the legacy');
            $expected[$domain] = $goIds;
        }

        $domainIds = [];

        foreach ($this->domains() as $index => $domain) {
            $domainIds[$domain->accession] = $index + 1;
        }

        $mapping = [];

        foreach (new Pfam2GoParser()->mappings(GoldenFixtures::lines('reference/cdd/' . $pfam2go)) as $association) {
            $domainId = $domainIds[$association->pfamAccession] ?? null;

            if ($domainId !== null && !\in_array($association->goId, $mapping[$domainId] ?? [], true)) {
                $mapping[$domainId][] = $association->goId;
            }
        }

        ksort($mapping, \SORT_STRING);
        $actual = array_map(static fn (int $domain, array $goIds): string => $domain . "\t" . implode(',', $goIds) . ',', array_keys($mapping), $mapping);
        $expectedRows = [];

        foreach ($expected as $domain => $goIds) {
            if ($goIds !== null) {
                $expectedRows[] = $domain . "\t" . $goIds;
            }
        }

        $this->assertSameRows($expectedRows, $actual);
    }

    #[DataProvider('pfam2goFiles')]
    public function testTheVersionMatchesAnnocript(string $pfam2go): void
    {
        $legacy = iterator_to_array(GoldenFixtures::lines(\sprintf('golden/reference/domains_%s.tsv.version', $pfam2go)), false)[0] ?? '';

        self::assertSame("pfam2go\t2026/07/06", $legacy);
        self::assertSame('2026/07/06', new Pfam2GoParser()->version(GoldenFixtures::lines('reference/cdd/' . $pfam2go)));
    }

    /**
     * @return list<CddDomain>
     */
    private function domains(): array
    {
        return iterator_to_array(new CddIdParser()->domains(GoldenFixtures::lines('reference/cdd/cddid_all.tbl')), false);
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function assertSameRows(array $expected, array $actual): void
    {
        $comparison = new GoldenComparison();

        foreach ($expected as $index => $row) {
            $comparison->same((string) ($index + 1), 'row', $row, $actual[$index] ?? '(missing)');
        }

        self::assertCount(\count($expected), $actual);
        self::assertSame([], $comparison->failures(), $comparison->report());
    }
}
