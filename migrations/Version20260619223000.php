<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260619223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des publications Facebook des trajets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trajet ADD facebook_post_id VARCHAR(120) DEFAULT NULL, ADD facebook_posted_at DATETIME DEFAULT NULL, ADD facebook_post_error LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trajet DROP facebook_post_id, DROP facebook_posted_at, DROP facebook_post_error');
    }
}
