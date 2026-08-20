<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Destination import run and source reference tracking tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE destination_import_run (id INT AUTO_INCREMENT NOT NULL, target_type VARCHAR(32) NOT NULL, country_name VARCHAR(180) NOT NULL, city_name VARCHAR(180) DEFAULT NULL, providers JSON NOT NULL, refresh TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(32) NOT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', found_count INT NOT NULL, created_count INT NOT NULL, updated_count INT NOT NULL, unchanged_count INT NOT NULL, skipped_count INT NOT NULL, duplicate_count INT NOT NULL, failed_count INT NOT NULL, errors JSON NOT NULL, summary JSON NOT NULL, INDEX idx_destination_import_target (target_type, country_name, city_name), INDEX idx_destination_import_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE destination_source_reference (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(64) NOT NULL, canonical_type VARCHAR(32) NOT NULL, canonical_id INT NOT NULL, external_id VARCHAR(190) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, source_title VARCHAR(255) DEFAULT NULL, normalized_name VARCHAR(190) DEFAULT NULL, checksum VARCHAR(64) DEFAULT NULL, sync_status VARCHAR(32) NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', data_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_destination_source_external (source, canonical_type, external_id), INDEX idx_destination_source_canonical (canonical_type, canonical_id), INDEX idx_destination_source_status (sync_status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE destination_source_reference');
        $this->addSql('DROP TABLE destination_import_run');
    }
}
