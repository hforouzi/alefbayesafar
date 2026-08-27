<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hotel room types and own nightly rates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hotel_room_type (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, code VARCHAR(64) DEFAULT NULL, max_adults SMALLINT NOT NULL, max_children SMALLINT NOT NULL, max_occupancy SMALLINT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_room_type_hotel_active (hotel_id, active), UNIQUE INDEX uniq_hotel_room_type_hotel_code (hotel_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hotel_rate (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, room_type_id INT NOT NULL, valid_from DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', valid_to DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', adults SMALLINT NOT NULL, children SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', board_type VARCHAR(120) DEFAULT NULL, currency VARCHAR(3) NOT NULL, price_per_night NUMERIC(12, 2) NOT NULL, priority INT DEFAULT 100 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_rate_hotel_active (hotel_id, active), INDEX idx_hotel_rate_room_type_active (room_type_id, active), INDEX idx_hotel_rate_validity (hotel_id, valid_from, valid_to), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hotel_room_type ADD CONSTRAINT FK_6BC2782C3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_rate ADD CONSTRAINT FK_3E3E41E93243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_rate ADD CONSTRAINT FK_3E3E41E954177093 FOREIGN KEY (room_type_id) REFERENCES hotel_room_type (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_rate DROP FOREIGN KEY FK_3E3E41E954177093');
        $this->addSql('ALTER TABLE hotel_rate DROP FOREIGN KEY FK_3E3E41E93243BB18');
        $this->addSql('ALTER TABLE hotel_room_type DROP FOREIGN KEY FK_6BC2782C3243BB18');
        $this->addSql('DROP TABLE hotel_rate');
        $this->addSql('DROP TABLE hotel_room_type');
    }
}
