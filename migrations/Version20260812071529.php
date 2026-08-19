<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812071529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_setting (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE controller_action (id INT AUTO_INCREMENT NOT NULL, controller VARCHAR(255) NOT NULL, action VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, menu_category_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, route VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, category VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_7D053A937ABA83AE (menu_category_id), INDEX IDX_7D053A93727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_permissions (menu_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_4511D223CCD7E912 (menu_id), INDEX IDX_4511D223FED90CCA (permission_id), PRIMARY KEY(menu_id, permission_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_category (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(180) NOT NULL, label_key VARCHAR(180) DEFAULT NULL, icon VARCHAR(180) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_2A1D5C5777153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, route VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE permission_controller_action (permission_id INT NOT NULL, controller_action_id INT NOT NULL, INDEX IDX_638CE86CFED90CCA (permission_id), INDEX IDX_638CE86C17BBBD1C (controller_action_id), PRIMARY KEY(permission_id, controller_action_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_57698A6A727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role_permission (role_id INT NOT NULL, permission_id INT NOT NULL, INDEX IDX_6F7DF886D60322AC (role_id), INDEX IDX_6F7DF886FED90CCA (permission_id), PRIMARY KEY(role_id, permission_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_entity (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_6B7A5F55E7927C74 (email), INDEX IDX_6B7A5F55B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_roles (user_entity_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_54FCD59F81C5F0B9 (user_entity_id), INDEX IDX_54FCD59FD60322AC (role_id), PRIMARY KEY(user_entity_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A937ABA83AE FOREIGN KEY (menu_category_id) REFERENCES menu_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A93727ACA70 FOREIGN KEY (parent_id) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE menu_permissions ADD CONSTRAINT FK_4511D223CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_permissions ADD CONSTRAINT FK_4511D223FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission_controller_action ADD CONSTRAINT FK_638CE86CFED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission_controller_action ADD CONSTRAINT FK_638CE86C17BBBD1C FOREIGN KEY (controller_action_id) REFERENCES controller_action (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6A727ACA70 FOREIGN KEY (parent_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886FED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_entity ADD CONSTRAINT FK_6B7A5F55B03A8386 FOREIGN KEY (created_by_id) REFERENCES user_entity (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59F81C5F0B9 FOREIGN KEY (user_entity_id) REFERENCES user_entity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A937ABA83AE');
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A93727ACA70');
        $this->addSql('ALTER TABLE menu_permissions DROP FOREIGN KEY FK_4511D223CCD7E912');
        $this->addSql('ALTER TABLE menu_permissions DROP FOREIGN KEY FK_4511D223FED90CCA');
        $this->addSql('ALTER TABLE permission_controller_action DROP FOREIGN KEY FK_638CE86CFED90CCA');
        $this->addSql('ALTER TABLE permission_controller_action DROP FOREIGN KEY FK_638CE86C17BBBD1C');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6A727ACA70');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886D60322AC');
        $this->addSql('ALTER TABLE role_permission DROP FOREIGN KEY FK_6F7DF886FED90CCA');
        $this->addSql('ALTER TABLE user_entity DROP FOREIGN KEY FK_6B7A5F55B03A8386');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59F81C5F0B9');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY FK_54FCD59FD60322AC');
        $this->addSql('DROP TABLE app_setting');
        $this->addSql('DROP TABLE controller_action');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_permissions');
        $this->addSql('DROP TABLE menu_category');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE permission_controller_action');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE role_permission');
        $this->addSql('DROP TABLE user_entity');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
