<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029112710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagna ADD slug VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE campagna ADD landing JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE campagna ADD offerta_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE campagna ADD CONSTRAINT FK_54F6990F7337635A FOREIGN KEY (offerta_id) REFERENCES offerta (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54F6990F989D9B62 ON campagna (slug)');
        $this->addSql('CREATE INDEX IDX_54F6990F7337635A ON campagna (offerta_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagna DROP CONSTRAINT FK_54F6990F7337635A');
        $this->addSql('DROP INDEX UNIQ_54F6990F989D9B62');
        $this->addSql('DROP INDEX IDX_54F6990F7337635A');
        $this->addSql('ALTER TABLE campagna DROP slug');
        $this->addSql('ALTER TABLE campagna DROP landing');
        $this->addSql('ALTER TABLE campagna DROP offerta_id');
    }
}
