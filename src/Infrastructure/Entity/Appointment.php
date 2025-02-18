<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointments')]
#[ORM\HasLifecycleCallbacks]
class Appointment
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['appointment'])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id', nullable: false)]
    private Patient $patient;


    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(name: 'doctor_id', referencedColumnName: 'id', nullable: false)]
    private Doctor $doctor;

    #[ORM\ManyToOne(targetEntity: MedicalSpecialty::class)]
    #[ORM\JoinColumn(name: 'medical_specialty_id', referencedColumnName: 'id', nullable: false)]
    private MedicalSpecialty $medicalSpecialty;

    #[ORM\ManyToOne(targetEntity: HospitalService::class)]
    #[ORM\JoinColumn(name: 'hospital_service_id', referencedColumnName: 'id', nullable: false)]
    private HospitalService $hospitalService;

    #[ORM\ManyToOne(targetEntity: TimeSlot::class)]
    #[ORM\JoinColumn(name: 'time_slot_id', referencedColumnName: 'id', nullable: false)]
    private TimeSlot $timeSlot;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $isActive;

    // todo: mode ???

    // todo: specialNeeds ???

    public function getId(): int
    {
        return $this->id;
    }

    public function getPatient(): Patient
    {
        return $this->patient;
    }

    public function setPatient(Patient $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function getDoctor(): Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(Doctor $doctor): self
    {
        $this->doctor = $doctor;
        return $this;
    }

    public function getMedicalSpecialty(): MedicalSpecialty
    {
        return $this->medicalSpecialty;
    }

    public function setMedicalSpecialty(MedicalSpecialty $medicalSpecialty): self
    {
        $this->medicalSpecialty = $medicalSpecialty;
        return $this;
    }

    public function getHospitalService(): HospitalService
    {
        return $this->hospitalService;
    }

    public function setHospitalService(HospitalService $hospitalService): self
    {
        $this->hospitalService = $hospitalService;
        return $this;
    }

    public function getTimeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function setTimeSlot(TimeSlot $timeSlot): Appointment
    {
        $this->timeSlot = $timeSlot;

        return $this;
    }

    public function getIsActive()
    {
        return $this->isActive;
    }

    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }
}
