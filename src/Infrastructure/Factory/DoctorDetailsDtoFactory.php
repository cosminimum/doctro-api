<?php

namespace App\Infrastructure\Factory;

use App\Domain\Dto\DoctorDetailsDto;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;

class DoctorDetailsDtoFactory
{
    public static function fromEntity(Doctor $doctor): DoctorDetailsDto
    {
        $dto = (new DoctorDetailsDto())
            ->setId($doctor->getId())
            ->setEmail($doctor->getEmail())
            ->setFirstName($doctor->getFirstName())
            ->setLastName($doctor->getLastName())
            ->setCnp($doctor->getCnp())
            ->setPhone($doctor->getPhone());

        /** @var MedicalSpecialty $specialty */
        foreach ($doctor->getMedicalSpecialties()->toArray() as $specialty) {
            $dto->addSpecialty(
                $specialty->getId(),
                $specialty->getCode(),
                $specialty->getName()
            );
        }

        /** @var HospitalService $hospitalService */
        foreach ($doctor->getHospitalServices()->toArray() as $hospitalService) {
            $dto->addHospitalService(
                $hospitalService->getId(),
                $hospitalService->getName(),
                $hospitalService->getHospital()->getId(),
                $hospitalService->getHospital()->getName(),
                $hospitalService->getMedicalService()->getId(),
                $hospitalService->getMedicalService()->getName(),
                $hospitalService->getMedicalService()->getCode()
            );
        }

        return $dto;
    }
}
