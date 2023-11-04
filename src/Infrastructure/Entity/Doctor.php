<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\DoctorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctorRepository::class)]
class Doctor extends User
{
    #[ORM\OneToOne(mappedBy: 'doctor', targetEntity: DoctorDetails::class, cascade: ["persist"])]
    private DoctorDetails $doctorDetails;

    public function getDoctorDetails(): DoctorDetails
    {
        return $this->doctorDetails;
    }

    public function setDoctorDetails(DoctorDetails $doctorDetails): self
    {
        $this->doctorDetails = $doctorDetails;
        return $this;
    }
}
