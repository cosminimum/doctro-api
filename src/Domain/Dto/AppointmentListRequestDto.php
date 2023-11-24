<?php

namespace App\Domain\Dto;

class AppointmentListRequestDto
{
    public function __construct(
        private readonly ?int $hospitalId = null,
        private readonly ?int $specialtyId = null,
        private readonly ?string $doctorName = null,
        private readonly ?\DateTime $date = null,
        private readonly ?string $status = null
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

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
