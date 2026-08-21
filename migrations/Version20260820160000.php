<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical Hotel catalog tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hotel (id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, district_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, slug VARCHAR(180) NOT NULL, stars SMALLINT DEFAULT NULL, address VARCHAR(500) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, check_in TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', check_out TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', description_original LONGTEXT DEFAULT NULL, description_fa LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, verified TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_city (city_id), INDEX idx_hotel_district (district_id), INDEX idx_hotel_name (name), INDEX idx_hotel_name_fa (name_fa), INDEX idx_hotel_stars (stars), INDEX idx_hotel_active_verified (active, verified), UNIQUE INDEX uniq_hotel_city_slug (city_id, slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hotel_amenity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, code VARCHAR(120) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_amenity_name (name), INDEX idx_hotel_amenity_name_fa (name_fa), UNIQUE INDEX uniq_hotel_amenity_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hotel_hotel_amenity (hotel_id INT NOT NULL, hotel_amenity_id INT NOT NULL, INDEX IDX_FC3F29673243BB18 (hotel_id), INDEX IDX_FC3F2967EA4BF9E8 (hotel_amenity_id), PRIMARY KEY(hotel_id, hotel_amenity_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hotel_image (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, path VARCHAR(1024) NOT NULL, alt VARCHAR(255) DEFAULT NULL, alt_fa VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, is_primary TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_image_hotel_primary (hotel_id, is_primary), UNIQUE INDEX uniq_hotel_image_position (hotel_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hotel_source_reference (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, source VARCHAR(64) NOT NULL, external_id VARCHAR(190) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, source_title VARCHAR(255) DEFAULT NULL, checksum VARCHAR(64) DEFAULT NULL, sync_status VARCHAR(32) NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', data_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_source_hotel (hotel_id), INDEX idx_hotel_source_status (sync_status), UNIQUE INDEX uniq_hotel_source_external (source, external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hotel ADD CONSTRAINT FK_3535EDDE8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE hotel ADD CONSTRAINT FK_3535EDDEB08FA272 FOREIGN KEY (district_id) REFERENCES district (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hotel_hotel_amenity ADD CONSTRAINT FK_7D4958553243C9C3 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_hotel_amenity ADD CONSTRAINT FK_7D495855233D8AF6 FOREIGN KEY (hotel_amenity_id) REFERENCES hotel_amenity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_image ADD CONSTRAINT FK_8E27E7533243C9C3 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_source_reference ADD CONSTRAINT FK_5BC329523243C9C3 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_source_reference DROP FOREIGN KEY FK_5BC329523243C9C3');
        $this->addSql('ALTER TABLE hotel_image DROP FOREIGN KEY FK_8E27E7533243C9C3');
        $this->addSql('ALTER TABLE hotel_hotel_amenity DROP FOREIGN KEY FK_7D4958553243C9C3');
        $this->addSql('ALTER TABLE hotel_hotel_amenity DROP FOREIGN KEY FK_7D495855233D8AF6');
        $this->addSql('ALTER TABLE hotel DROP FOREIGN KEY FK_3535EDDE8BAC62AF');
        $this->addSql('ALTER TABLE hotel DROP FOREIGN KEY FK_3535EDDEB08FA272');
        $this->addSql('DROP TABLE hotel_source_reference');
        $this->addSql('DROP TABLE hotel_image');
        $this->addSql('DROP TABLE hotel_hotel_amenity');
        $this->addSql('DROP TABLE hotel_amenity');
        $this->addSql('DROP TABLE hotel');
    }
}
