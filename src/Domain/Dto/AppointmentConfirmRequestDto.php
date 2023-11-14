<?php

namespace App\Domain\Dto;

class AppointmentConfirmRequestDto
{
    public function __construct(
        private readonly int $doctorId,
        private readonly int $specialtyId,
        private readonly int $hospitalId,
        private readonly int $hospitalServiceId,
        private readonly \DateTime $date
    ) {
    }

    public function getDoctorId(): int
    {
        return $this->doctorId;
    }

    public function getSpecialtyId(): int
    {
        return $this->specialtyId;
    }

    public function getHospitalId(): int
    {
        return $this->hospitalId;
    }

    public function getHospitalServiceId(): int
    {
        return $this->hospitalServiceId;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }
}
