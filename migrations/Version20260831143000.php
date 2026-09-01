<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Tour Commerce package and package image tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tour_package (id INT AUTO_INCREMENT NOT NULL, origin_airport_id INT DEFAULT NULL, destination_city_id INT NOT NULL, flight_offer_id INT DEFAULT NULL, hotel_id INT DEFAULT NULL, hotel_room_type_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, slug VARCHAR(180) NOT NULL, valid_from DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', valid_to DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', departure_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', return_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', nights SMALLINT NOT NULL, days SMALLINT DEFAULT NULL, adults SMALLINT NOT NULL, children SMALLINT NOT NULL, infants SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', pricing_mode VARCHAR(255) NOT NULL, currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) DEFAULT NULL, adult_price NUMERIC(12, 2) DEFAULT NULL, child_price NUMERIC(12, 2) DEFAULT NULL, infant_price NUMERIC(12, 2) DEFAULT NULL, board_type VARCHAR(120) DEFAULT NULL, inclusions JSON NOT NULL COMMENT \'(DC2Type:json)\', exclusions JSON NOT NULL COMMENT \'(DC2Type:json)\', priority INT DEFAULT 100 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, featured TINYINT(1) DEFAULT 0 NOT NULL, public_visible TINYINT(1) DEFAULT 0 NOT NULL, short_description VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_tour_package_dates (departure_date, return_date), INDEX idx_tour_package_destination (destination_city_id), INDEX idx_tour_package_occupancy (adults, children, infants), INDEX idx_tour_package_origin (origin_airport_id), INDEX idx_tour_package_public_order (active, public_visible, featured, priority), INDEX idx_tour_package_validity (valid_from, valid_to), INDEX IDX_TOUR_PACKAGE_FLIGHT_OFFER (flight_offer_id), INDEX IDX_TOUR_PACKAGE_HOTEL (hotel_id), INDEX IDX_TOUR_PACKAGE_ROOM_TYPE (hotel_room_type_id), UNIQUE INDEX uniq_tour_package_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tour_package_image (id INT AUTO_INCREMENT NOT NULL, tour_package_id INT NOT NULL, path VARCHAR(1024) NOT NULL, alt VARCHAR(255) DEFAULT NULL, alt_fa VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, is_primary TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_tour_package_image_package_primary (tour_package_id, is_primary), UNIQUE INDEX uniq_tour_package_image_position (tour_package_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tour_package ADD CONSTRAINT FK_TOUR_PACKAGE_ORIGIN_AIRPORT FOREIGN KEY (origin_airport_id) REFERENCES airport (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tour_package ADD CONSTRAINT FK_TOUR_PACKAGE_DESTINATION_CITY FOREIGN KEY (destination_city_id) REFERENCES city (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tour_package ADD CONSTRAINT FK_TOUR_PACKAGE_FLIGHT_OFFER FOREIGN KEY (flight_offer_id) REFERENCES flight_offer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tour_package ADD CONSTRAINT FK_TOUR_PACKAGE_HOTEL FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tour_package ADD CONSTRAINT FK_TOUR_PACKAGE_ROOM_TYPE FOREIGN KEY (hotel_room_type_id) REFERENCES hotel_room_type (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tour_package_image ADD CONSTRAINT FK_TOUR_PACKAGE_IMAGE_PACKAGE FOREIGN KEY (tour_package_id) REFERENCES tour_package (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour_package_image DROP FOREIGN KEY FK_TOUR_PACKAGE_IMAGE_PACKAGE');
        $this->addSql('ALTER TABLE tour_package DROP FOREIGN KEY FK_TOUR_PACKAGE_ORIGIN_AIRPORT');
        $this->addSql('ALTER TABLE tour_package DROP FOREIGN KEY FK_TOUR_PACKAGE_DESTINATION_CITY');
        $this->addSql('ALTER TABLE tour_package DROP FOREIGN KEY FK_TOUR_PACKAGE_FLIGHT_OFFER');
        $this->addSql('ALTER TABLE tour_package DROP FOREIGN KEY FK_TOUR_PACKAGE_HOTEL');
        $this->addSql('ALTER TABLE tour_package DROP FOREIGN KEY FK_TOUR_PACKAGE_ROOM_TYPE');
        $this->addSql('DROP TABLE tour_package_image');
        $this->addSql('DROP TABLE tour_package');
    }
}
