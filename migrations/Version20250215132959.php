<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250215132959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE appointments ADD time_slot_id INT NOT NULL, DROP appointment_date, CHANGE created created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CHANGE updated updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_6A41727AD62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slots (id)');
        $this->addSql('CREATE INDEX IDX_6A41727AD62B0FA ON appointments (time_slot_id)');
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
        $this->addSql('ALTER TABLE hospitals CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE hospital_services CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE doctor_details CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE access_tokens CHANGE valid_until valid_until DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE medical_services CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE medical_specialties CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_6A41727AD62B0FA');
        $this->addSql('DROP INDEX IDX_6A41727AD62B0FA ON appointments');
        $this->addSql('ALTER TABLE appointments ADD appointment_date DATETIME NOT NULL, DROP time_slot_id, CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE created created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
