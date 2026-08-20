<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Destination Catalog tables for countries, cities, districts, and airports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE country (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, iso2 VARCHAR(2) DEFAULT NULL, iso3 VARCHAR(3) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_5373C9663C24D15A (iso2), UNIQUE INDEX UNIQ_5373C96649D13143 (iso3), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE city (id INT AUTO_INCREMENT NOT NULL, country_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, slug VARCHAR(180) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2D5B0234F92F3E70 (country_id), UNIQUE INDEX uniq_city_country_slug (country_id, slug), UNIQUE INDEX uniq_city_country_name (country_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE district (id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, slug VARCHAR(180) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_31C154878BAC62AF (city_id), UNIQUE INDEX uniq_district_city_slug (city_id, slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE airport (id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, iata_code VARCHAR(3) DEFAULT NULL, icao_code VARCHAR(4) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_2DC0AD6D5FDF6F93 (iata_code), UNIQUE INDEX UNIQ_2DC0AD6D19D036B6 (icao_code), INDEX IDX_2DC0AD6D8BAC62AF (city_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE district ADD CONSTRAINT FK_31C154878BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE airport ADD CONSTRAINT FK_2DC0AD6D8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE airport DROP FOREIGN KEY FK_2DC0AD6D8BAC62AF');
        $this->addSql('ALTER TABLE district DROP FOREIGN KEY FK_31C154878BAC62AF');
        $this->addSql('ALTER TABLE city DROP FOREIGN KEY FK_2D5B0234F92F3E70');
        $this->addSql('DROP TABLE airport');
        $this->addSql('DROP TABLE district');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE country');
    }
}
