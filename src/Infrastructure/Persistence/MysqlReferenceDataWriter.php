<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\BulkLoader;
use App\Application\Port\ReferenceDataWriter;
use App\Domain\Reference\CddDomain;
use App\Domain\Reference\EnzymeEntry;
use App\Domain\Reference\GoTerm;
use App\Domain\Reference\IdMappingEntry;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\Pathway;
use App\Domain\Reference\Pfam2GoMapping;
use App\Domain\Reference\ProteinRecord;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SpeciesCode;
use App\Domain\Reference\UnirefVariant;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Generator;
use InvalidArgumentException;
use Iterator;
use Override;

/**
 * {@see ReferenceDataWriter} su MySQL: ogni release ha la sua partizione `p<id>` in ogni tabella `ref_*`
 * (migration Version20261028200500), i dati arrivano con {@see BulkLoader} e le associazioni si risolvono con
 * `INSERT … SELECT … JOIN` da tabelle temporanee di staging (§B.5.1), senza dizionari in memoria.
 *
 * Gli id delle proteine e dei dizionari proseguono da quelli già presenti nella release, perché Swiss-Prot e TrEMBL
 * (o UniRef) arrivano da due task distinti e condividono descrizioni, organismi e nomi di gene. I dizionari si
 * deduplicano per MD5 del valore (colonna `hash`), indipendentemente dalla collation. Le proteine arrivano a blocchi:
 * staging, nuovi valori dei dizionari, proteine, staging svuotata.
 */
