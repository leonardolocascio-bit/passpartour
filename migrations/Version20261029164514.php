<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029164514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_media_kit_tipo');
        $this->addSql('ALTER TABLE media_kit_asset ADD gruppo INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_media_kit_tipo_gruppo ON media_kit_asset (tipo, gruppo)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_media_kit_tipo_gruppo');
        $this->addSql('ALTER TABLE media_kit_asset DROP gruppo');
        $this->addSql('CREATE UNIQUE INDEX uniq_media_kit_tipo ON media_kit_asset (tipo)');
    }
}
