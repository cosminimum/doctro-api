<?php

namespace App\Infrastructure\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'time_slots')]
class TimeSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: DoctorSchedule::class, inversedBy: 'timeSlots')]
    #[ORM\JoinColumn(nullable: false)]
    private DoctorSchedule $schedule;

    #[ORM\Column(type: 'time')]
    private \DateTime $startTime;

    #[ORM\Column(type: 'time')]
    private \DateTime $endTime;

    #[ORM\Column(type: 'boolean')]
    private bool $isBooked = false;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): TimeSlot
    {
        $this->id = $id;

        return $this;
    }

    public function getSchedule(): DoctorSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(DoctorSchedule $schedule): TimeSlot
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getStartTime(): \DateTime
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTime $startTime): TimeSlot
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): \DateTime
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTime $endTime): TimeSlot
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isBooked(): bool
    {
        return $this->isBooked;
    }

    public function setIsBooked(bool $isBooked): TimeSlot
    {
        $this->isBooked = $isBooked;

        return $this;
    }
}
