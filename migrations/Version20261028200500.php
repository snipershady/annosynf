<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dati di riferimento delle release (CLAUDE.md §B.4.2), con SQL esplicito: Doctrine non esprime il
 * partizionamento e queste tabelle sono escluse dal suo confronto (`schema_filter` in doctrine.yaml).
 *
 * Ogni tabella è partizionata `LIST (release_id)`: l'import aggiunge `PARTITION p<id> VALUES IN (<id>)` prima di
 * caricare e cancellare una release è un `DROP PARTITION`. `p_init` (release 0, che non esiste) c'è solo perché
 * MySQL vuole almeno una partizione. Niente chiavi esterne, che InnoDB non ammette su tabelle partizionate; ogni
 * indice unico contiene `release_id`, come MySQL richiede. I dizionari hanno un hash (MD5 del valore) per
 * deduplicare con `INSERT IGNORE` senza dipendere dalla collation.
 */
final class Version20261028200500 extends AbstractMigration
{
    private const string PARTITIONS = ' PARTITION BY LIST (release_id) (PARTITION p_init VALUES IN (0))';

    /** @var array<string, string> */
    private const array TABLES = [
        'ref_description' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, text MEDIUMTEXT NOT NULL, hash BINARY(16) NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_description_hash (release_id, hash)',
        'ref_organism' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, name VARCHAR(512) NOT NULL, taxon_id INT UNSIGNED DEFAULT NULL, hash BINARY(16) NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_organism_hash (release_id, hash), KEY ix_ref_organism_taxon (release_id, taxon_id), KEY ix_ref_organism_name (release_id, name(191))',
        'ref_gene_name' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, hash BINARY(16) NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_gene_name_hash (release_id, hash)',
        'ref_protein' => "release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, source ENUM('sp','tr','uniref') NOT NULL, accession VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, entry_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, description_id INT UNSIGNED NOT NULL, organism_id INT UNSIGNED DEFAULT NULL, gene_name_id INT UNSIGNED DEFAULT NULL, pe TINYINT UNSIGNED DEFAULT NULL, sv SMALLINT UNSIGNED DEFAULT NULL, member_count INT UNSIGNED DEFAULT NULL, rep_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_protein_accession (release_id, accession), KEY ix_ref_protein_organism (release_id, organism_id)",
        'ref_go_term' => "release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, go_id CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, name TEXT NOT NULL, namespace ENUM('P','F','C') NOT NULL, primary_go_id CHAR(10) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL, is_obsolete TINYINT(1) NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_go_term_go_id (release_id, go_id)",
        'ref_enzyme' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, ec_number VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, description TEXT NOT NULL, legacy_description TEXT NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_enzyme_ec_number (release_id, ec_number)',
        'ref_pathway' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, description TEXT NOT NULL, level1 VARCHAR(512) NOT NULL, level2 VARCHAR(512) DEFAULT NULL, level3 VARCHAR(512) DEFAULT NULL, PRIMARY KEY (release_id, id)',
        'ref_domain' => 'release_id SMALLINT UNSIGNED NOT NULL, id INT UNSIGNED NOT NULL, pssm_id INT UNSIGNED NOT NULL, accession VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, short_name VARCHAR(128) NOT NULL, description TEXT NOT NULL, pssm_length INT UNSIGNED NOT NULL, source_db VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, PRIMARY KEY (release_id, id), UNIQUE KEY uq_ref_domain_accession (release_id, accession)',
        'ref_protein_go' => 'release_id SMALLINT UNSIGNED NOT NULL, protein_id INT UNSIGNED NOT NULL, go_term_id INT UNSIGNED NOT NULL, position SMALLINT UNSIGNED NOT NULL, PRIMARY KEY (release_id, protein_id, go_term_id)',
        'ref_protein_enzyme' => 'release_id SMALLINT UNSIGNED NOT NULL, protein_id INT UNSIGNED NOT NULL, enzyme_id INT UNSIGNED NOT NULL, PRIMARY KEY (release_id, protein_id, enzyme_id)',
        'ref_protein_pathway' => 'release_id SMALLINT UNSIGNED NOT NULL, protein_id INT UNSIGNED NOT NULL, pathway_id INT UNSIGNED NOT NULL, PRIMARY KEY (release_id, protein_id, pathway_id)',
        'ref_domain_go' => 'release_id SMALLINT UNSIGNED NOT NULL, domain_id INT UNSIGNED NOT NULL, go_term_id INT UNSIGNED NOT NULL, position SMALLINT UNSIGNED NOT NULL, PRIMARY KEY (release_id, domain_id, go_term_id)',
        'ref_organism_catalog' => 'release_id SMALLINT UNSIGNED NOT NULL, code VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, kingdom CHAR(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, taxon_id INT UNSIGNED NOT NULL, official_name VARCHAR(512) NOT NULL, common_name VARCHAR(512) DEFAULT NULL, synonym VARCHAR(512) DEFAULT NULL, is_virtual TINYINT(1) NOT NULL, PRIMARY KEY (release_id, code), KEY ix_ref_organism_catalog_taxon (release_id, taxon_id), KEY ix_ref_organism_catalog_name (release_id, official_name(191))',
    ];

    public function getDescription(): string
    {
        return 'Tabelle ref_* dei dati di riferimento, partizionate per release';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table => $columns) {
            $this->addSql(\sprintf('CREATE TABLE %s (%s) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci', $table, $columns) . self::PARTITIONS);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_reverse(array_keys(self::TABLES)) as $table) {
            $this->addSql(\sprintf('DROP TABLE %s', $table));
        }
    }
}
