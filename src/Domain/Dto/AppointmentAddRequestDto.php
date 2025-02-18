<?php

namespace App\Domain\Dto;

class AppointmentAddRequestDto
{
    public function __construct(
        private readonly int $doctorId,
        private readonly int $specialtyId,
        private readonly int $hospitalServiceId,
        private readonly int $timeSlotId
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

    public function getHospitalServiceId(): int
    {
        return $this->hospitalServiceId;
    }

    public function getTimeSlotId(): int
    {
        return $this->timeSlotId;
    }
}
