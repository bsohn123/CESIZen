<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226163100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile image fields to user for VichUploader';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD image_name VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP image_name, DROP updated_at');
    }
}
