<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Hotel Offer pricing table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hotel_offer (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, search_source_id INT NOT NULL, provider_code VARCHAR(64) NOT NULL, external_offer_id VARCHAR(190) DEFAULT NULL, check_in DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', check_out DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', adults SMALLINT NOT NULL, children SMALLINT NOT NULL, room_name VARCHAR(255) DEFAULT NULL, board_type VARCHAR(120) DEFAULT NULL, currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) NOT NULL, booking_url VARCHAR(2048) DEFAULT NULL, availability_status VARCHAR(32) DEFAULT NULL, fetched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_offer_hotel (hotel_id), INDEX idx_hotel_offer_search_source (search_source_id), INDEX idx_hotel_offer_fetched_at (fetched_at), INDEX idx_hotel_offer_stay_travelers (hotel_id, check_in, check_out, adults, children), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA2E270CC9 FOREIGN KEY (search_source_id) REFERENCES search_source (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_offer DROP FOREIGN KEY FK_CA0249FA3243BB18');
        $this->addSql('ALTER TABLE hotel_offer DROP FOREIGN KEY FK_CA0249FA2E270CC9');
        $this->addSql('DROP TABLE hotel_offer');
    }
}
