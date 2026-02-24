<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224085629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE role_assignment DROP FOREIGN KEY `FK_BFF12E96A76ED395`');
        $this->addSql('ALTER TABLE role_assignment DROP FOREIGN KEY `FK_BFF12E96D60322AC`');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE role_assignment');
        $this->addSql('DROP INDEX UNIQ_8D93D64935C246D5 ON user');
        $this->addSql('ALTER TABLE user ADD roles JSON NOT NULL, CHANGE actif active TINYINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64986CC499D ON user (pseudo)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE role (id_role INT AUTO_INCREMENT NOT NULL, role_code VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, role_label VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_57698A6AC9AA420C (role_code), PRIMARY KEY (id_role)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE role_assignment (id_role_assignment INT AUTO_INCREMENT NOT NULL, assignment_date DATETIME NOT NULL, user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_BFF12E96D60322AC (role_id), INDEX IDX_BFF12E96A76ED395 (user_id), PRIMARY KEY (id_role_assignment)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE role_assignment ADD CONSTRAINT `FK_BFF12E96A76ED395` FOREIGN KEY (user_id) REFERENCES user (id_users)');
        $this->addSql('ALTER TABLE role_assignment ADD CONSTRAINT `FK_BFF12E96D60322AC` FOREIGN KEY (role_id) REFERENCES role (id_role)');
        $this->addSql('DROP INDEX UNIQ_8D93D64986CC499D ON `user`');
        $this->addSql('ALTER TABLE `user` DROP roles, CHANGE active actif TINYINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64935C246D5 ON `user` (password)');
    }
}
