<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819132500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Destination Catalog generated index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE airport RENAME INDEX uniq_2dc0ad6d5fdf6f93 TO UNIQ_7E91F7C23D151C2D');
        $this->addSql('ALTER TABLE airport RENAME INDEX uniq_2dc0ad6d19d036b6 TO UNIQ_7E91F7C270DCC949');
        $this->addSql('ALTER TABLE airport RENAME INDEX idx_2dc0ad6d8bac62af TO IDX_7E91F7C28BAC62AF');
        $this->addSql('ALTER TABLE country RENAME INDEX uniq_5373c9663c24d15a TO UNIQ_5373C9661B6F9774');
        $this->addSql('ALTER TABLE country RENAME INDEX uniq_5373c96649d13143 TO UNIQ_5373C9666C68A7E2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE airport RENAME INDEX UNIQ_7E91F7C23D151C2D TO uniq_2dc0ad6d5fdf6f93');
        $this->addSql('ALTER TABLE airport RENAME INDEX UNIQ_7E91F7C270DCC949 TO uniq_2dc0ad6d19d036b6');
        $this->addSql('ALTER TABLE airport RENAME INDEX IDX_7E91F7C28BAC62AF TO idx_2dc0ad6d8bac62af');
        $this->addSql('ALTER TABLE country RENAME INDEX UNIQ_5373C9661B6F9774 TO uniq_5373c9663c24d15a');
        $this->addSql('ALTER TABLE country RENAME INDEX UNIQ_5373C9666C68A7E2 TO uniq_5373c96649d13143');
    }
}
