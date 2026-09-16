<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916183729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, subject_key VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, message CLOB DEFAULT NULL, data CLOB NOT NULL, author VARCHAR(80) NOT NULL, session_id VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, project_id INTEGER NOT NULL, ticket_id INTEGER DEFAULT NULL, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AC74095A8B8E8428 ON activity (created_at)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A700047D2 ON activity (ticket_id)');
        $this->addSql('CREATE TABLE epic (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "key" VARCHAR(20) NOT NULL, number INTEGER NOT NULL, title VARCHAR(200) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, start_date DATETIME DEFAULT NULL, target_date DATETIME DEFAULT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, initiative_id INTEGER NOT NULL, CONSTRAINT FK_19C95071166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_19C95071AB7D9771 FOREIGN KEY (initiative_id) REFERENCES initiative (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_19C950718A90ABA9 ON epic ("key")');
        $this->addSql('CREATE INDEX IDX_19C95071166D1F9C ON epic (project_id)');
        $this->addSql('CREATE INDEX IDX_19C95071AB7D9771 ON epic (initiative_id)');
        $this->addSql('CREATE TABLE initiative (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "key" VARCHAR(20) NOT NULL, number INTEGER NOT NULL, title VARCHAR(200) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, risk VARCHAR(10) NOT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_E115DEFE166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E115DEFE8A90ABA9 ON initiative ("key")');
        $this->addSql('CREATE INDEX IDX_E115DEFE166D1F9C ON initiative (project_id)');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "key" VARCHAR(10) NOT NULL, name VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, repository VARCHAR(255) DEFAULT NULL, ticket_sequence INTEGER NOT NULL, initiative_sequence INTEGER NOT NULL, epic_sequence INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE8A90ABA9 ON project ("key")');
        $this->addSql('CREATE TABLE sub_task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, done BOOLEAN NOT NULL, position INTEGER NOT NULL, ticket_id INTEGER NOT NULL, CONSTRAINT FK_75E844E4700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_75E844E4700047D2 ON sub_task (ticket_id)');
        $this->addSql('CREATE TABLE ticket (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "key" VARCHAR(20) NOT NULL, number INTEGER NOT NULL, title VARCHAR(200) NOT NULL, description CLOB DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(10) NOT NULL, story_points INTEGER DEFAULT NULL, labels CLOB NOT NULL, assignee VARCHAR(80) DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, epic_id INTEGER DEFAULT NULL, CONSTRAINT FK_97A0ADA3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_97A0ADA36B71E00E FOREIGN KEY (epic_id) REFERENCES epic (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA38A90ABA9 ON ticket ("key")');
        $this->addSql('CREATE INDEX IDX_97A0ADA37B00651C ON ticket (status)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3166D1F9C ON ticket (project_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA36B71E00E ON ticket (epic_id)');
        $this->addSql('CREATE TABLE ticket_dependency (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, source_id INTEGER NOT NULL, target_id INTEGER NOT NULL, CONSTRAINT FK_B9CBC751953C1C61 FOREIGN KEY (source_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B9CBC751158E0B66 FOREIGN KEY (target_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B9CBC751953C1C61158E0B668CDE5729 ON ticket_dependency (source_id, target_id, type)');
        $this->addSql('CREATE INDEX IDX_B9CBC751953C1C61 ON ticket_dependency (source_id)');
        $this->addSql('CREATE INDEX IDX_B9CBC751158E0B66 ON ticket_dependency (target_id)');
        $this->addSql('CREATE TABLE ticket_link (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) NOT NULL, reference VARCHAR(500) NOT NULL, label VARCHAR(200) DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INTEGER NOT NULL, CONSTRAINT FK_4778CC9700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4778CC9700047D2 ON ticket_link (ticket_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE epic');
        $this->addSql('DROP TABLE initiative');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE sub_task');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ticket_dependency');
        $this->addSql('DROP TABLE ticket_link');
    }
}
