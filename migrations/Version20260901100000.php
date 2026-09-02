<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external Tour offer snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE external_tour_offer (id INT AUTO_INCREMENT NOT NULL, search_source_id INT NOT NULL, origin_airport_id INT DEFAULT NULL, destination_city_id INT DEFAULT NULL, hotel_id INT DEFAULT NULL, hotel_room_type_id INT DEFAULT NULL, provider_code VARCHAR(64) NOT NULL, external_offer_id VARCHAR(190) DEFAULT NULL, title VARCHAR(220) NOT NULL, title_fa VARCHAR(220) DEFAULT NULL, origin_text VARCHAR(180) DEFAULT NULL, destination_text VARCHAR(180) NOT NULL, departure_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', return_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', valid_from DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', valid_to DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', nights SMALLINT DEFAULT NULL, days SMALLINT DEFAULT NULL, adults SMALLINT NOT NULL, children SMALLINT NOT NULL, infants SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', hotel_name VARCHAR(220) DEFAULT NULL, board_type VARCHAR(120) DEFAULT NULL, flight_summary VARCHAR(500) DEFAULT NULL, inclusions JSON NOT NULL COMMENT \'(DC2Type:json)\', exclusions JSON NOT NULL COMMENT \'(DC2Type:json)\', currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) NOT NULL, booking_url VARCHAR(2048) DEFAULT NULL, availability_status VARCHAR(255) NOT NULL, fetched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_external_tour_offer_dates (departure_date, return_date), INDEX idx_external_tour_offer_destination (destination_city_id), INDEX idx_external_tour_offer_occupancy (adults, children, infants), INDEX idx_external_tour_offer_origin (origin_airport_id), INDEX idx_external_tour_offer_source_context (search_source_id, expires_at), INDEX idx_external_tour_offer_validity (valid_from, valid_to), INDEX IDX_EXTERNAL_TOUR_OFFER_HOTEL (hotel_id), INDEX IDX_EXTERNAL_TOUR_OFFER_ROOM_TYPE (hotel_room_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE external_tour_offer ADD CONSTRAINT FK_EXTERNAL_TOUR_OFFER_SOURCE FOREIGN KEY (search_source_id) REFERENCES search_source (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE external_tour_offer ADD CONSTRAINT FK_EXTERNAL_TOUR_OFFER_ORIGIN_AIRPORT FOREIGN KEY (origin_airport_id) REFERENCES airport (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE external_tour_offer ADD CONSTRAINT FK_EXTERNAL_TOUR_OFFER_DESTINATION_CITY FOREIGN KEY (destination_city_id) REFERENCES city (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE external_tour_offer ADD CONSTRAINT FK_EXTERNAL_TOUR_OFFER_HOTEL FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE external_tour_offer ADD CONSTRAINT FK_EXTERNAL_TOUR_OFFER_ROOM_TYPE FOREIGN KEY (hotel_room_type_id) REFERENCES hotel_room_type (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_tour_offer DROP FOREIGN KEY FK_EXTERNAL_TOUR_OFFER_SOURCE');
        $this->addSql('ALTER TABLE external_tour_offer DROP FOREIGN KEY FK_EXTERNAL_TOUR_OFFER_ORIGIN_AIRPORT');
        $this->addSql('ALTER TABLE external_tour_offer DROP FOREIGN KEY FK_EXTERNAL_TOUR_OFFER_DESTINATION_CITY');
        $this->addSql('ALTER TABLE external_tour_offer DROP FOREIGN KEY FK_EXTERNAL_TOUR_OFFER_HOTEL');
        $this->addSql('ALTER TABLE external_tour_offer DROP FOREIGN KEY FK_EXTERNAL_TOUR_OFFER_ROOM_TYPE');
        $this->addSql('DROP TABLE external_tour_offer');
    }
}
