<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\PatientRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient extends User
{
    public const USER_TYPE = 'patient';
    public const BASE_ROLE = 'ROLE_PATIENT';

    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Appointment::class)]
    private Collection $appointments;
}
