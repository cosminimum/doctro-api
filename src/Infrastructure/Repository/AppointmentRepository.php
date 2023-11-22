<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRepository extends ServiceEntityRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, Appointment::class);
    }

    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int
    {
        $patient = $this->getEntityManager()->find(Patient::class, $user->getId());
        $doctor = $this->getEntityManager()->find(Doctor::class, $requestDto->getDoctorId());
        $medicalSpecialty = $this->getEntityManager()->find(MedicalSpecialty::class, $requestDto->getSpecialtyId());
        $hospitalService = $this->getEntityManager()->find(HospitalService::class, $requestDto->getHospitalServiceId());

        if (!$patient || !$doctor || !$medicalSpecialty || !$hospitalService) {
            throw new \Exception('missing mandatory data on appointment add');
        }

        $appointment = (new Appointment())
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setMedicalSpecialty($medicalSpecialty)
            ->setHospitalService($hospitalService)
            ->setAppointmentDate($requestDto->getDate());

        $this->getEntityManager()->persist($appointment);
        $this->getEntityManager()->flush();

        return $appointment->getId();
    }
}
