<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l historique des SMS envoyes aux passagers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sms_log (id INT AUTO_INCREMENT NOT NULL, reservation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, phone VARCHAR(40) NOT NULL, event_type VARCHAR(40) NOT NULL, message LONGTEXT NOT NULL, provider VARCHAR(40) DEFAULT NULL, status VARCHAR(20) NOT NULL, provider_message_id VARCHAR(120) DEFAULT NULL, error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A9E43D70B83297E7 (reservation_id), INDEX IDX_A9E43D70A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sms_log ADD CONSTRAINT FK_8CBF7AEFB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sms_log ADD CONSTRAINT FK_8CBF7AEFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sms_log DROP FOREIGN KEY FK_8CBF7AEFB83297E7');
        $this->addSql('ALTER TABLE sms_log DROP FOREIGN KEY FK_8CBF7AEFA76ED395');
        $this->addSql('DROP TABLE sms_log');
    }
}
