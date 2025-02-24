<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Factory\AppointmentDtoFactory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
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
        $timeSlot = $this->getEntityManager()->find(TimeSlot::class, $requestDto->getTimeSlotId());

        if (!$patient || !$doctor || !$medicalSpecialty || !$hospitalService) {
            throw new \Exception('missing mandatory data on appointment add');
        }

        $appointment = (new Appointment())
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setMedicalSpecialty($medicalSpecialty)
            ->setHospitalService($hospitalService)
            ->setTimeSlot($timeSlot)
            ->setIsActive(false)
        ;

        $this->getEntityManager()->persist($appointment);
        $this->getEntityManager()->flush();

        return $appointment->getId();
    }

    /** @return AppointmentDto[] */
    public function getAppointmentListByFilters(?AppointmentListRequestDto $requestDto): array
    {
        $qb = $this->createQueryBuilder('appointment');

        $qb->innerJoin('appointment.hospitalService', 'hospitalService');
        $qb->innerJoin('appointment.doctor', 'doctor');

        $qb->select('appointment');

        $qb->where($qb->expr()->eq(true, true));

        if ($requestDto?->getHospitalId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('hospitalService.hospital', ':hospitalId')
            );

            $qb->setParameter('hospitalId', $requestDto?->getHospitalId());
        }

        if ($requestDto?->getDate() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('appointment.appointmentDate', ':appointmentDate')
            );

            $qb->setParameter('appointmentDate', $requestDto?->getDate());
        }

        if ($requestDto?->getSpecialtyId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('appointment.medicalSpecialty', ':specialtyId')
            );

            $qb->setParameter('specialtyId', $requestDto?->getSpecialtyId());
        }

        if ($requestDto?->getDoctorName() !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('doctor.firstName', ':doctorName'),
                    $qb->expr()->like('doctor.lastName', ':doctorName')
                )
            );

            $qb->setParameter('doctorName', '%'.$requestDto?->getDoctorName().'%');
        }

        if ($requestDto?->getStatus() !== null) {
            // todo: field & filter TBD
        }

        $qb->groupBy('appointment.id');

        /** @var Appointment[] $results */
        $results = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT);

        return array_map(static function ($appointment) {
            return AppointmentDtoFactory::fromEntity($appointment);
        }, $results);
    }
}
