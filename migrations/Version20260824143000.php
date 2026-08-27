<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add child ages to Hotel Offer snapshot context.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_offer ADD children_ages JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('UPDATE hotel_offer SET children_ages = \'[]\' WHERE children_ages IS NULL');
        $this->addSql('ALTER TABLE hotel_offer CHANGE children_ages children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_offer DROP children_ages');
    }
}
