<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le montant remboursé sur les paiements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE paiement ADD montant_rembourse NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE paiement DROP montant_rembourse');
    }
}
