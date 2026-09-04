<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Flight Commerce airline, offer, and offer-leg tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE airline (id INT AUTO_INCREMENT NOT NULL, country_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, iata_code VARCHAR(3) DEFAULT NULL, icao_code VARCHAR(4) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_airline_active (active), INDEX idx_airline_iata (iata_code), INDEX idx_airline_icao (icao_code), INDEX idx_airline_name (name), INDEX IDX_9C9B6DECF92F3E70 (country_id), UNIQUE INDEX UNIQ_9C9B6DEC3D151C2D (iata_code), UNIQUE INDEX UNIQ_9C9B6DEC70DCC949 (icao_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flight_offer (id INT AUTO_INCREMENT NOT NULL, search_source_id INT DEFAULT NULL, source_type VARCHAR(255) NOT NULL, provider_code VARCHAR(64) DEFAULT NULL, external_offer_id VARCHAR(190) DEFAULT NULL, trip_type VARCHAR(255) NOT NULL, pricing_mode VARCHAR(255) NOT NULL, adults SMALLINT NOT NULL, children SMALLINT NOT NULL, infants SMALLINT NOT NULL, cabin_class VARCHAR(255) NOT NULL, currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) DEFAULT NULL, adult_price NUMERIC(12, 2) DEFAULT NULL, child_price NUMERIC(12, 2) DEFAULT NULL, infant_price NUMERIC(12, 2) DEFAULT NULL, baggage LONGTEXT DEFAULT NULL, booking_url VARCHAR(2048) DEFAULT NULL, availability_status VARCHAR(255) NOT NULL, priority INT DEFAULT 100 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, valid_from DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', valid_to DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', fetched_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_flight_offer_active_priority (active, priority), INDEX idx_flight_offer_freshness (fetched_at, expires_at), INDEX idx_flight_offer_search_source (search_source_id), INDEX idx_flight_offer_source_type (source_type), INDEX idx_flight_offer_trip_cabin (trip_type, cabin_class), INDEX idx_flight_offer_validity (valid_from, valid_to), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flight_offer_leg (id INT AUTO_INCREMENT NOT NULL, flight_offer_id INT NOT NULL, airline_id INT DEFAULT NULL, origin_airport_id INT NOT NULL, destination_airport_id INT NOT NULL, direction VARCHAR(255) NOT NULL, segment_index SMALLINT NOT NULL, flight_number VARCHAR(16) DEFAULT NULL, departure_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', arrival_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', duration_minutes INT DEFAULT NULL, aircraft VARCHAR(120) DEFAULT NULL, metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_flight_offer_leg_airline (airline_id), INDEX idx_flight_offer_leg_destination (destination_airport_id), INDEX idx_flight_offer_leg_offer_order (flight_offer_id, direction, segment_index), INDEX idx_flight_offer_leg_origin (origin_airport_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE airline ADD CONSTRAINT FK_9C9B6DECF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flight_offer ADD CONSTRAINT FK_FC194F5F2E270CC9 FOREIGN KEY (search_source_id) REFERENCES search_source (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE flight_offer_leg ADD CONSTRAINT FK_6E9F20F3B60F42B0 FOREIGN KEY (flight_offer_id) REFERENCES flight_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE flight_offer_leg ADD CONSTRAINT FK_6E9F20F3C2521EA FOREIGN KEY (airline_id) REFERENCES airline (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE flight_offer_leg ADD CONSTRAINT FK_6E9F20F31A98875E FOREIGN KEY (origin_airport_id) REFERENCES airport (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE flight_offer_leg ADD CONSTRAINT FK_6E9F20F398B3DB87 FOREIGN KEY (destination_airport_id) REFERENCES airport (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flight_offer_leg DROP FOREIGN KEY FK_6E9F20F3B60F42B0');
        $this->addSql('ALTER TABLE flight_offer_leg DROP FOREIGN KEY FK_6E9F20F3C2521EA');
        $this->addSql('ALTER TABLE flight_offer_leg DROP FOREIGN KEY FK_6E9F20F31A98875E');
        $this->addSql('ALTER TABLE flight_offer_leg DROP FOREIGN KEY FK_6E9F20F398B3DB87');
        $this->addSql('ALTER TABLE flight_offer DROP FOREIGN KEY FK_FC194F5F2E270CC9');
        $this->addSql('ALTER TABLE airline DROP FOREIGN KEY FK_9C9B6DECF92F3E70');
        $this->addSql('DROP TABLE flight_offer_leg');
        $this->addSql('DROP TABLE flight_offer');
        $this->addSql('DROP TABLE airline');
    }
}
