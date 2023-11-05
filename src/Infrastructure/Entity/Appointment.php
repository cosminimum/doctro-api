<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointments')]
#[ORM\HasLifecycleCallbacks]
class Appointment
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
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

    #[ORM\Column(type: 'datetime')]
    private \DateTime $appointmentDate;

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

    public function getAppointmentDate(): \DateTime
    {
        return $this->appointmentDate;
    }

    public function setAppointmentDate(\DateTime $appointmentDate): self
    {
        $this->appointmentDate = $appointmentDate;
        return $this;
    }
}
