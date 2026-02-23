<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223153557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE breathing_exercise (id_exercise INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, inhale_duration SMALLINT NOT NULL, hold_duration SMALLINT NOT NULL, exhale_duration SMALLINT NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (id_exercise)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `launch` (id_launch INT AUTO_INCREMENT NOT NULL, launch_date DATETIME NOT NULL, cycle_count SMALLINT NOT NULL, total_duration TIME NOT NULL, user_id INT NOT NULL, exercise_id INT NOT NULL, INDEX IDX_79B757F5A76ED395 (user_id), INDEX IDX_79B757F5E934951A (exercise_id), PRIMARY KEY (id_launch)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `menu` (id_menu INT AUTO_INCREMENT NOT NULL, title VARCHAR(50) NOT NULL, display_order INT NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (id_menu)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `page` (id_page INT AUTO_INCREMENT NOT NULL, title VARCHAR(50) NOT NULL, slug VARCHAR(100) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, menu_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_140AB620989D9B62 (slug), INDEX IDX_140AB620F675F31B (author_id), INDEX IDX_140AB620CCD7E912 (menu_id), PRIMARY KEY (id_page)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `role` (id_role INT AUTO_INCREMENT NOT NULL, role_code VARCHAR(50) NOT NULL, role_label VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_57698A6AC9AA420C (role_code), PRIMARY KEY (id_role)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE role_assignment (id_role_assignment INT AUTO_INCREMENT NOT NULL, assignment_date DATETIME NOT NULL, user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_BFF12E96A76ED395 (user_id), INDEX IDX_BFF12E96D60322AC (role_id), PRIMARY KEY (id_role_assignment)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `user` (id_users INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, pseudo VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, actif TINYINT NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D64935C246D5 (password), PRIMARY KEY (id_users)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE `launch` ADD CONSTRAINT FK_79B757F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id_users)');
        $this->addSql('ALTER TABLE `launch` ADD CONSTRAINT FK_79B757F5E934951A FOREIGN KEY (exercise_id) REFERENCES breathing_exercise (id_exercise)');
        $this->addSql('ALTER TABLE `page` ADD CONSTRAINT FK_140AB620F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id_users)');
        $this->addSql('ALTER TABLE `page` ADD CONSTRAINT FK_140AB620CCD7E912 FOREIGN KEY (menu_id) REFERENCES `menu` (id_menu) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role_assignment ADD CONSTRAINT FK_BFF12E96A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id_users)');
        $this->addSql('ALTER TABLE role_assignment ADD CONSTRAINT FK_BFF12E96D60322AC FOREIGN KEY (role_id) REFERENCES `role` (id_role)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `launch` DROP FOREIGN KEY FK_79B757F5A76ED395');
        $this->addSql('ALTER TABLE `launch` DROP FOREIGN KEY FK_79B757F5E934951A');
        $this->addSql('ALTER TABLE `page` DROP FOREIGN KEY FK_140AB620F675F31B');
        $this->addSql('ALTER TABLE `page` DROP FOREIGN KEY FK_140AB620CCD7E912');
        $this->addSql('ALTER TABLE role_assignment DROP FOREIGN KEY FK_BFF12E96A76ED395');
        $this->addSql('ALTER TABLE role_assignment DROP FOREIGN KEY FK_BFF12E96D60322AC');
        $this->addSql('DROP TABLE breathing_exercise');
        $this->addSql('DROP TABLE `launch`');
        $this->addSql('DROP TABLE `menu`');
        $this->addSql('DROP TABLE `page`');
        $this->addSql('DROP TABLE `role`');
        $this->addSql('DROP TABLE role_assignment');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
