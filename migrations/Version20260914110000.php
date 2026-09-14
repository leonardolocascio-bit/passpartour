<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Impaginatore social/stampa: claim + stato JSON (slide carosello, righe, caption).
 */
final class Version20260914110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offerta: claim e stato impaginatore (JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta ADD claim VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD impaginato JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta DROP claim');
        $this->addSql('ALTER TABLE offerta DROP impaginato');
    }
}
