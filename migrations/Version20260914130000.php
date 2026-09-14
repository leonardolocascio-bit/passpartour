<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Varianti dell'offerta (durata/validità/partenza/righe, con prezzo).
 */
final class Version20260914130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offerta: varianti con prezzo (JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta ADD varianti JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta DROP varianti');
    }
}
