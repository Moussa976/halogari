<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des rappels de notation après trajet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD passenger_rating_reminder_sent_at DATETIME DEFAULT NULL, ADD driver_rating_reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP passenger_rating_reminder_sent_at, DROP driver_rating_reminder_sent_at');
    }
}
