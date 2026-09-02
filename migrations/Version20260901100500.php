<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align external Tour offer foreign key index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_tour_offer RENAME INDEX idx_external_tour_offer_hotel TO IDX_86718C43243BB18');
        $this->addSql('ALTER TABLE external_tour_offer RENAME INDEX idx_external_tour_offer_room_type TO IDX_86718C48261C360');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_tour_offer RENAME INDEX IDX_86718C43243BB18 TO idx_external_tour_offer_hotel');
        $this->addSql('ALTER TABLE external_tour_offer RENAME INDEX IDX_86718C48261C360 TO idx_external_tour_offer_room_type');
    }
}
