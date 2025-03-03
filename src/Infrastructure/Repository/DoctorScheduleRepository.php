<?php
namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\TimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctorScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoctorSchedule::class);
    }

    // src/Infrastructure/Repository/DoctorScheduleRepository.php

    /**
     * Find all available slots based on search criteria
     */
    public function findAvailableSlots(array $criteria): array
    {
        $qb = $this->createQueryBuilder('ds')
            ->leftJoin('ds.doctor', 'd')
            ->leftJoin('ds.timeSlots', 'ts')
            ->leftJoin('ts.hospitalService', 'hs')
            ->leftJoin('d.medicalSpecialties', 'ms')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->andWhere('ms.id = :specialtyId')
            ->setParameter('startDate', $criteria['startDate'])
            ->setParameter('endDate', $criteria['endDate'])
            ->setParameter('isBooked', false)
            ->setParameter('specialtyId', $criteria['specialty']);

        if (!empty($criteria['service'])) {
            $qb->andWhere('hs.id = :serviceId')
                ->setParameter('serviceId', $criteria['service']);
        }

        if (!empty($criteria['doctor'])) {
            $qb->andWhere('d.id = :doctorId')
                ->setParameter('doctorId', $criteria['doctor']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check for scheduling conflicts
     */
    public function hasConflictingSlots(Doctor $doctor, \DateTimeInterface $date, \DateTimeInterface $startTime, \DateTimeInterface $endTime, ?int $serviceId = null): bool
    {
        $qb = $this->createQueryBuilder('ds')
            ->leftJoin('ds.timeSlots', 'ts')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date = :date')
            ->andWhere('ts.startTime < :endTime')
            ->andWhere('ts.endTime > :startTime')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        // If a service is specified, only check for conflicts with slots for that service
        if ($serviceId) {
            $qb->leftJoin('ts.hospitalService', 'hs')
                ->andWhere('hs.id = :serviceId OR hs.id IS NULL')
                ->setParameter('serviceId', $serviceId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }

    /**
     * Find slots available for a specific service
     */
    public function findAvailableSlotsByService(
        int $serviceId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ds')
            ->leftJoin('ds.timeSlots', 'ts')
            ->leftJoin('ts.hospitalService', 'hs')
            ->where('hs.id = :serviceId')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->setParameter('serviceId', $serviceId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isBooked', false)
            ->getQuery()
            ->getResult();
    }
}