<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Destination State/Admin1 hierarchy and City state relation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE state (id INT AUTO_INCREMENT NOT NULL, country_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, code VARCHAR(32) NOT NULL, slug VARCHAR(180) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A393D2FBF92F3E70 (country_id), INDEX idx_state_name (name), INDEX idx_state_name_fa (name_fa), UNIQUE INDEX uniq_state_country_code (country_id, code), UNIQUE INDEX uniq_state_country_slug (country_id, slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE state ADD CONSTRAINT FK_A393D2FBF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE city ADD state_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B02345D83CC1 FOREIGN KEY (state_id) REFERENCES state (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX uniq_city_country_slug ON city');
        $this->addSql('DROP INDEX uniq_city_country_name ON city');
        $this->addSql('CREATE INDEX IDX_2D5B02345D83CC1 ON city (state_id)');
        $this->addSql('CREATE INDEX idx_city_name ON city (name)');
        $this->addSql('CREATE INDEX idx_city_name_fa ON city (name_fa)');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_country_state_slug ON city (country_id, state_id, slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_country_state_name ON city (country_id, state_id, name)');
        $this->addSql('CREATE INDEX idx_country_name ON country (name)');
        $this->addSql('CREATE INDEX idx_country_name_fa ON country (name_fa)');
        $this->addSql('CREATE INDEX idx_district_name ON district (name)');
        $this->addSql('CREATE INDEX idx_district_name_fa ON district (name_fa)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B02345D83CC1');
        $this->addSql('ALTER TABLE state DROP FOREIGN KEY FK_A393D2FBF92F3E70');
        $this->addSql('DROP INDEX uniq_city_country_state_slug ON city');
        $this->addSql('DROP INDEX uniq_city_country_state_name ON city');
        $this->addSql('DROP INDEX IDX_2D5B02345D83CC1 ON city');
        $this->addSql('DROP INDEX idx_city_name ON city');
        $this->addSql('DROP INDEX idx_city_name_fa ON city');
        $this->addSql('ALTER TABLE city DROP state_id');
        $this->addSql('DROP TABLE state');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_country_slug ON city (country_id, slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_country_name ON city (country_id, name)');
        $this->addSql('DROP INDEX idx_country_name ON country');
        $this->addSql('DROP INDEX idx_country_name_fa ON country');
        $this->addSql('DROP INDEX idx_district_name ON district');
        $this->addSql('DROP INDEX idx_district_name_fa ON district');
    }
}
