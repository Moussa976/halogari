<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi de l auteur des annulations de reservation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD canceled_by VARCHAR(30) DEFAULT NULL, ADD canceled_at DATETIME DEFAULT NULL, ADD cancellation_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP canceled_by, DROP canceled_at, DROP cancellation_reason');
    }
}
