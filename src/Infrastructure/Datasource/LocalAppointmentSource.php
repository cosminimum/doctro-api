<?php

namespace App\Infrastructure\Datasource;

use App\Application\DataSource\AppointmentSourceInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;

class LocalAppointmentSource implements AppointmentSourceInterface
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly DoctorScheduleRepository $doctorScheduleRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int
    {
        return $this->appointmentRepository->addAppointment($requestDto, $user);
    }

    public function getAppointmentsByFilters(?AppointmentListRequestDto $requestDto): array
    {
        return $this->appointmentRepository->getAppointmentListByFilters($requestDto);
    }

    public function getAppointmentById(int $appointmentId): ?AppointmentDto
    {
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        if (!$appointment) {
            return null;
        }

        $dto = new AppointmentDto();
        $dto->setAppointmentId($appointment->getId())
            ->setPatientId($appointment->getPatient()->getId())
            ->setPatientName($appointment->getPatient()->getFirstName() . ' ' . $appointment->getPatient()->getLastName())
            ->setDoctorId($appointment->getDoctor()->getId())
            ->setDoctorName($appointment->getDoctor()->getFirstName() . ' ' . $appointment->getDoctor()->getLastName())
            ->setSpecialtyId($appointment->getMedicalSpecialty()->getId())
            ->setSpecialtyName($appointment->getMedicalSpecialty()->getName())
            ->setHospitalServiceId($appointment->getHospitalService()->getId())
            ->setHospitalServiceName($appointment->getHospitalService()->getName())
            ->setAppointmentDate(new \DateTime(
                $appointment->getTimeSlot()->getSchedule()->getDate()->format('Y-m-d') . ' ' .
                $appointment->getTimeSlot()->getStartTime()->format('H:i:s')
            ));

        return $dto;
    }

    public function findAvailableSlots(array $criteria): array
    {
        return $this->doctorScheduleRepository->findAvailableSlots($criteria);
    }
}