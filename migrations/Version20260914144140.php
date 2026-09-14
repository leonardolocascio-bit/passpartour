<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914144140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta ADD alloggio JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD assicurazioni JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD comprende TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD non_comprende TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta DROP alloggio');
        $this->addSql('ALTER TABLE offerta DROP assicurazioni');
        $this->addSql('ALTER TABLE offerta DROP comprende');
        $this->addSql('ALTER TABLE offerta DROP non_comprende');
    }
}
