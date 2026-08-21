<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Search Source configuration table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE search_source (id INT AUTO_INCREMENT NOT NULL, country_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, domain VARCHAR(255) NOT NULL, provider VARCHAR(64) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, language VARCHAR(16) DEFAULT NULL, priority INT DEFAULT 0 NOT NULL, provider_type VARCHAR(255) NOT NULL, capabilities JSON NOT NULL, config JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_search_source_country (country_id), INDEX idx_search_source_name (name), INDEX idx_search_source_domain (domain), INDEX idx_search_source_provider (provider), INDEX idx_search_source_provider_type (provider_type), INDEX idx_search_source_enabled_priority (enabled, priority), UNIQUE INDEX uniq_search_source_domain_provider_country_language (domain, provider, country_id, language), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE search_source ADD CONSTRAINT FK_643049DCF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_source DROP FOREIGN KEY FK_643049DCF92F3E70');
        $this->addSql('DROP TABLE search_source');
    }
}
