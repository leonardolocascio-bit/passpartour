<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720065930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tappa_viaggio ADD giorno_a INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tappa_viaggio ADD data DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE tappa_viaggio ADD data_a DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD da VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD a VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD orario_partenza VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD orario_arrivo VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD nome_struttura VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD stelle INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD indirizzo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD trattamento VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD data_inizio DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD orario_inizio VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD data_fine DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD orario_fine VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE voce_costo ADD note TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tappa_viaggio DROP giorno_a');
        $this->addSql('ALTER TABLE tappa_viaggio DROP data');
        $this->addSql('ALTER TABLE tappa_viaggio DROP data_a');
        $this->addSql('ALTER TABLE voce_costo DROP da');
        $this->addSql('ALTER TABLE voce_costo DROP a');
        $this->addSql('ALTER TABLE voce_costo DROP orario_partenza');
        $this->addSql('ALTER TABLE voce_costo DROP orario_arrivo');
        $this->addSql('ALTER TABLE voce_costo DROP nome_struttura');
        $this->addSql('ALTER TABLE voce_costo DROP stelle');
        $this->addSql('ALTER TABLE voce_costo DROP indirizzo');
        $this->addSql('ALTER TABLE voce_costo DROP trattamento');
        $this->addSql('ALTER TABLE voce_costo DROP data_inizio');
        $this->addSql('ALTER TABLE voce_costo DROP orario_inizio');
        $this->addSql('ALTER TABLE voce_costo DROP data_fine');
        $this->addSql('ALTER TABLE voce_costo DROP orario_fine');
        $this->addSql('ALTER TABLE voce_costo DROP note');
    }
}
