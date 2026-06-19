<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les parametres plateforme pour la configuration Facebook.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_setting (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, value LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_platform_setting_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_setting');
    }
}
