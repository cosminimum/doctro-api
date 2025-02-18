<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250218090226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hospital_settings (id INT AUTO_INCREMENT NOT NULL, reminder_enabled TINYINT(1) NOT NULL, reminder_sms_message LONGTEXT DEFAULT NULL, reminder_email_message LONGTEXT DEFAULT NULL, confirmation_enabled TINYINT(1) NOT NULL, confirmation_sms_message LONGTEXT DEFAULT NULL, confirmation_email_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE appointments CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE doctor_details CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE hospital_services CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE medical_specialties CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE users CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE hospital_settings');
        $this->addSql('ALTER TABLE medical_specialties CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE appointments CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE doctor_details CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE hospital_services CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
