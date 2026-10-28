<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Reference\CddDomain;
use App\Domain\Reference\EnzymeEntry;
use App\Domain\Reference\GoNamespace;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\UnirefVariant;
use App\Infrastructure\Persistence\BulkLoadFailedException;
use App\Infrastructure\Persistence\MysqlLoadDataBulkLoader;
use App\Infrastructure\Persistence\MysqlReferenceDataWriter;
use App\Infrastructure\Storage\PendingFile;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Lavora su una release fittizia con un id alto e ne toglie le partizioni alla fine: ADD/DROP PARTITION chiudono
 * qualunque transazione, quindi qui non si può usare il rollback di DatabaseTestCase.
 */
#[CoversClass(MysqlReferenceDataWriter::class)]
#[UsesClass(MysqlLoadDataBulkLoader::class)]
#[UsesClass(BulkLoadFailedException::class)]
#[UsesClass(PendingFile::class)]
#[CoversClass(MappingResult::class)]
#[UsesClass(CddDomain::class)]
#[UsesClass(EnzymeEntry::class)]
#[UsesClass(GoTerm::class)]
#[UsesClass(GoNamespace::class)]
#[UsesClass(IdMappingEntry::class)]
#[UsesClass(Pathway::class)]
#[UsesClass(Pfam2GoMapping::class)]
#[UsesClass(ProteinRecord::class)]
#[UsesClass(SpeciesCode::class)]
final class MysqlReferenceDataWriterTest extends KernelTestCase
{
    private Connection $connection;

    private MysqlReferenceDataWriter $writer;

