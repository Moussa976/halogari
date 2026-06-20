<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des actions admin envoyees dans le resume hebdomadaire.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_audit_log ADD digest_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_audit_log DROP digest_sent_at');
    }
}
