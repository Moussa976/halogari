<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une image optionnelle aux messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD image_filename VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP image_filename');
    }
}
