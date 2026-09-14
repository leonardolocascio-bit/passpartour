<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914145307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // default in SQL per popolare le righe esistenti; l'entità gestisce i default lato PHP
        $this->addSql("ALTER TABLE offerta ADD valuta VARCHAR(3) NOT NULL DEFAULT 'EUR'");
        $this->addSql('ALTER TABLE offerta ADD quote JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD supplemento_singola NUMERIC(12, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD early_booking BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE offerta ADD last_minute BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE offerta ADD cancellazione_gratuita BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE offerta ADD cancellazione_entro_giorni INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta DROP valuta');
        $this->addSql('ALTER TABLE offerta DROP quote');
        $this->addSql('ALTER TABLE offerta DROP supplemento_singola');
        $this->addSql('ALTER TABLE offerta DROP early_booking');
        $this->addSql('ALTER TABLE offerta DROP last_minute');
        $this->addSql('ALTER TABLE offerta DROP cancellazione_gratuita');
        $this->addSql('ALTER TABLE offerta DROP cancellazione_entro_giorni');
    }
}
