<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Activity and Transfer commerce entities (Phase 9).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE activity (
              id INT AUTO_INCREMENT NOT NULL,
              city_id INT NOT NULL,
              name VARCHAR(180) NOT NULL,
              name_fa VARCHAR(180) DEFAULT NULL,
              slug VARCHAR(180) NOT NULL,
              category VARCHAR(30) NOT NULL,
              short_description VARCHAR(500) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              duration_minutes SMALLINT DEFAULT NULL,
              meeting_point_text VARCHAR(255) DEFAULT NULL,
              latitude NUMERIC(10, 7) DEFAULT NULL,
              longitude NUMERIC(10, 7) DEFAULT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              featured TINYINT(1) DEFAULT 0 NOT NULL,
              public_visible TINYINT(1) DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX idx_activity_city (city_id),
              INDEX idx_activity_category (category),
              INDEX idx_activity_public_order (active, public_visible, featured),
              UNIQUE INDEX uniq_activity_slug (slug),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_image (
              id INT AUTO_INCREMENT NOT NULL,
              activity_id INT NOT NULL,
              path VARCHAR(1024) NOT NULL,
              alt VARCHAR(255) DEFAULT NULL,
              alt_fa VARCHAR(255) DEFAULT NULL,
              position INT DEFAULT 0 NOT NULL,
              is_primary TINYINT(1) DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_DB3F32EC81C06096 (activity_id),
              INDEX idx_activity_image_activity_primary (activity_id, is_primary),
              UNIQUE INDEX uniq_activity_image_position (activity_id, position),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_offer (
              id INT AUTO_INCREMENT NOT NULL,
              activity_id INT NOT NULL,
              valid_from DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
              valid_to DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
              specific_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
              currency VARCHAR(3) NOT NULL,
              pricing_mode VARCHAR(255) NOT NULL,
              adult_price NUMERIC(12, 2) DEFAULT NULL,
              child_price NUMERIC(12, 2) DEFAULT NULL,
              infant_price NUMERIC(12, 2) DEFAULT NULL,
              total_price NUMERIC(12, 2) DEFAULT NULL,
              minimum_participants SMALLINT DEFAULT NULL,
              maximum_participants SMALLINT DEFAULT NULL,
              availability_status VARCHAR(20) NOT NULL,
              priority INT DEFAULT 100 NOT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_37D4B18D81C06096 (activity_id),
              INDEX idx_activity_offer_activity_active (activity_id, active),
              INDEX idx_activity_offer_validity (valid_from, valid_to),
              INDEX idx_activity_offer_specific_date (specific_date),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE transfer_offer (
              id INT AUTO_INCREMENT NOT NULL,
              transfer_product_id INT NOT NULL,
              valid_from DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
              valid_to DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
              currency VARCHAR(3) NOT NULL,
              pricing_mode VARCHAR(20) NOT NULL,
              total_price NUMERIC(12, 2) DEFAULT NULL,
              availability_status VARCHAR(20) NOT NULL,
              priority INT DEFAULT 100 NOT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_8A5272669F4958B3 (transfer_product_id),
              INDEX idx_transfer_offer_product_active (transfer_product_id, active),
              INDEX idx_transfer_offer_validity (valid_from, valid_to),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE transfer_product (
              id INT AUTO_INCREMENT NOT NULL,
              origin_airport_id INT DEFAULT NULL,
              origin_city_id INT DEFAULT NULL,
              origin_hotel_id INT DEFAULT NULL,
              destination_airport_id INT DEFAULT NULL,
              destination_city_id INT DEFAULT NULL,
              destination_hotel_id INT DEFAULT NULL,
              name VARCHAR(180) NOT NULL,
              name_fa VARCHAR(180) DEFAULT NULL,
              transfer_type VARCHAR(20) NOT NULL,
              vehicle_type VARCHAR(20) NOT NULL,
              max_passengers SMALLINT DEFAULT NULL,
              max_luggage SMALLINT DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              active TINYINT(1) DEFAULT 1 NOT NULL,
              featured TINYINT(1) DEFAULT 0 NOT NULL,
              public_visible TINYINT(1) DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              INDEX IDX_6CFF76E691440985 (origin_airport_id),
              INDEX IDX_6CFF76E63EDB77C2 (origin_city_id),
              INDEX IDX_6CFF76E61F5D1E8 (origin_hotel_id),
              INDEX IDX_6CFF76E675A6C87A (destination_airport_id),
              INDEX IDX_6CFF76E6E5955DD7 (destination_city_id),
              INDEX IDX_6CFF76E66CF37B29 (destination_hotel_id),
              INDEX idx_transfer_product_origin (
                origin_airport_id, origin_city_id,
                origin_hotel_id
              ),
              INDEX idx_transfer_product_destination (
                destination_airport_id, destination_city_id,
                destination_hotel_id
              ),
              INDEX idx_transfer_product_public_order (active, public_visible, featured),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              activity
            ADD
              CONSTRAINT FK_AC74095A8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              activity_image
            ADD
              CONSTRAINT FK_DB3F32EC81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              activity_offer
            ADD
              CONSTRAINT FK_37D4B18D81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_offer
            ADD
              CONSTRAINT FK_8A5272669F4958B3 FOREIGN KEY (transfer_product_id) REFERENCES transfer_product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E691440985 FOREIGN KEY (origin_airport_id) REFERENCES airport (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E63EDB77C2 FOREIGN KEY (origin_city_id) REFERENCES city (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E61F5D1E8 FOREIGN KEY (origin_hotel_id) REFERENCES hotel (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E675A6C87A FOREIGN KEY (destination_airport_id) REFERENCES airport (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E6E5955DD7 FOREIGN KEY (destination_city_id) REFERENCES city (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              transfer_product
            ADD
              CONSTRAINT FK_6CFF76E66CF37B29 FOREIGN KEY (destination_hotel_id) REFERENCES hotel (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A8BAC62AF');
        $this->addSql('ALTER TABLE activity_image DROP FOREIGN KEY FK_DB3F32EC81C06096');
        $this->addSql('ALTER TABLE activity_offer DROP FOREIGN KEY FK_37D4B18D81C06096');
        $this->addSql('ALTER TABLE transfer_offer DROP FOREIGN KEY FK_8A5272669F4958B3');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E691440985');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E63EDB77C2');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E61F5D1E8');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E675A6C87A');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E6E5955DD7');
        $this->addSql('ALTER TABLE transfer_product DROP FOREIGN KEY FK_6CFF76E66CF37B29');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_image');
        $this->addSql('DROP TABLE activity_offer');
        $this->addSql('DROP TABLE transfer_offer');
        $this->addSql('DROP TABLE transfer_product');
    }
}
