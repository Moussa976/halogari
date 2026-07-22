<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add anonymous visitor statistics tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE visitor_profile (id INT AUTO_INCREMENT NOT NULL, visitor_key VARCHAR(64) NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', page_views INT NOT NULL, last_path VARCHAR(255) DEFAULT NULL, user_agent_hash VARCHAR(64) DEFAULT NULL, UNIQUE INDEX uniq_visitor_profile_key (visitor_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visitor_daily_stat (id INT AUTO_INCREMENT NOT NULL, visited_on DATE NOT NULL, unique_visitors INT NOT NULL, page_views INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_visitor_daily_stat_day (visited_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visitor_daily_visit (id INT AUTO_INCREMENT NOT NULL, visitor_profile_id INT NOT NULL, visited_on DATE NOT NULL, page_views INT NOT NULL, first_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_VISITOR_DAILY_VISIT_PROFILE (visitor_profile_id), UNIQUE INDEX uniq_visitor_daily_visit (visitor_profile_id, visited_on), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE visitor_daily_visit ADD CONSTRAINT FK_VISITOR_DAILY_VISIT_PROFILE FOREIGN KEY (visitor_profile_id) REFERENCES visitor_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visitor_daily_visit DROP FOREIGN KEY FK_VISITOR_DAILY_VISIT_PROFILE');
        $this->addSql('DROP TABLE visitor_daily_visit');
        $this->addSql('DROP TABLE visitor_daily_stat');
        $this->addSql('DROP TABLE visitor_profile');
    }
}
