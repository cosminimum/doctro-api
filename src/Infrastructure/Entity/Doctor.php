<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\DoctorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctorRepository::class)]
class Doctor extends User
{
}
