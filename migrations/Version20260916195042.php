<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Activity\SearchText;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916195042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonne de recherche normalisée du journal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD COLUMN search_text CLOB DEFAULT \'\' NOT NULL');
    }

    /** Indexe les entrées existantes avec la même normalisation que l'entité. */
    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.id, a.subject_key, a.message, a.data, t.title FROM activity a LEFT JOIN ticket t ON t.id = a.ticket_id'
        );

        foreach ($rows as $row) {
            $data = json_decode((string) $row['data'], true) ?: [];
            $this->connection->update('activity', [
                'search_text' => SearchText::normalize($row['subject_key'], $row['message'], \is_string($data['title'] ?? null) ? $data['title'] : null, $row['title']),
            ], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, subject_key, type, message, data, author, session_id, created_at, project_id, ticket_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, subject_key VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, message CLOB DEFAULT NULL, data CLOB NOT NULL, author VARCHAR(80) NOT NULL, session_id VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, project_id INTEGER NOT NULL, ticket_id INTEGER DEFAULT NULL, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, subject_key, type, message, data, author, session_id, created_at, project_id, ticket_id) SELECT id, subject_key, type, message, data, author, session_id, created_at, project_id, ticket_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8B8E8428 ON activity (created_at)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A700047D2 ON activity (ticket_id)');
    }
}
