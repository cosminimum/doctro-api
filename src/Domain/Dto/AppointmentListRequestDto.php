<?php

namespace App\Domain\Dto;

class AppointmentListRequestDto
{
    public function __construct(
        private readonly ?int $hospitalId,
        private readonly ?int $specialtyId,
        private readonly ?string $doctorName,
        private readonly ?\DateTime $date,
        private readonly ?string $status
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
