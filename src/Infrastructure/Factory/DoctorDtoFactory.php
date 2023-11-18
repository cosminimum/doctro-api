<?php

namespace App\Infrastructure\Factory;

use App\Domain\Dto\DoctorDto;
use App\Infrastructure\Entity\Doctor;

class DoctorDtoFactory
{
    public static function fromEntity(Doctor $doctor): DoctorDto
    {
        $dto = (new DoctorDto())
            ->setId($doctor->getId())
            ->setEmail($doctor->getEmail())
            ->setFirstName($doctor->getFirstName())
            ->setLastName($doctor->getLastName())
            ->setCnp($doctor->getCnp())
            ->setPhone($doctor->getPhone());

        return $dto;
    }
}
