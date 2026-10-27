<?php

declare(strict_types=1);

namespace App\Tests\Golden;

use App\Domain\Reference\HeaderTags;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\UniProtHeader;
use App\Domain\Reference\UniProtHeaderParser;
use App\Domain\Reference\UniRefHeader;
use App\Domain\Reference\UniRefHeaderParser;
use App\Domain\Reference\UnirefVariant;
use App\Tests\Support\Golden\GoldenComparison;
use App\Tests\Support\Golden\GoldenFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Header UniProtKB e UniRef letti come createUniprotKBDB e createUnirefDB di Annocript
 * (golden/reference/*_headers.tsv) sugli header di tests/Fixtures/reference/headers, salvo le correzioni
 * dichiarate di §A.12.1 e §A.12.2. I valori assenti sono "NA" come nelle tabelle del legacy.
 */
#[CoversClass(UniProtHeaderParser::class)]
#[CoversClass(UniRefHeaderParser::class)]
#[CoversClass(HeaderTags::class)]
#[UsesClass(UniProtHeader::class)]
#[UsesClass(UniRefHeader::class)]
final class ProteinHeaderParserParityTest extends TestCase
{
    /**
     * Riga del fixture → campi in cui il port si discosta da createUniprotKBDB.
     */
    private const array UNIPROTKB_CORRECTIONS = [
        // gene name con spazi: il legacy tiene il primo token
        5 => ['GN' => 'MGF 110-1L'],
        6 => ['GN' => 'ICL 8'],
        13 => ['GN' => 'orf c06021'],
        // nessun tag seguito da spazio dopo l'organismo: il legacy ne prende un carattere
        15 => ['OS' => 'Frog virus 3 (isolate Goorha)'],
        16 => ['OS' => 'Homo sapiens'],
        // header senza " OS=" preceduto dalla descrizione: il legacy ripete ID e descrizione della riga prima
        17 => ['trId' => 'sp|P99999|NOOS_HUMAN', 'seqId' => 'P99999', 'db' => 'sp', 'description' => 'Protein without organism'],
        18 => ['trId' => 'sp|P99998|EMPTY_HUMAN', 'seqId' => 'P99998', 'db' => 'sp', 'description' => ''],
    ];

    /**
     * Riga del fixture → campi in cui il port si discosta da createUnirefDB, oltre a "TaxID=" tolto dalla
     * tassonomia (tutte le righe che lo hanno).
     */
    private const array UNIREF_CORRECTIONS = [
        // header senza " n=": il legacy ripete ID e numero di membri della riga prima
        12 => ['seqId' => 'UniRef90_Q197F7', 'n' => 'NA'],
    ];

    public function testUniProtKbHeadersMatchAnnocript(): void
    {
        $fields = ['trId', 'seqId', 'db', 'description', 'OS', 'GN', 'PE', 'SV'];
        $legacy = $this->legacyRows('golden/reference/uniprotkb_headers.tsv', $fields);
        $comparison = new GoldenComparison();

        foreach (new UniProtHeaderParser()->headers(GoldenFixtures::lines('reference/headers/uniprotkb.headers')) as $line => $header) {
            $actual = array_combine($fields, [
                $header->entryId,
                $header->accession,
                $header->source->value,
                $header->description,
                $header->organism ?? 'NA',
                $header->geneName ?? 'NA',
                (string) ($header->proteinExistence ?? 'NA'),
                (string) ($header->sequenceVersion ?? 'NA'),
            ]);

            $this->compare($comparison, (string) $line, $legacy[$line] ?? [], self::UNIPROTKB_CORRECTIONS[$line] ?? [], $actual);
        }

        self::assertSame(18 * \count($fields), $comparison->comparisons());
        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    public function testUniRefHeadersMatchAnnocript(): void
    {
        $fields = ['seqId', 'description', 'n', 'tax', 'repId'];
        $legacy = $this->legacyRows('golden/reference/uniref_headers.tsv', $fields);
        $comparison = new GoldenComparison();

        foreach (new UniRefHeaderParser()->headers(GoldenFixtures::lines('reference/headers/uniref.headers')) as $line => $header) {
            $actual = array_combine($fields, [
                $header->clusterId,
                $header->description,
                (string) ($header->memberCount ?? 'NA'),
                $header->taxonomy ?? 'NA',
                $header->representativeId ?? 'NA',
            ]);
            $corrections = self::UNIREF_CORRECTIONS[$line] ?? [];
            $legacyTaxonomy = $legacy[$line]['tax'] ?? '';

            if (str_contains($legacyTaxonomy, ' TaxID=')) {
                $corrections['tax'] = explode(' TaxID=', $legacyTaxonomy, 2)[0];
            }

            $this->compare($comparison, (string) $line, $legacy[$line] ?? [], $corrections, $actual);
        }

        self::assertSame(13 * \count($fields), $comparison->comparisons());
        self::assertSame([], $comparison->failures(), $comparison->report());
    }

    public function testTheTaxonIdentifiersDroppedByAnnocriptAreKept(): void
    {
        $taxa = [];

        foreach (new UniProtHeaderParser()->headers(GoldenFixtures::lines('reference/headers/uniprotkb.headers')) as $header) {
            $taxa[] = $header->taxonId;
        }

        self::assertSame([654924, 654924, 9606, 189830, 443878, 3352, 5850, 9606, 3702, 2285, 2261, 523847, 2287, null, null, 9606, null, 9606], $taxa);

        $clusters = iterator_to_array(new UniRefHeaderParser()->headers(GoldenFixtures::lines('reference/headers/uniref.headers')), false);

        self::assertSame([2918886, 2918886, 654924, 1, 1121927, 243265, 7227, 8782, null, 654924, null, 345201, 9606], array_map(static fn (UniRefHeader $header): ?int => $header->taxonId, $clusters));
        self::assertSame([UnirefVariant::UniRef50, UnirefVariant::UniRef90, UnirefVariant::UniRef100], array_map(static fn (UniRefHeader $header): UnirefVariant => $header->variant, \array_slice($clusters, 0, 3)));
        self::assertSame(ProteinSource::TrEmbl, new UniProtHeaderParser()->parse('>tr|A0A000|A0A000_HUMAN Truncated header OS=Homo sapiens OX=9606')->source);
    }

    /**
     * @param list<string> $fields
     *
     * @return array<int, array<string, string>> riga del fixture → campo → valore
     */
    private function legacyRows(string $golden, array $fields): array
    {
        $rows = [];

        foreach (GoldenFixtures::lines($golden) as $line) {
            $values = explode("\t", $line);
            $rows[(int) array_shift($values)] = array_combine($fields, $values);
        }

        return $rows;
    }

    /**
     * @param array<string, string> $legacy
     * @param array<string, string> $corrections
     * @param array<string, string> $actual
     */
    private function compare(GoldenComparison $comparison, string $line, array $legacy, array $corrections, array $actual): void
    {
        foreach ($actual as $field => $value) {
            $legacyValue = $legacy[$field] ?? '(missing)';

            if (\array_key_exists($field, $corrections)) {
                self::assertNotSame($legacyValue, $corrections[$field], \sprintf('line %s, %s: a declared correction must differ from the legacy', $line, $field));
            }

            $comparison->same($line, $field, $corrections[$field] ?? $legacyValue, $value);
        }
    }
}
