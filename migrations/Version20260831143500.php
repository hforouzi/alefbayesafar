<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831143500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Tour Commerce generated index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour_package RENAME INDEX idx_tour_package_flight_offer TO IDX_9E82E2144A29AE7C');
        $this->addSql('ALTER TABLE tour_package RENAME INDEX idx_tour_package_hotel TO IDX_9E82E2143243BB18');
        $this->addSql('ALTER TABLE tour_package RENAME INDEX idx_tour_package_room_type TO IDX_9E82E2148261C360');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour_package RENAME INDEX IDX_9E82E2144A29AE7C TO idx_tour_package_flight_offer');
        $this->addSql('ALTER TABLE tour_package RENAME INDEX IDX_9E82E2143243BB18 TO idx_tour_package_hotel');
        $this->addSql('ALTER TABLE tour_package RENAME INDEX IDX_9E82E2148261C360 TO idx_tour_package_room_type');
    }
}
