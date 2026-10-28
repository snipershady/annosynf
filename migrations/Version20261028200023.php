<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogo delle release di riferimento (CLAUDE.md §B.4.1): release, file sorgente, task di import, database
 * BLAST e insiemi di organismi.
 */
final class Version20261028200023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogo delle release di riferimento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organism_set (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(128) NOT NULL, match_mode VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, release_id SMALLINT UNSIGNED NOT NULL, created_by_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX uq_organism_set (release_id, name), INDEX IDX_421EC0DCB12A727D (release_id), INDEX IDX_421EC0DCB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organism_set_member (id INT UNSIGNED AUTO_INCREMENT NOT NULL, matched_proteins INT UNSIGNED DEFAULT NULL, value VARCHAR(512) NOT NULL COLLATE `utf8mb4_bin`, organism_set_id INT UNSIGNED NOT NULL, UNIQUE INDEX uq_organism_set_member (organism_set_id, value), INDEX IDX_1A91D32BD327E30B (organism_set_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reference_blast_db (id INT UNSIGNED AUTO_INCREMENT NOT NULL, kind VARCHAR(16) NOT NULL, name VARCHAR(64) NOT NULL, path VARCHAR(512) NOT NULL, num_sequences BIGINT UNSIGNED NOT NULL, num_letters BIGINT UNSIGNED NOT NULL, blast_version VARCHAR(32) NOT NULL, built_at DATETIME NOT NULL, release_id SMALLINT UNSIGNED NOT NULL, organism_set_id INT UNSIGNED DEFAULT NULL, UNIQUE INDEX uq_reference_blast_db (release_id, name), INDEX IDX_3FBAB330B12A727D (release_id), INDEX IDX_3FBAB330D327E30B (organism_set_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reference_release (id SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL, uniref_variant VARCHAR(10) NOT NULL, status VARCHAR(24) NOT NULL, schema_version VARCHAR(8) NOT NULL, source_versions JSON NOT NULL, counts JSON DEFAULT NULL, error LONGTEXT DEFAULT NULL, started_at DATETIME DEFAULT NULL, ready_at DATETIME DEFAULT NULL, type VARCHAR(16) NOT NULL, version CHAR(7) NOT NULL, cdd_name VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uq_reference_release (type, version, uniref_variant), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reference_source_file (id INT UNSIGNED AUTO_INCREMENT NOT NULL, local_path VARCHAR(512) DEFAULT NULL, bytes BIGINT UNSIGNED DEFAULT NULL, sha256 CHAR(64) DEFAULT NULL, downloaded_at DATETIME DEFAULT NULL, extracted_path VARCHAR(512) DEFAULT NULL, kind VARCHAR(32) NOT NULL, url VARCHAR(1024) NOT NULL, release_id SMALLINT UNSIGNED NOT NULL, UNIQUE INDEX uq_reference_source_file (release_id, kind), INDEX IDX_6B22F127B12A727D (release_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reference_task (id INT UNSIGNED AUTO_INCREMENT NOT NULL, status VARCHAR(16) NOT NULL, attempts SMALLINT UNSIGNED NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, rows_loaded BIGINT UNSIGNED DEFAULT NULL, message LONGTEXT DEFAULT NULL, task VARCHAR(48) NOT NULL, release_id SMALLINT UNSIGNED NOT NULL, UNIQUE INDEX uq_reference_task (release_id, task), INDEX IDX_25BD2385B12A727D (release_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE organism_set ADD CONSTRAINT FK_421EC0DCB12A727D FOREIGN KEY (release_id) REFERENCES reference_release (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organism_set ADD CONSTRAINT FK_421EC0DCB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organism_set_member ADD CONSTRAINT FK_1A91D32BD327E30B FOREIGN KEY (organism_set_id) REFERENCES organism_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reference_blast_db ADD CONSTRAINT FK_3FBAB330B12A727D FOREIGN KEY (release_id) REFERENCES reference_release (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reference_blast_db ADD CONSTRAINT FK_3FBAB330D327E30B FOREIGN KEY (organism_set_id) REFERENCES organism_set (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reference_source_file ADD CONSTRAINT FK_6B22F127B12A727D FOREIGN KEY (release_id) REFERENCES reference_release (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reference_task ADD CONSTRAINT FK_25BD2385B12A727D FOREIGN KEY (release_id) REFERENCES reference_release (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organism_set DROP FOREIGN KEY FK_421EC0DCB12A727D');
        $this->addSql('ALTER TABLE organism_set DROP FOREIGN KEY FK_421EC0DCB03A8386');
        $this->addSql('ALTER TABLE organism_set_member DROP FOREIGN KEY FK_1A91D32BD327E30B');
        $this->addSql('ALTER TABLE reference_blast_db DROP FOREIGN KEY FK_3FBAB330B12A727D');
        $this->addSql('ALTER TABLE reference_blast_db DROP FOREIGN KEY FK_3FBAB330D327E30B');
        $this->addSql('ALTER TABLE reference_source_file DROP FOREIGN KEY FK_6B22F127B12A727D');
        $this->addSql('ALTER TABLE reference_task DROP FOREIGN KEY FK_25BD2385B12A727D');
        $this->addSql('DROP TABLE organism_set');
        $this->addSql('DROP TABLE organism_set_member');
        $this->addSql('DROP TABLE reference_blast_db');
        $this->addSql('DROP TABLE reference_release');
        $this->addSql('DROP TABLE reference_source_file');
        $this->addSql('DROP TABLE reference_task');
    }
}
