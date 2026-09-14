<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914144450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta ADD voli JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD bagaglio JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD treni JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD navi JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE offerta ADD trasferimenti JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE offerta DROP voli');
        $this->addSql('ALTER TABLE offerta DROP bagaglio');
        $this->addSql('ALTER TABLE offerta DROP treni');
        $this->addSql('ALTER TABLE offerta DROP navi');
        $this->addSql('ALTER TABLE offerta DROP trasferimenti');
    }
}
