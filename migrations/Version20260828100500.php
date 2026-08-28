<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Flight Commerce generated index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE airline RENAME INDEX uniq_9c9b6dec3d151c2d TO UNIQ_EC141EF83D151C2D');
        $this->addSql('ALTER TABLE airline RENAME INDEX uniq_9c9b6dec70dcc949 TO UNIQ_EC141EF870DCC949');
        $this->addSql('ALTER TABLE airline RENAME INDEX idx_9c9b6decf92f3e70 TO IDX_EC141EF8F92F3E70');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE airline RENAME INDEX UNIQ_EC141EF83D151C2D TO uniq_9c9b6dec3d151c2d');
        $this->addSql('ALTER TABLE airline RENAME INDEX UNIQ_EC141EF870DCC949 TO uniq_9c9b6dec70dcc949');
        $this->addSql('ALTER TABLE airline RENAME INDEX IDX_EC141EF8F92F3E70 TO idx_9c9b6decf92f3e70');
    }
}