final readonly class MysqlReferenceDataWriter implements ReferenceDataWriter
{
    /** Tabelle partizionate per release, nell'ordine della migration. */
    public const array TABLES = [
        'ref_description', 'ref_organism', 'ref_gene_name', 'ref_protein', 'ref_go_term', 'ref_enzyme', 'ref_pathway',
        'ref_domain', 'ref_protein_go', 'ref_protein_enzyme', 'ref_protein_pathway', 'ref_domain_go', 'ref_organism_catalog',
    ];

    private const int MAX_RELEASE_ID = 65_535;

    private const string STAGE_PROTEIN = 'CREATE TEMPORARY TABLE stage_protein (id INT UNSIGNED NOT NULL PRIMARY KEY, accession VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, entry_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL, description MEDIUMTEXT NOT NULL, description_hash CHAR(32) CHARACTER SET ascii NOT NULL, organism VARCHAR(512) NULL, taxon_id INT UNSIGNED NULL, organism_hash CHAR(32) CHARACTER SET ascii NULL, gene_name VARCHAR(255) NULL, gene_name_hash CHAR(32) CHARACTER SET ascii NULL, pe TINYINT UNSIGNED NULL, sv SMALLINT UNSIGNED NULL, member_count INT UNSIGNED NULL, rep_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL)';

    private const string STAGE_MAP = 'CREATE TEMPORARY TABLE stage_map (accession VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, target VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, seq BIGINT UNSIGNED NOT NULL)';

    private const string STAGE_PROTEIN_GO = 'CREATE TEMPORARY TABLE stage_protein_go (protein_id INT UNSIGNED NOT NULL, go_term_id INT UNSIGNED NOT NULL, seq BIGINT UNSIGNED NOT NULL, PRIMARY KEY (protein_id, go_term_id))';

    private const array STAGE_PROTEIN_COLUMNS = ['id', 'accession', 'entry_id', 'description', 'description_hash', 'organism', 'taxon_id', 'organism_hash', 'gene_name', 'gene_name_hash', 'pe', 'sv', 'member_count', 'rep_id'];

    /**
     * @param positive-int $chunkSize proteine o associazioni per blocco di staging
     */
    public function __construct(
        private Connection $connection,
        private BulkLoader $loader,
        private int $chunkSize = 200_000,
    ) {
    }

    #[Override]
    public function prepare(int $releaseId): void
    {
        $existing = $this->tablesWithPartition($releaseId);

        foreach (self::TABLES as $table) {
            if (!\in_array($table, $existing, true)) {
                $this->execute($table, \sprintf('ALTER TABLE %s ADD PARTITION (PARTITION p%2$d VALUES IN (%2$d))', $table, $releaseId));
            }
        }
    }

    #[Override]
    public function drop(int $releaseId): void
    {
        foreach ($this->tablesWithPartition($releaseId) as $table) {
            $this->execute($table, \sprintf('ALTER TABLE %s DROP PARTITION p%d', $table, $releaseId));
        }
    }

    #[Override]
    public function counts(int $releaseId): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = $this->count($table, \sprintf('SELECT COUNT(*) FROM %s WHERE release_id = ?', $table), [$this->release($releaseId)]);
        }

        return $counts;
    }

    #[Override]
    public function writeGoTerms(int $releaseId, iterable $terms): int
    {
        $this->truncate('ref_go_term', $releaseId);

        return $this->loader->load('ref_go_term', ['release_id', 'id', 'go_id', 'name', 'namespace', 'primary_go_id', 'is_obsolete'], $this->goTermRows($releaseId, $terms));
    }

    #[Override]
    public function writeEnzymes(int $releaseId, iterable $entries): int
    {
        $this->truncate('ref_enzyme', $releaseId);
        $rows = $this->numbered($entries, static fn (EnzymeEntry $entry, int $id): array => [$releaseId, $id, $entry->ecNumber, $entry->description(), $entry->legacyDescription()]);

        return $this->loader->load('ref_enzyme', ['release_id', 'id', 'ec_number', 'description', 'legacy_description'], $rows);
    }

    #[Override]
    public function writePathways(int $releaseId, iterable $pathways): int
    {
        $this->truncate('ref_pathway', $releaseId);
        $rows = $this->numbered($pathways, static function (Pathway $pathway, int $id) use ($releaseId): array {
            $levels = $pathway->levels();

            return [$releaseId, $id, $pathway->description, $levels[0], $levels[1] ?? null, $levels[2] ?? null];
        });

        return $this->loader->load('ref_pathway', ['release_id', 'id', 'description', 'level1', 'level2', 'level3'], $rows);
    }

    #[Override]
    public function writeDomains(int $releaseId, iterable $domains): int
    {
        $this->truncate('ref_domain', $releaseId);
        $rows = $this->numbered($domains, static fn (CddDomain $domain, int $id): array => [$releaseId, $id, $domain->pssmId, $domain->accession, $domain->shortName, $domain->description, $domain->pssmLength, $domain->sourceDatabase()]);

        return $this->loader->load('ref_domain', ['release_id', 'id', 'pssm_id', 'accession', 'short_name', 'description', 'pssm_length', 'source_db'], $rows);
    }

    #[Override]
    public function writeOrganismCatalog(int $releaseId, iterable $codes): int
    {
        $this->truncate('ref_organism_catalog', $releaseId);
        $rows = $this->numbered($codes, static fn (SpeciesCode $code): array => [$releaseId, $code->code, $code->kingdom, $code->taxonId, $code->officialName, $code->commonName, $code->synonym, $code->virtual]);

        return $this->loader->load('ref_organism_catalog', ['release_id', 'code', 'kingdom', 'taxon_id', 'official_name', 'common_name', 'synonym', 'is_virtual'], $rows);
    }

    #[Override]
    public function writeProteins(int $releaseId, ProteinSource $source, iterable $proteins): int
    {
        $release = $this->release($releaseId);
        $this->execute('ref_protein', 'DELETE FROM ref_protein WHERE release_id = ? AND source = ?', [$release, $source->value]);
        $next = $this->count('ref_protein', 'SELECT COALESCE(MAX(id), 0) FROM ref_protein WHERE release_id = ?', [$release]);
        $this->createStage('stage_protein', self::STAGE_PROTEIN);
        $records = $this->iterate($proteins);
        $loaded = 0;

        try {
            while ($records->valid()) {
                $staged = $this->loader->load('stage_protein', self::STAGE_PROTEIN_COLUMNS, $this->proteinRows($records, $next));
                $next += $staged;
                $this->addDictionaryValues($release, 'ref_description', 'text', 'description');
                $this->addDictionaryValues($release, 'ref_organism', 'name', 'organism', ['taxon_id']);
                $this->addDictionaryValues($release, 'ref_gene_name', 'name', 'gene_name');
                $loaded += $this->execute('ref_protein', <<<'SQL'
                    INSERT INTO ref_protein (release_id, id, source, accession, entry_id, description_id, organism_id, gene_name_id, pe, sv, member_count, rep_id)
                    SELECT ?, s.id, ?, s.accession, s.entry_id, d.id, o.id, g.id, s.pe, s.sv, s.member_count, s.rep_id
                    FROM stage_protein s
                    JOIN ref_description d ON d.release_id = ? AND d.hash = UNHEX(s.description_hash)
                    LEFT JOIN ref_organism o ON o.release_id = ? AND o.hash = UNHEX(s.organism_hash)
                    LEFT JOIN ref_gene_name g ON g.release_id = ? AND g.hash = UNHEX(s.gene_name_hash)
                    SQL, [$release, $source->value, $release, $release, $release]);
                $this->execute('stage_protein', 'DELETE FROM stage_protein');
            }
        } finally {
            $this->dropStage('stage_protein');
        }

        return $loaded;
    }

    #[Override]
    public function mapProteinGo(int $releaseId, iterable $entries, ?UnirefVariant $variant): MappingResult
    {
        $release = $this->release($releaseId);
        $this->truncate('ref_protein_go', $releaseId);
        $this->createStage('stage_map', self::STAGE_MAP);
        $this->createStage('stage_protein_go', self::STAGE_PROTEIN_GO);
        $pairs = $this->iterate($this->proteinGoPairs($entries, $variant));
        $unmatched = 0;

        try {
            while ($pairs->valid()) {
                $this->loader->load('stage_map', ['accession', 'target', 'seq'], $this->take($pairs));
                $unmatched += $this->unmatched($release, 'ref_protein', 'accession', 'ref_go_term', 'go_id');
                // un cluster UniRef riceve i GO di tutti i suoi membri: si tiene la prima apparizione di ogni coppia
                $this->execute('stage_protein_go', <<<'SQL'
                    INSERT INTO stage_protein_go (protein_id, go_term_id, seq)
                    SELECT * FROM (
                        SELECT p.id AS protein_id, g.id AS go_term_id, MIN(s.seq) AS first_seq
                        FROM stage_map s
                        JOIN ref_protein p ON p.release_id = ? AND p.accession = s.accession
                        JOIN ref_go_term g ON g.release_id = ? AND g.go_id = s.target
                        GROUP BY p.id, g.id
                    ) AS n
                    ON DUPLICATE KEY UPDATE seq = LEAST(stage_protein_go.seq, n.first_seq)
                    SQL, [$release, $release]);
                $this->execute('stage_map', 'DELETE FROM stage_map');
            }

            $loaded = $this->execute('ref_protein_go', 'INSERT INTO ref_protein_go (release_id, protein_id, go_term_id, position) SELECT ?, protein_id, go_term_id, ROW_NUMBER() OVER (PARTITION BY protein_id ORDER BY seq) FROM stage_protein_go', [$release]);
        } finally {
            $this->dropStage('stage_map');
            $this->dropStage('stage_protein_go');
        }

        return new MappingResult($loaded, $unmatched);
    }

    #[Override]
    public function mapProteinEnzymes(int $releaseId, iterable $entries): MappingResult
    {
        $pairs = (static function () use ($entries): Generator {
            foreach ($entries as $entry) {
                foreach ($entry->accessions as $accession) {
                    yield [$accession, $entry->ecNumber];
                }
            }
        })();

        return $this->mapProteins($releaseId, 'ref_protein_enzyme', 'enzyme_id', 'ref_enzyme', 'ec_number', $pairs);
    }

    #[Override]
    public function mapProteinPathways(int $releaseId, iterable $pathways): MappingResult
    {
        // stessa numerazione di writePathways: l'id del pathway è la sua posizione nel file
        $pairs = (static function () use ($pathways): Generator {
            $id = 0;

            foreach ($pathways as $pathway) {
                ++$id;

                foreach ($pathway->accessions as $accession) {
                    yield [$accession, (string) $id];
                }
            }
        })();

        return $this->mapProteins($releaseId, 'ref_protein_pathway', 'pathway_id', 'ref_pathway', 'id', $pairs);
    }

    #[Override]
    public function mapDomainGo(int $releaseId, iterable $mappings): MappingResult
    {
        $release = $this->release($releaseId);
        $this->truncate('ref_domain_go', $releaseId);
        $this->createStage('stage_map', self::STAGE_MAP);

        try {
            $this->loader->load('stage_map', ['accession', 'target', 'seq'], $this->numbered($mappings, static fn (Pfam2GoMapping $mapping, int $seq): array => [$mapping->pfamAccession, $mapping->goId, $seq]));
            $unmatched = $this->unmatched($release, 'ref_domain', 'accession', 'ref_go_term', 'go_id');
            $loaded = $this->execute('ref_domain_go', <<<'SQL'
                INSERT INTO ref_domain_go (release_id, domain_id, go_term_id, position)
                SELECT ?, n.domain_id, n.go_term_id, ROW_NUMBER() OVER (PARTITION BY n.domain_id ORDER BY n.first_seq)
                FROM (
                    SELECT d.id AS domain_id, g.id AS go_term_id, MIN(s.seq) AS first_seq
                    FROM stage_map s
                    JOIN ref_domain d ON d.release_id = ? AND d.accession = s.accession
                    JOIN ref_go_term g ON g.release_id = ? AND g.go_id = s.target
                    GROUP BY d.id, g.id
                ) AS n
                SQL, [$release, $release, $release]);
        } finally {
            $this->dropStage('stage_map');
        }

        return new MappingResult($loaded, $unmatched);
    }

    /**
     * Associazioni proteina → enzima o pathway: piccole (enzyme.dat e pathway.txt elencano solo Swiss-Prot), quindi
     * in un solo blocco di staging. Un'accession ripetuta nella stessa voce si salva una volta (§A.12.23).
     *
     * @param iterable<array{string, string}> $pairs accession → valore di `$targetKey` in `$targetTable`
     */
    private function mapProteins(int $releaseId, string $table, string $column, string $targetTable, string $targetKey, iterable $pairs): MappingResult
    {
        $release = $this->release($releaseId);
        $this->truncate($table, $releaseId);
        $this->createStage('stage_map', self::STAGE_MAP);

        try {
            $this->loader->load('stage_map', ['accession', 'target', 'seq'], $this->numbered($pairs, static fn (array $pair, int $seq): array => [$pair[0], $pair[1], $seq]));
            $unmatched = $this->unmatched($release, 'ref_protein', 'accession', $targetTable, $targetKey);
            $loaded = $this->execute($table, \sprintf(<<<'SQL'
                INSERT INTO %1$s (release_id, protein_id, %2$s)
                SELECT DISTINCT ?, p.id, t.id
                FROM stage_map s
                JOIN ref_protein p ON p.release_id = ? AND p.accession = s.accession
                JOIN %3$s t ON t.release_id = ? AND t.%4$s = s.target
                SQL, $table, $column, $targetTable, $targetKey), [$release, $release, $release]);
        } finally {
            $this->dropStage('stage_map');
        }

        return new MappingResult($loaded, $unmatched);
    }

    /**
     * Righe di `stage_map` la cui accession o il cui valore non sono nella release.
     */
    private function unmatched(int $release, string $sourceTable, string $sourceKey, string $targetTable, string $targetKey): int
    {
        return $this->count('stage_map', \sprintf(<<<'SQL'
            SELECT COUNT(*) FROM stage_map s
            WHERE NOT EXISTS (SELECT 1 FROM %1$s a WHERE a.release_id = ? AND a.%2$s = s.accession)
               OR NOT EXISTS (SELECT 1 FROM %3$s b WHERE b.release_id = ? AND b.%4$s = s.target)
            SQL, $sourceTable, $sourceKey, $targetTable, $targetKey), [$release, $release]);
    }

    /**
     * Aggiunge al dizionario i valori della staging che non ha ancora, con id che proseguono da quelli esistenti
     * nell'ordine della prima proteina che li usa.
     *
     * @param list<string> $extraColumns colonne copiate così come sono (prima occorrenza del valore)
     */
    private function addDictionaryValues(int $release, string $table, string $valueColumn, string $stageColumn, array $extraColumns = []): void
    {
        $offset = $this->count($table, \sprintf('SELECT COALESCE(MAX(id), 0) FROM %s WHERE release_id = ?', $table), [$release]);
        $extra = implode('', array_map(static fn (string $column): string => ', ' . $column, $extraColumns));
        $extraValues = implode('', array_map(static fn (string $column): string => ', n.' . $column, $extraColumns));
        $extraSelect = implode('', array_map(static fn (string $column): string => \sprintf(', ANY_VALUE(s.%1$s) AS %1$s', $column), $extraColumns));

        $this->execute($table, \sprintf(<<<'SQL'
            INSERT INTO %1$s (release_id, id, %2$s, hash%3$s)
            SELECT ?, ? + ROW_NUMBER() OVER (ORDER BY n.first_id), n.value, n.hash%4$s
            FROM (
                SELECT MIN(s.id) AS first_id, UNHEX(s.%5$s_hash) AS hash, ANY_VALUE(s.%5$s) AS value%6$s
                FROM stage_protein s
                WHERE s.%5$s_hash IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM %1$s d WHERE d.release_id = ? AND d.hash = UNHEX(s.%5$s_hash))
                GROUP BY s.%5$s_hash
            ) AS n
            SQL, $table, $valueColumn, $extra, $extraValues, $stageColumn, $extraSelect), [$release, $offset, $release]);
    }

    /**
     * @param iterable<GoTerm> $terms
     *
     * @return Generator<int, list<string|int|bool|null>>
     */
    private function goTermRows(int $releaseId, iterable $terms): Generator
    {
        $id = 0;

        foreach ($terms as $term) {
            yield [$releaseId, ++$id, $term->goId, $term->name, $term->namespace->value, null, $term->isObsolete];

            foreach ($term->altIds as $altId) {
                yield [$releaseId, ++$id, $altId, $term->name, $term->namespace->value, $term->goId, $term->isObsolete];
            }
        }
    }

    /**
     * Fino a un blocco di proteine per la staging, con id progressivi da `$last + 1`.
     *
     * @param Iterator<mixed, ProteinRecord> $records
     *
     * @return Generator<int, list<string|int|null>>
     */
    private function proteinRows(Iterator $records, int $last): Generator
    {
        foreach ($this->take($records) as $record) {
            $organism = $record->organism === null ? null : md5($record->organism . "\t" . $record->taxonId);

            yield [
                ++$last, $record->accession, $record->entryId, $record->description, md5($record->description),
                $record->organism, $record->taxonId, $organism, $record->geneName,
                $record->geneName === null ? null : md5($record->geneName),
                $record->proteinExistence, $record->sequenceVersion, $record->memberCount, $record->representativeId,
            ];
        }
    }

    /**
     * Coppie accession → GO id da idmapping, con un progressivo che ne conserva l'ordine nel file.
     *
     * @param iterable<IdMappingEntry> $entries
     *
     * @return Generator<int, list<string|int>>
     */
    private function proteinGoPairs(iterable $entries, ?UnirefVariant $variant): Generator
    {
        $seq = 0;

        foreach ($entries as $entry) {
            $keys = [$entry->accession];
            $cluster = $variant instanceof UnirefVariant ? $entry->cluster($variant) : null;

            if ($cluster !== null) {
                $keys[] = $cluster;
            }

            foreach ($entry->goIds as $goId) {
                ++$seq;

                foreach ($keys as $key) {
                    yield [$key, $goId, $seq];
                }
            }
        }
    }

    /**
     * I prossimi elementi di un iteratore già avviato, al massimo un blocco.
     *
     * @template T
     *
     * @param Iterator<mixed, T> $iterator
     *
     * @return Generator<int, T>
     */
    private function take(Iterator $iterator): Generator
    {
        for ($taken = 0; $taken < $this->chunkSize && $iterator->valid(); ++$taken) {
            yield $iterator->current();
            $iterator->next();
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $items
     *
     * @return Generator<mixed, T>
     */
    private function iterate(iterable $items): Generator
    {
        yield from $items;
    }

    /**
     * Righe numerate da 1 nell'ordine del file.
     *
     * @template T
     *
     * @param iterable<T>                                        $items
     * @param callable(T, int): list<string|int|float|bool|null> $row
     *
     * @return Generator<int, list<string|int|float|bool|null>>
     */
    private function numbered(iterable $items, callable $row): Generator
    {
        $number = 0;

        foreach ($items as $item) {
            yield $row($item, ++$number);
        }
    }

    /**
     * @return list<string> tabelle `ref_*` che hanno già la partizione della release
     */
    private function tablesWithPartition(int $releaseId): array
    {
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ref\\_%' AND PARTITION_NAME = ?",
            ['p' . $this->release($releaseId)],
        );

        return array_values(array_filter($tables, \is_string(...)));
    }

    private function truncate(string $table, int $releaseId): void
    {
        $this->execute($table, \sprintf('ALTER TABLE %s TRUNCATE PARTITION p%d', $table, $this->release($releaseId)));
    }

    private function createStage(string $table, string $definition): void
    {
        $this->dropStage($table);
        $this->execute($table, $definition);
    }

    private function dropStage(string $table): void
    {
        $this->execute($table, 'DROP TEMPORARY TABLE IF EXISTS ' . $table);
    }

    /**
     * @param list<int|string> $parameters
     *
     * @throws BulkLoadFailedException
     */
    private function execute(string $table, string $sql, array $parameters = []): int
    {
        return (int) $this->guard($table, fn (): int|string => $this->connection->executeStatement($sql, $parameters));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @throws BulkLoadFailedException
     */
    private function count(string $table, string $sql, array $parameters): int
    {
        $value = $this->guard($table, fn (): mixed => $this->connection->fetchOne($sql, $parameters));

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @template T
     *
     * @param callable(): T $statement
     *
     * @throws BulkLoadFailedException
     *
     * @return T
     */
    private function guard(string $table, callable $statement): mixed
    {
        try {
            return $statement();
        } catch (DbalException $dbalException) {
            throw BulkLoadFailedException::rejected($table, $dbalException);
        }
    }

    /**
     * @throws InvalidArgumentException se l'id non può essere quello di una release (SMALLINT UNSIGNED, 0 è `p_init`)
     */
    private function release(int $releaseId): int
    {
        if ($releaseId < 1 || $releaseId > self::MAX_RELEASE_ID) {
            throw new InvalidArgumentException(\sprintf('%d is not a release id.', $releaseId));
        }

        return $releaseId;
    }
}
