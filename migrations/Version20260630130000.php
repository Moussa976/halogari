<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les informations vehicule au profil conducteur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD vehicle_brand VARCHAR(80) DEFAULT NULL, ADD vehicle_model VARCHAR(80) DEFAULT NULL, ADD vehicle_color VARCHAR(50) DEFAULT NULL, ADD vehicle_seats INT DEFAULT NULL, ADD vehicle_photo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP vehicle_brand, DROP vehicle_model, DROP vehicle_color, DROP vehicle_seats, DROP vehicle_photo');
    }
}
