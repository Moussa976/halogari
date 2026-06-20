<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le gel des comptes avant suppression definitive.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD disabled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD scheduled_deletion_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD disabled_reason VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP disabled_at, DROP scheduled_deletion_at, DROP disabled_reason');
    }
}
