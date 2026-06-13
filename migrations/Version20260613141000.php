<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le journal admin et promeut le compte principal en superadmin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_audit_log (id INT AUTO_INCREMENT NOT NULL, actor_id INT DEFAULT NULL, target_user_id INT DEFAULT NULL, action VARCHAR(120) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E6F2F31610DAF24A (actor_id), INDEX IDX_E6F2F3168D9F6D38 (target_user_id), INDEX IDX_E6F2F3168D93D649 (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_audit_log ADD CONSTRAINT FK_E6F2F31610DAF24A FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE admin_audit_log ADD CONSTRAINT FK_E6F2F3168D9F6D38 FOREIGN KEY (target_user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql("UPDATE user SET roles = '[\"ROLE_SUPER_ADMIN\"]' WHERE email IN ('moussa@halogari.yt', 'moussainssa@outlook.fr')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_audit_log DROP FOREIGN KEY FK_E6F2F31610DAF24A');
        $this->addSql('ALTER TABLE admin_audit_log DROP FOREIGN KEY FK_E6F2F3168D9F6D38');
        $this->addSql('DROP TABLE admin_audit_log');
    }
}
