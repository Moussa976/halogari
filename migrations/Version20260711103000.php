<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add boarding code tracking to reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD boarding_code VARCHAR(12) DEFAULT NULL, ADD boarding_code_created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD boarding_validated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD boarding_validated_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_RESERVATION_BOARDING_VALIDATED_BY ON reservation (boarding_validated_by_id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_RESERVATION_BOARDING_VALIDATED_BY FOREIGN KEY (boarding_validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_RESERVATION_BOARDING_VALIDATED_BY');
        $this->addSql('DROP INDEX IDX_RESERVATION_BOARDING_VALIDATED_BY ON reservation');
        $this->addSql('ALTER TABLE reservation DROP boarding_code, DROP boarding_code_created_at, DROP boarding_validated_at, DROP boarding_validated_by_id');
    }
}
