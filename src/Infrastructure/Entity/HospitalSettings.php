<?php
namespace App\Infrastructure\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Infrastructure\Repository\HospitalSettingsRepository;

#[ORM\Entity(repositoryClass: HospitalSettingsRepository::class)]
class HospitalSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean')]
    private bool $reminderEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reminderSmsMessage = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reminderEmailMessage = '';

    #[ORM\Column(type: 'boolean')]
    private bool $confirmationEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $confirmationSmsMessage = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $confirmationEmailMessage = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): HospitalSettings
    {
        $this->id = $id;

        return $this;
    }

    public function isReminderEnabled(): bool
    {
        return $this->reminderEnabled;
    }

    public function setReminderEnabled(bool $reminderEnabled): HospitalSettings
    {
        $this->reminderEnabled = $reminderEnabled;

        return $this;
    }

    public function getReminderSmsMessage(): ?string
    {
        return $this->reminderSmsMessage;
    }

    public function setReminderSmsMessage(?string $reminderSmsMessage
    ): HospitalSettings {
        $this->reminderSmsMessage = $reminderSmsMessage;

        return $this;
    }

    public function getReminderEmailMessage(): ?string
    {
        return $this->reminderEmailMessage;
    }

    public function setReminderEmailMessage(?string $reminderEmailMessage
    ): HospitalSettings {
        $this->reminderEmailMessage = $reminderEmailMessage;

        return $this;
    }

    public function isConfirmationEnabled(): bool
    {
        return $this->confirmationEnabled;
    }

    public function setConfirmationEnabled(bool $confirmationEnabled
    ): HospitalSettings {
        $this->confirmationEnabled = $confirmationEnabled;

        return $this;
    }

    public function getConfirmationSmsMessage(): ?string
    {
        return $this->confirmationSmsMessage;
    }

    public function setConfirmationSmsMessage(?string $confirmationSmsMessage
    ): HospitalSettings {
        $this->confirmationSmsMessage = $confirmationSmsMessage;

        return $this;
    }

    public function getConfirmationEmailMessage(): ?string
    {
        return $this->confirmationEmailMessage;
    }

    public function setConfirmationEmailMessage(
        ?string $confirmationEmailMessage
    ): HospitalSettings {
        $this->confirmationEmailMessage = $confirmationEmailMessage;

        return $this;
    }
}
