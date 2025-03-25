<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\DoctorScheduleRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DoctorScheduleRepository::class)]
#[ORM\Table(name: 'doctor_schedules')]
class DoctorSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private string $idHis;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\OneToMany(mappedBy: 'schedule', targetEntity: TimeSlot::class, cascade: ['persist', 'remove'])]
    private Collection $timeSlots;

    public function __construct()
    {
        $this->timeSlots = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): DoctorSchedule
    {
        $this->id = $id;

        return $this;
    }

    public function getIdHis(): string
    {
        return $this->idHis;
    }

    public function setIdHis(string $idHis): DoctorSchedule
    {
        $this->idHis = $idHis;

        return $this;
    }

    public function getDoctor(): Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(Doctor $doctor): DoctorSchedule
    {
        $this->doctor = $doctor;

        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): DoctorSchedule
    {
        $this->date = $date;

        return $this;
    }

    public function getTimeSlots(): Collection
    {
        return $this->timeSlots;
    }

    public function setTimeSlots(Collection $timeSlots): DoctorSchedule
    {
        $this->timeSlots = $timeSlots;

        return $this;
    }
}