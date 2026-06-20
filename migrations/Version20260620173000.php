<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l IBAN saisi avec le document RIB.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD rib_iban VARCHAR(34) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP rib_iban');
    }
}
