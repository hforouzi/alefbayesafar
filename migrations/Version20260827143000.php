<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hotel room type import attribution and catalogue facts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_room_type ADD description_original LONGTEXT DEFAULT NULL, ADD description_fa LONGTEXT DEFAULT NULL, ADD bed_configuration VARCHAR(255) DEFAULT NULL, ADD size_sqm NUMERIC(7, 2) DEFAULT NULL, ADD source VARCHAR(64) DEFAULT NULL, ADD external_id VARCHAR(190) DEFAULT NULL, ADD source_url VARCHAR(2048) DEFAULT NULL, ADD source_name VARCHAR(180) DEFAULT NULL, ADD metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE max_adults max_adults SMALLINT DEFAULT NULL, CHANGE max_children max_children SMALLINT DEFAULT NULL');
        $this->addSql('UPDATE hotel_room_type SET metadata = JSON_OBJECT() WHERE metadata IS NULL');
        $this->addSql('ALTER TABLE hotel_room_type CHANGE metadata metadata JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('CREATE INDEX idx_hotel_room_type_source_external ON hotel_room_type (hotel_id, source, external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_hotel_room_type_source_external ON hotel_room_type');
        $this->addSql('ALTER TABLE hotel_room_type DROP description_original, DROP description_fa, DROP bed_configuration, DROP size_sqm, DROP source, DROP external_id, DROP source_url, DROP source_name, DROP metadata, CHANGE max_adults max_adults SMALLINT NOT NULL, CHANGE max_children max_children SMALLINT NOT NULL');
    }
}
