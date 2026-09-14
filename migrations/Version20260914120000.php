<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Righe/opzioni del viaggio sull'offerta (compilate nel configuratore,
 * ereditate dall'impaginatore).
 */
final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offerta: righe/opzioni del viaggio (JSON)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta ADD righe JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offerta DROP righe');
    }
}
