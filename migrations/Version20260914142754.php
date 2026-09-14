<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914142754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta ADD destinazione_macro VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD destinazione_micro VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD tipologie JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD temi JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta DROP destinazione_macro');
        $this->addSql('ALTER TABLE offerta DROP destinazione_micro');
        $this->addSql('ALTER TABLE offerta DROP tipologie');
        $this->addSql('ALTER TABLE offerta DROP temi');
    }
}
