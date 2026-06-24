<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l adresse postale utilisateur requise pour publier un trajet.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD postal_address_line1 VARCHAR(255) DEFAULT NULL, ADD postal_address_line2 VARCHAR(255) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD postal_city VARCHAR(120) DEFAULT NULL, ADD postal_country VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP postal_address_line1, DROP postal_address_line2, DROP postal_code, DROP postal_city, DROP postal_country');
    }
}
