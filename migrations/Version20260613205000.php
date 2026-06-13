<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613205000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renforce le suivi de validation des documents utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD reviewed_by_id INT DEFAULT NULL, ADD original_filename VARCHAR(255) DEFAULT NULL, ADD mime_type VARCHAR(120) DEFAULT NULL, ADD file_size INT DEFAULT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_D8698A76F2FDE151 ON document (reviewed_by_id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76F2FDE151 FOREIGN KEY (reviewed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76F2FDE151');
        $this->addSql('DROP INDEX IDX_D8698A76F2FDE151 ON document');
        $this->addSql('ALTER TABLE document DROP reviewed_by_id, DROP original_filename, DROP mime_type, DROP file_size, DROP rejection_reason, DROP reviewed_at');
    }
}
