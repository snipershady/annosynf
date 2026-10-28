<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le migration producono lo schema descritto dalle entity e le tabelle ref_* partizionate per release.
 */
#[CoversNothing]
final class ReferenceSchemaTest extends KernelTestCase
{
    private const array REFERENCE_TABLES = [
        'ref_description', 'ref_domain', 'ref_domain_go', 'ref_enzyme', 'ref_gene_name', 'ref_go_term', 'ref_organism',
        'ref_organism_catalog', 'ref_pathway', 'ref_protein', 'ref_protein_enzyme', 'ref_protein_go', 'ref_protein_pathway',
    ];

    public function testTheMigratedSchemaMatchesTheEntities(): void
    {
        $validator = new SchemaValidator(self::getContainer()->get(EntityManagerInterface::class));

        self::assertSame([], $validator->validateMapping());
        // la tabella delle migration la nasconde DoctrineMigrationsBundle solo nei comandi della console
        $differences = array_filter($validator->getUpdateSchemaList(), static fn (string $sql): bool => $sql !== 'DROP TABLE doctrine_migration_versions');

        self::assertSame([], array_values($differences), 'run doctrine:migrations:migrate --env=test');
    }

    public function testReferenceTablesArePartitionedByRelease(): void
    {
        $partitions = self::getContainer()->get(Connection::class)->fetchAllAssociative(
            "SELECT TABLE_NAME, PARTITION_NAME, PARTITION_METHOD, PARTITION_EXPRESSION FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ref\\_%' ORDER BY TABLE_NAME",
        );

        self::assertSame(self::REFERENCE_TABLES, array_column($partitions, 'TABLE_NAME'));

        foreach ($partitions as $partition) {
            self::assertSame(['p_init', 'LIST', '`release_id`'], [$partition['PARTITION_NAME'], $partition['PARTITION_METHOD'], $partition['PARTITION_EXPRESSION']]);
        }
    }
}
