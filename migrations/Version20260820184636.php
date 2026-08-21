<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820184636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE breach_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, feed_url VARCHAR(255) NOT NULL, guid VARCHAR(512) NOT NULL, title VARCHAR(500) NOT NULL, link VARCHAR(1000) NOT NULL, content CLOB NOT NULL, categories CLOB NOT NULL, published_at DATETIME DEFAULT NULL, first_seen_at DATETIME NOT NULL, content_hash VARCHAR(64) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_breach_item_guid ON breach_item (guid)');
        $this->addSql('CREATE TABLE breach_match (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, matched_term VARCHAR(255) NOT NULL, matched_field VARCHAR(20) NOT NULL, snippet CLOB NOT NULL, detected_at DATETIME NOT NULL, notified_at DATETIME DEFAULT NULL, breach_item_id INTEGER NOT NULL, CONSTRAINT FK_C2B8A8F9996559E9 FOREIGN KEY (breach_item_id) REFERENCES breach_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C2B8A8F9996559E9 ON breach_match (breach_item_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_breach_match_item_company ON breach_match (breach_item_id, company_name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE breach_item');
        $this->addSql('DROP TABLE breach_match');
    }
}
