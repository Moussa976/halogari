<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des trajets et l’historique des événements de paiement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE trajet ADD statut_suivi VARCHAR(30) DEFAULT 'auto' NOT NULL, ADD validated_at DATETIME DEFAULT NULL, ADD disputed_at DATETIME DEFAULT NULL, ADD status_updated_at DATETIME DEFAULT NULL, ADD status_note LONGTEXT DEFAULT NULL");
        $this->addSql('CREATE TABLE paiement_evenement (id INT AUTO_INCREMENT NOT NULL, paiement_id INT NOT NULL, acteur_id INT DEFAULT NULL, type VARCHAR(60) NOT NULL, titre VARCHAR(160) NOT NULL, message LONGTEXT DEFAULT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6F6E3C587F06C7E0 (paiement_id), INDEX IDX_6F6E3C58E1C84C1E (acteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE paiement_evenement ADD CONSTRAINT FK_6F6E3C587F06C7E0 FOREIGN KEY (paiement_id) REFERENCES paiement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement_evenement ADD CONSTRAINT FK_6F6E3C58E1C84C1E FOREIGN KEY (acteur_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE paiement_evenement');
        $this->addSql('ALTER TABLE trajet DROP statut_suivi, DROP validated_at, DROP disputed_at, DROP status_updated_at, DROP status_note');
    }
}
