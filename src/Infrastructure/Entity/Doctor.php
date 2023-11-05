<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\DoctorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctorRepository::class)]
class Doctor extends User
{
    public const USER_TYPE = 'doctor';

    #[ORM\OneToOne(mappedBy: 'doctor', targetEntity: DoctorDetails::class, cascade: ["persist"])]
    private DoctorDetails $doctorDetails;

    #[ORM\ManyToMany(targetEntity: MedicalSpecialty::class, inversedBy: 'doctors')]
    #[ORM\JoinTable(name: 'doctor_to_specialty')]
    private Collection $medicalSpecialties;

    #[ORM\OneToMany(mappedBy: 'doctor', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->medicalSpecialties = new ArrayCollection();
        $this->appointments = new ArrayCollection();
    }

    public function getDoctorDetails(): DoctorDetails
    {
        return $this->doctorDetails;
    }

    public function setDoctorDetails(DoctorDetails $doctorDetails): self
    {
        $this->doctorDetails = $doctorDetails;
        return $this;
    }

    public function getMedicalSpecialties(): Collection
    {
        return $this->medicalSpecialties;
    }

    public function addMedicalSpecialty(MedicalSpecialty $medicalSpecialty): self
    {
        $this->medicalSpecialties->add($medicalSpecialty);
        return $this;
    }

    public function removeMedicalSpecialty(MedicalSpecialty $medicalSpecialty): self
    {
        $this->medicalSpecialties->removeElement($medicalSpecialty);
        return $this;
    }

    public function getAppointments(): Collection
    {
        return $this->appointments;
    }
}
