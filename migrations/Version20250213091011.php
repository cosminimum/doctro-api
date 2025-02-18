<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250213091011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE doctor_schedules (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, date DATE NOT NULL, INDEX IDX_FE29BD6587F4FB17 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_slots (id INT AUTO_INCREMENT NOT NULL, schedule_id INT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, is_booked TINYINT(1) NOT NULL, INDEX IDX_8D06D4ACA40BC2D5 (schedule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE doctor_schedules ADD CONSTRAINT FK_FE29BD6587F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE time_slots ADD CONSTRAINT FK_8D06D4ACA40BC2D5 FOREIGN KEY (schedule_id) REFERENCES doctor_schedules (id)');
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE appointments CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE doctor_details CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE hospital_services CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE hospitals CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE medical_services CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE medical_specialties CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE users CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE doctor_schedules DROP FOREIGN KEY FK_FE29BD6587F4FB17');
        $this->addSql('ALTER TABLE time_slots DROP FOREIGN KEY FK_8D06D4ACA40BC2D5');
        $this->addSql('DROP TABLE doctor_schedules');
        $this->addSql('DROP TABLE time_slots');
        $this->addSql('ALTER TABLE medical_specialties CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE medical_services CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE hospitals CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE hospital_services CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE doctor_details CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE appointments CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
