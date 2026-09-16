<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916200054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiches de session des agents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agent_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, session_id VARCHAR(100) NOT NULL, client VARCHAR(80) NOT NULL, title VARCHAR(200) DEFAULT NULL, summary CLOB DEFAULT NULL, summary_updated_at DATETIME DEFAULT NULL, branch VARCHAR(200) DEFAULT NULL, started_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_217B77E613FECDF ON agent_session (session_id)');
        $this->addSql('CREATE INDEX IDX_217B77EB81C492A ON agent_session (last_seen_at)');
    }

    /** Reconstruit les fiches des sessions déjà présentes dans le journal. */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO agent_session (session_id, client, started_at, last_seen_at)
            SELECT session_id, MAX(author), MIN(created_at), MAX(created_at)
            FROM activity
            WHERE session_id IS NOT NULL
            GROUP BY session_id
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent_session');
    }
}
