<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\OrganismLookup;
use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Reference\OrganismSearchHit;
use App\Domain\Reference\ProteinSource;
use Doctrine\DBAL\Connection;
use Override;

/**
 * {@see OrganismLookup} sulle tabelle `ref_*` (lettura DBAL). I confronti sui nomi restano deterministici qualunque
 * sia la collation della colonna: la condizione sulla collation di default sfrutta l'indice, quella binaria o
 * `as_ci` rifinisce il risultato (`name_exact` distingue maiuscole e accenti, `name_ci` solo gli accenti).
 */
final readonly class MysqlOrganismLookup implements OrganismLookup
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function search(int $releaseId, string $text, int $limit = 50): array
    {
        $like = '%' . addcslashes($text, '%_\\') . '%';
        $rows = $this->connection->fetchAllAssociative(
            'SELECT code, taxon_id, official_name, common_name, synonym FROM ref_organism_catalog
             WHERE release_id = ? AND (official_name LIKE ? OR common_name LIKE ? OR synonym LIKE ? OR code = ? OR taxon_id = ?)
             ORDER BY official_name, code LIMIT ' . max(1, $limit),
            [$releaseId, $like, $like, $like, $text, ctype_digit($text) ? (int) $text : null],
        );

        return array_map(
            static fn (array $row): OrganismSearchHit => new OrganismSearchHit(
                \is_string($row['code']) ? $row['code'] : '',
                is_numeric($row['taxon_id']) ? (int) $row['taxon_id'] : 0,
                \is_string($row['official_name']) ? $row['official_name'] : '',
                \is_string($row['common_name']) ? $row['common_name'] : null,
                \is_string($row['synonym']) ? $row['synonym'] : null,
            ),
            $rows,
        );
    }

    #[Override]
    public function countProteins(int $releaseId, OrganismMatchMode $mode, string $value): int
    {
        [$condition, $parameters] = $this->condition($mode, [$value]);
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ref_protein p JOIN ref_organism o ON o.release_id = p.release_id AND o.id = p.organism_id
             WHERE p.release_id = ? AND ' . $condition,
            [$releaseId, ...$parameters],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function sequenceIds(int $releaseId, ProteinSource $source, OrganismMatchMode $mode, array $values): iterable
    {
        [$condition, $parameters] = $this->condition($mode, $values);

        foreach ($this->connection->iterateColumn(
            'SELECT COALESCE(p.entry_id, p.accession) FROM ref_protein p
             JOIN ref_organism o ON o.release_id = p.release_id AND o.id = p.organism_id
             WHERE p.release_id = ? AND p.source = ? AND ' . $condition,
            [$releaseId, $source->value, ...$parameters],
        ) as $id) {
            if (\is_string($id)) {
                yield $id;
            }
        }
    }

    /**
     * @param non-empty-list<string> $values
     *
     * @return array{string, list<int|string>} condizione su `o` e parametri
     */
    private function condition(OrganismMatchMode $mode, array $values): array
    {
        $clauses = [];
        $parameters = [];

        foreach ($values as $value) {
            if ($mode === OrganismMatchMode::TaxonId) {
                $clauses[] = 'o.taxon_id = ?';
                $parameters[] = (int) $value;

                continue;
            }

            $clauses[] = $mode === OrganismMatchMode::ExactName
                ? '(o.name = ? AND o.name COLLATE utf8mb4_bin = ?)'
                : '(o.name = ? AND o.name COLLATE utf8mb4_0900_as_ci = ?)';
            $parameters[] = $value;
            $parameters[] = $value;
        }

        return ['(' . implode(' OR ', $clauses) . ')', $parameters];
    }
}
