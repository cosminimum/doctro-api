<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250501094830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_tokens (id INT AUTO_INCREMENT NOT NULL, user_identifier VARCHAR(180) NOT NULL, token VARCHAR(180) NOT NULL, valid_until TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE appointments (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL, medical_specialty_id INT NOT NULL, hospital_service_id INT NOT NULL, time_slot_id INT NOT NULL, id_his VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 0 NOT NULL, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX IDX_6A41727A6B899279 (patient_id), INDEX IDX_6A41727A87F4FB17 (doctor_id), INDEX IDX_6A41727ABFC81879 (medical_specialty_id), INDEX IDX_6A41727A9DFBA0F2 (hospital_service_id), INDEX IDX_6A41727AD62B0FA (time_slot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctor_details (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, stamp VARCHAR(255) NOT NULL, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE INDEX UNIQ_E18B682787F4FB17 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctor_schedules (id INT AUTO_INCREMENT NOT NULL, doctor_id INT NOT NULL, id_his VARCHAR(255) DEFAULT NULL, date DATE NOT NULL, INDEX IDX_FE29BD6587F4FB17 (doctor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hospital_services (id INT AUTO_INCREMENT NOT NULL, medical_specialty_id INT NOT NULL, id_his VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, price VARCHAR(255) NOT NULL, duration INT NOT NULL, mode VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 0 NOT NULL, color VARCHAR(20) DEFAULT NULL, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX IDX_59AF779CBFC81879 (medical_specialty_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hospital_settings (id INT AUTO_INCREMENT NOT NULL, reminder_enabled TINYINT(1) NOT NULL, reminder_sms_message LONGTEXT DEFAULT NULL, reminder_email_message LONGTEXT DEFAULT NULL, confirmation_enabled TINYINT(1) NOT NULL, confirmation_sms_message LONGTEXT DEFAULT NULL, confirmation_email_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE medical_specialties (id INT AUTO_INCREMENT NOT NULL, id_his VARCHAR(255) DEFAULT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_slots (id INT AUTO_INCREMENT NOT NULL, schedule_id INT NOT NULL, hospital_service_id INT DEFAULT NULL, id_his VARCHAR(255) DEFAULT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, is_booked TINYINT(1) NOT NULL, status VARCHAR(20) DEFAULT NULL, INDEX IDX_8D06D4ACA40BC2D5 (schedule_id), INDEX IDX_8D06D4AC9DFBA0F2 (hospital_service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, id_his VARCHAR(255) DEFAULT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, cnp VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, photo VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, user_type VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctor_to_medical_specialty (doctor_id INT NOT NULL, medical_specialty_id INT NOT NULL, INDEX IDX_3C3D22487F4FB17 (doctor_id), INDEX IDX_3C3D224BFC81879 (medical_specialty_id), PRIMARY KEY(doctor_id, medical_specialty_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE doctor_to_hospital_service (doctor_id INT NOT NULL, hospital_service_id INT NOT NULL, INDEX IDX_A9C5CCF687F4FB17 (doctor_id), INDEX IDX_A9C5CCF69DFBA0F2 (hospital_service_id), PRIMARY KEY(doctor_id, hospital_service_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A6B899279 FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A87F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727ABFC81879 FOREIGN KEY (medical_specialty_id) REFERENCES medical_specialties (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727A9DFBA0F2 FOREIGN KEY (hospital_service_id) REFERENCES hospital_services (id)');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727AD62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slots (id)');
        $this->addSql('ALTER TABLE doctor_details ADD CONSTRAINT FK_E18B682787F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE doctor_schedules ADD CONSTRAINT FK_FE29BD6587F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE hospital_services ADD CONSTRAINT FK_59AF779CBFC81879 FOREIGN KEY (medical_specialty_id) REFERENCES medical_specialties (id)');
        $this->addSql('ALTER TABLE time_slots ADD CONSTRAINT FK_8D06D4ACA40BC2D5 FOREIGN KEY (schedule_id) REFERENCES doctor_schedules (id)');
        $this->addSql('ALTER TABLE time_slots ADD CONSTRAINT FK_8D06D4AC9DFBA0F2 FOREIGN KEY (hospital_service_id) REFERENCES hospital_services (id)');
        $this->addSql('ALTER TABLE doctor_to_medical_specialty ADD CONSTRAINT FK_3C3D22487F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE doctor_to_medical_specialty ADD CONSTRAINT FK_3C3D224BFC81879 FOREIGN KEY (medical_specialty_id) REFERENCES medical_specialties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE doctor_to_hospital_service ADD CONSTRAINT FK_A9C5CCF687F4FB17 FOREIGN KEY (doctor_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE doctor_to_hospital_service ADD CONSTRAINT FK_A9C5CCF69DFBA0F2 FOREIGN KEY (hospital_service_id) REFERENCES hospital_services (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A6B899279');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A87F4FB17');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727ABFC81879');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727A9DFBA0F2');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727AD62B0FA');
        $this->addSql('ALTER TABLE doctor_details DROP FOREIGN KEY FK_E18B682787F4FB17');
        $this->addSql('ALTER TABLE doctor_schedules DROP FOREIGN KEY FK_FE29BD6587F4FB17');
        $this->addSql('ALTER TABLE hospital_services DROP FOREIGN KEY FK_59AF779CBFC81879');
        $this->addSql('ALTER TABLE time_slots DROP FOREIGN KEY FK_8D06D4ACA40BC2D5');
        $this->addSql('ALTER TABLE time_slots DROP FOREIGN KEY FK_8D06D4AC9DFBA0F2');
        $this->addSql('ALTER TABLE doctor_to_medical_specialty DROP FOREIGN KEY FK_3C3D22487F4FB17');
        $this->addSql('ALTER TABLE doctor_to_medical_specialty DROP FOREIGN KEY FK_3C3D224BFC81879');
        $this->addSql('ALTER TABLE doctor_to_hospital_service DROP FOREIGN KEY FK_A9C5CCF687F4FB17');
        $this->addSql('ALTER TABLE doctor_to_hospital_service DROP FOREIGN KEY FK_A9C5CCF69DFBA0F2');
        $this->addSql('DROP TABLE access_tokens');
        $this->addSql('DROP TABLE appointments');
        $this->addSql('DROP TABLE doctor_details');
        $this->addSql('DROP TABLE doctor_schedules');
        $this->addSql('DROP TABLE hospital_services');
        $this->addSql('DROP TABLE hospital_settings');
        $this->addSql('DROP TABLE medical_specialties');
        $this->addSql('DROP TABLE time_slots');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE doctor_to_medical_specialty');
        $this->addSql('DROP TABLE doctor_to_hospital_service');
    }
}
