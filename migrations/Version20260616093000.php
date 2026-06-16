<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le detail de repartition des commissions de paiement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commission ADD commission_halo_gari NUMERIC(10, 2) DEFAULT 0 NOT NULL, ADD montant_conducteur NUMERIC(10, 2) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commission DROP commission_halo_gari, DROP montant_conducteur');
    }
}