    private int $release;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
        $this->writer = $this->writer(2);
        $this->release = random_int(60_000, 65_000);
        $this->writer->prepare($this->release);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->writer->drop($this->release);
        parent::tearDown();
    }

    public function testEachReleaseHasItsOwnPartitions(): void
    {
        $this->writer->prepare($this->release);

        self::assertSame(array_fill_keys(MysqlReferenceDataWriter::TABLES, 0), $this->writer->counts($this->release));
        self::assertCount(13, $this->partitions($this->release));

        $this->writer->drop($this->release);
        self::assertSame([], $this->partitions($this->release));
        $this->writer->drop($this->release);

        $this->writer->prepare($this->release);
        self::assertCount(13, $this->partitions($this->release));
    }

    public function testGoTermsIncludeTheirAlternativeIdsAndCanBeReloaded(): void
    {
        $terms = [
            new GoTerm('GO:0000001', 'mitochondrion inheritance', GoNamespace::BiologicalProcess, altIds: ['GO:0000002', 'GO:0000003']),
            new GoTerm('GO:0000004', 'obsolete thing', GoNamespace::MolecularFunction, true),
        ];

        self::assertSame(4, $this->writer->writeGoTerms($this->release, $terms));
        self::assertSame(4, $this->writer->writeGoTerms($this->release, $terms), 'a rerun replaces the terms');
        self::assertSame([
            ['1', 'GO:0000001', 'mitochondrion inheritance', 'P', null, '0'],
            ['2', 'GO:0000002', 'mitochondrion inheritance', 'P', 'GO:0000001', '0'],
            ['3', 'GO:0000003', 'mitochondrion inheritance', 'P', 'GO:0000001', '0'],
            ['4', 'GO:0000004', 'obsolete thing', 'F', null, '1'],
        ], $this->rows('SELECT id, go_id, name, namespace, primary_go_id, is_obsolete FROM ref_go_term WHERE release_id = ? ORDER BY id'));
    }

    public function testProteinsOfTwoSourcesShareTheDictionaries(): void
    {
        $swissProt = [
            new ProteinRecord(ProteinSource::SwissProt, 'P31946', '14-3-3 protein beta/alpha', 'sp|P31946|1433B_HUMAN', 'Homo sapiens', 9606, 'YWHAB', 1, 3),
            new ProteinRecord(ProteinSource::SwissProt, 'P62258', '14-3-3 protein epsilon', 'sp|P62258|1433E_HUMAN', 'Homo sapiens', 9606, 'YWHAE', 1, 1),
            new ProteinRecord(ProteinSource::SwissProt, 'P68250', '14-3-3 protein beta/alpha', 'sp|P68250|1433B_BOVIN', 'Bos taurus', 9913, 'YWHAB', 1, 2),
        ];
        $trembl = [
            new ProteinRecord(ProteinSource::TrEmbl, 'A0A024R161', 'Uncharacterized protein', 'tr|A0A024R161|A0A024R161_HUMAN', 'Homo sapiens', 9606, null, 4, 1),
            new ProteinRecord(ProteinSource::TrEmbl, 'A0A0B4J2F0', '14-3-3 protein epsilon', 'tr|A0A0B4J2F0|A0A0B4J2F0_HUMAN', 'Homo sapiens', 9606),
        ];

        self::assertSame(3, $this->writer->writeProteins($this->release, ProteinSource::SwissProt, $swissProt));
        self::assertSame(2, $this->writer->writeProteins($this->release, ProteinSource::TrEmbl, $trembl));
        self::assertSame(3, $this->writer->writeProteins($this->release, ProteinSource::SwissProt, $swissProt), 'a rerun replaces only its source');

        self::assertSame([
            ['A0A024R161', 'tr', 'Uncharacterized protein', 'Homo sapiens', '9606', null, '4', '1'],
            ['A0A0B4J2F0', 'tr', '14-3-3 protein epsilon', 'Homo sapiens', '9606', null, null, null],
            ['P31946', 'sp', '14-3-3 protein beta/alpha', 'Homo sapiens', '9606', 'YWHAB', '1', '3'],
            ['P62258', 'sp', '14-3-3 protein epsilon', 'Homo sapiens', '9606', 'YWHAE', '1', '1'],
            ['P68250', 'sp', '14-3-3 protein beta/alpha', 'Bos taurus', '9913', 'YWHAB', '1', '2'],
        ], $this->rows(<<<'SQL'
            SELECT p.accession, p.source, d.text, o.name, o.taxon_id, g.name, p.pe, p.sv
            FROM ref_protein p
            JOIN ref_description d ON d.release_id = p.release_id AND d.id = p.description_id
            LEFT JOIN ref_organism o ON o.release_id = p.release_id AND o.id = p.organism_id
            LEFT JOIN ref_gene_name g ON g.release_id = p.release_id AND g.id = p.gene_name_id
            WHERE p.release_id = ? ORDER BY p.accession
            SQL));
        self::assertSame(['ref_description' => 3, 'ref_organism' => 2, 'ref_gene_name' => 2, 'ref_protein' => 5], array_intersect_key($this->writer->counts($this->release), array_flip(['ref_description', 'ref_organism', 'ref_gene_name', 'ref_protein'])));
        self::assertSame([['6', '8']], $this->rows("SELECT MIN(id), MAX(id) FROM ref_protein WHERE release_id = ? AND source = 'sp'"), 'ids continue after the TrEMBL ones');
    }

    public function testUniRefClustersKeepTheirOwnColumns(): void
    {
        $this->writer->writeProteins($this->release, ProteinSource::UniRef, [
            new ProteinRecord(ProteinSource::UniRef, 'UniRef90_P31946', '14-3-3 protein beta/alpha', organism: 'Homo sapiens', taxonId: 9606, memberCount: 12, representativeId: '1433B_HUMAN'),
            new ProteinRecord(ProteinSource::UniRef, 'UniRef90_UPI0000', 'Cluster without organism'),
        ]);

        self::assertSame([
            ['UniRef90_P31946', null, 'Homo sapiens', '12', '1433B_HUMAN'],
            ['UniRef90_UPI0000', null, null, null, null],
        ], $this->rows('SELECT p.accession, p.entry_id, o.name, p.member_count, p.rep_id FROM ref_protein p LEFT JOIN ref_organism o ON o.release_id = p.release_id AND o.id = p.organism_id WHERE p.release_id = ? ORDER BY p.accession'));
    }

    public function testADuplicateAccessionIsAnError(): void
    {
        $protein = new ProteinRecord(ProteinSource::SwissProt, 'P31946', 'protein');

        $this->expectException(BulkLoadFailedException::class);
        $this->expectExceptionMessageIsOrContains('Loading rows into "ref_protein" failed');

        $this->writer->writeProteins($this->release, ProteinSource::SwissProt, [$protein, $protein]);
    }

    public function testProteinGoKeepsTheFirstAppearanceOrderAndClustersGetTheirMembersTerms(): void
    {
        $this->goTerms('GO:0000001', 'GO:0000002', 'GO:0000003');
        $this->writer->writeProteins($this->release, ProteinSource::SwissProt, [new ProteinRecord(ProteinSource::SwissProt, 'P1', 'one'), new ProteinRecord(ProteinSource::SwissProt, 'P2', 'two')]);
        $this->writer->writeProteins($this->release, ProteinSource::UniRef, [new ProteinRecord(ProteinSource::UniRef, 'UniRef90_P1', 'cluster')]);

        $entries = [
            new IdMappingEntry('P1', ['GO:0000003', 'GO:0000001'], ['UniRef90' => 'UniRef90_P1']),
            new IdMappingEntry('Q9', ['GO:0000002'], ['UniRef90' => 'UniRef90_P1']),
            new IdMappingEntry('P2', ['GO:0000002', 'GO:0000001', 'GO:9999999'], ['UniRef90' => 'UniRef90_P1']),
        ];

        $result = $this->writer->mapProteinGo($this->release, $entries, UnirefVariant::UniRef90);

        self::assertSame(7, $result->loaded);
        // Q9 non è nella release e GO:9999999 nemmeno (sia per P2 sia per il cluster)
        self::assertSame(3, $result->unmatched);
        self::assertSame([
            ['P1', 'GO:0000003', '1'], ['P1', 'GO:0000001', '2'],
            ['P2', 'GO:0000002', '1'], ['P2', 'GO:0000001', '2'],
            ['UniRef90_P1', 'GO:0000003', '1'], ['UniRef90_P1', 'GO:0000001', '2'], ['UniRef90_P1', 'GO:0000002', '3'],
        ], $this->rows(<<<'SQL'
            SELECT p.accession, g.go_id, x.position FROM ref_protein_go x
            JOIN ref_protein p ON p.release_id = x.release_id AND p.id = x.protein_id
            JOIN ref_go_term g ON g.release_id = x.release_id AND g.id = x.go_term_id
            WHERE x.release_id = ? ORDER BY p.accession, x.position
            SQL));

        self::assertSame(4, $this->writer->mapProteinGo($this->release, $entries, null)->loaded, 'without a variant only the accessions');
    }

    public function testEnzymesAndPathwaysAreMappedOnceToTheKnownProteins(): void
    {
        $this->writer->writeProteins($this->release, ProteinSource::SwissProt, [new ProteinRecord(ProteinSource::SwissProt, 'P07327', 'alcohol dehydrogenase'), new ProteinRecord(ProteinSource::SwissProt, 'P28469', 'other')]);
        $enzymes = [
            new EnzymeEntry('1.1.1.1', ['Alcohol dehydrogenase.'], ['P07327', 'P28469', 'P07327', 'Q00000']),
            new EnzymeEntry('1.1.1.2', ['Alcohol dehydrogenase (NADP(+)).']),
        ];
        $pathways = [
            new Pathway('Amino-acid biosynthesis; L-lysine biosynthesis via DAP pathway; step 1/4', ['P28469']),
            new Pathway('Cofactor biosynthesis', ['P07327', 'P07327']),
        ];

        self::assertSame(2, $this->writer->writeEnzymes($this->release, $enzymes));
        self::assertSame(2, $this->writer->writePathways($this->release, $pathways));
        self::assertSame([2, 1], $this->counted($this->writer->mapProteinEnzymes($this->release, $enzymes)));
        self::assertSame([2, 0], $this->counted($this->writer->mapProteinPathways($this->release, $pathways)));

        self::assertSame([
            ['1.1.1.1', 'Alcohol dehydrogenase.', 'Alcohol dehydrogenase.'],
            ['1.1.1.2', 'Alcohol dehydrogenase (NADP(+)).', 'Alcohol dehydrogenase (NADP( )).'],
        ], $this->rows('SELECT ec_number, description, legacy_description FROM ref_enzyme WHERE release_id = ? ORDER BY id'));
        self::assertSame([
            ['Amino-acid biosynthesis', 'L-lysine biosynthesis via DAP pathway', 'step 1/4'],
            ['Cofactor biosynthesis', null, null],
        ], $this->rows('SELECT level1, level2, level3 FROM ref_pathway WHERE release_id = ? ORDER BY id'));
        self::assertSame([['P07327', 'Cofactor biosynthesis'], ['P28469', 'Amino-acid biosynthesis']], $this->rows(<<<'SQL'
            SELECT p.accession, w.level1 FROM ref_protein_pathway x
            JOIN ref_protein p ON p.release_id = x.release_id AND p.id = x.protein_id
            JOIN ref_pathway w ON w.release_id = x.release_id AND w.id = x.pathway_id
            WHERE x.release_id = ? ORDER BY p.accession
            SQL));
    }

    public function testDomainsAndTheirGoTerms(): void
    {
        $this->goTerms('GO:0004930', 'GO:0007186');
        $domains = [new CddDomain(366_100, 'pfam00001', '7tm_1', '7 transmembrane receptor', 268), new CddDomain(238_000, 'cd00012', 'NEW', '', 100)];

        self::assertSame(2, $this->writer->writeDomains($this->release, $domains));
        self::assertSame([2, 2], $this->counted($this->writer->mapDomainGo($this->release, [
            new Pfam2GoMapping('pfam00001', '7tm_1', 'GO:0007186', 'signalling'),
            new Pfam2GoMapping('pfam00001', '7tm_1', 'GO:0004930', 'receptor'),
            new Pfam2GoMapping('pfam00001', '7tm_1', 'GO:9999999', 'unknown'),
            new Pfam2GoMapping('pfam99999', 'missing', 'GO:0004930', 'receptor'),
        ])));
        self::assertSame([['pfam00001', 'pfam', 'GO:0007186', '1'], ['pfam00001', 'pfam', 'GO:0004930', '2']], $this->rows(<<<'SQL'
            SELECT d.accession, d.source_db, g.go_id, x.position FROM ref_domain_go x
            JOIN ref_domain d ON d.release_id = x.release_id AND d.id = x.domain_id
            JOIN ref_go_term g ON g.release_id = x.release_id AND g.id = x.go_term_id
            WHERE x.release_id = ? ORDER BY x.position
            SQL));
    }

    public function testTheOrganismCatalog(): void
    {
        self::assertSame(2, $this->writer->writeOrganismCatalog($this->release, [
            new SpeciesCode('HUMAN', 'E', 9606, 'Homo sapiens', 'Human'),
            new SpeciesCode('9VIRU', 'V', 10239, 'Viruses', virtual: true),
        ]));
        self::assertSame([['9VIRU', '10239', null, '1'], ['HUMAN', '9606', 'Human', '0']], $this->rows('SELECT code, taxon_id, common_name, is_virtual FROM ref_organism_catalog WHERE release_id = ? ORDER BY code'));
    }

    public function testNothingCanBeWrittenWithoutPartitions(): void
    {
        $this->writer->drop($this->release);

        $this->expectException(BulkLoadFailedException::class);
        $this->expectExceptionMessageIsOrContains('Loading rows into "ref_go_term" failed');

        $this->writer->writeGoTerms($this->release, []);
    }

    public function testAReleaseIdMustFitTheColumn(): void
    {
        foreach ([0, 65_536] as $id) {
            try {
                $this->writer->counts($id);
                self::fail((string) $id);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($id . ' is not a release id.', $exception->getMessage());
            }
        }
    }

    /**
     * @return array{int, int} associazioni salvate e scartate
     */
    private function counted(MappingResult $result): array
    {
        return [$result->loaded, $result->unmatched];
    }

    private function goTerms(string ...$ids): void
    {
        $this->writer->writeGoTerms($this->release, array_map(static fn (string $id): GoTerm => new GoTerm($id, $id, GoNamespace::BiologicalProcess), $ids));
    }

    /**
     * @param positive-int $chunkSize
     */
    private function writer(int $chunkSize): MysqlReferenceDataWriter
    {
        $directory = self::getContainer()->getParameter('annosynf.bulk_dir');
        self::assertIsString($directory);

        return new MysqlReferenceDataWriter($this->connection, new MysqlLoadDataBulkLoader($this->connection, $directory), $chunkSize);
    }

    /**
     * @return list<list<mixed>>
     */
    private function rows(string $sql): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn (mixed $value): mixed => \is_int($value) ? (string) $value : $value, $row),
            $this->connection->fetchAllNumeric($sql, [$this->release]),
        );
    }

    /**
     * @return list<mixed>
     */
    private function partitions(int $release): array
    {
        return $this->connection->fetchFirstColumn('SELECT TABLE_NAME FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = DATABASE() AND PARTITION_NAME = ?', ['p' . $release]);
    }
}
