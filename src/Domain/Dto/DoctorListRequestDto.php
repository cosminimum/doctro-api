<?php

namespace App\Domain\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DoctorListRequestDto
{
    private bool $true = true;

    public function __construct(
        private readonly ?int $hospitalId = null,
        private readonly ?int $specialtyId = null,
        private readonly ?string $doctorName = null,
        private readonly ?string $serviceId = null,
        private readonly ?\DateTime $availableDate = null
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

    public function getAvailableDate(): ?\DateTime
    {
        return $this->availableDate;
    }
}
