<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les alertes e-mail pour les recherches de trajet sans resultat.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE trajet_alert (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, matched_trajet_id INT DEFAULT NULL, depart VARCHAR(120) NOT NULL, arrivee VARCHAR(120) NOT NULL, date_trajet DATE NOT NULL, places INT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', notified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_79D6E4BA76ED395 (user_id), INDEX IDX_79D6E4B6D81716B (matched_trajet_id), INDEX trajet_alert_search_idx (depart, arrivee, date_trajet, active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE trajet_alert ADD CONSTRAINT FK_79D6E4BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trajet_alert ADD CONSTRAINT FK_79D6E4B6D81716B FOREIGN KEY (matched_trajet_id) REFERENCES trajet (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE trajet_alert');
    }
}
