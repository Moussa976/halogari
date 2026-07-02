<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des actions admin ouvertes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_seen_action (id INT AUTO_INCREMENT NOT NULL, admin_id INT NOT NULL, action_type VARCHAR(40) NOT NULL, item_id INT NOT NULL, seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9B7D0B82642B8210 (admin_id), UNIQUE INDEX uniq_admin_seen_action (admin_id, action_type, item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_seen_action ADD CONSTRAINT FK_9B7D0B82642B8210 FOREIGN KEY (admin_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_seen_action DROP FOREIGN KEY FK_9B7D0B82642B8210');
        $this->addSql('DROP TABLE admin_seen_action');
    }
}
