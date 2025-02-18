<?php

namespace App\Infrastructure\Factory;

use App\Domain\Dto\AppointmentDto;
use App\Infrastructure\Entity\Appointment;

class AppointmentDtoFactory
{
    public static function fromEntity(Appointment $appointment): AppointmentDto
    {
        $dto = (new AppointmentDto())
            ->setAppointmentId($appointment->getId())
            ->setPatientId($appointment->getPatient()->getId())
            ->setPatientName(
                sprintf(
                    '%s %s',
                    $appointment->getPatient()->getFirstName(),
                    $appointment->getPatient()->getLastName()
                )
            )
            ->setDoctorId($appointment->getDoctor()->getId())
            ->setDoctorName(
                sprintf(
                    '%s %s',
                    $appointment->getDoctor()->getFirstName(),
                    $appointment->getDoctor()->getLastName()
                )
            )
            ->setSpecialtyId($appointment->getMedicalSpecialty()->getId())
            ->setSpecialtyName($appointment->getMedicalSpecialty()->getName())
            ->setHospitalServiceId($appointment->getHospitalService()->getId())
            ->setHospitalServiceName($appointment->getHospitalService()->getName());

        return $dto;
    }
}
