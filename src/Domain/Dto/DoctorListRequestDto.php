<?php

namespace App\Domain\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DoctorListRequestDto
{
    public function __construct(
        private readonly ?int $hospitalId,
        private readonly ?int $specialtyId,
        private readonly ?string $doctorName,
        private readonly ?string $serviceId
    ) {
    }

    public function getHospitalId(): ?int
    {
        return $this->hospitalId;
    }

    public function getSpecialtyId(): ?int
    {
        return $this->specialtyId;
    }

    public function getDoctorName(): ?string
    {
        return $this->doctorName;
    }

    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }
}
