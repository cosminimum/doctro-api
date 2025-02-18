<?php
namespace App\Infrastructure\Repository;

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

    /**
     * Find all available slots based on search criteria
     */
    public function findAvailableSlots(array $criteria): array
    {
        $qb = $this->createQueryBuilder('ds')
            ->leftJoin('ds.doctor', 'd')
            ->leftJoin('ds.timeSlots', 'ts')
            ->leftJoin('d.medicalSpecialties', 'ms')
            ->leftJoin('d.hospitalServices', 'hs')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->andWhere('ms.id = :specialtyId')
            ->andWhere('hs.id = :serviceId')
            ->setParameter('startDate', $criteria['startDate'])
            ->setParameter('endDate', $criteria['endDate'])
            ->setParameter('isBooked', false)
            ->setParameter('specialtyId', $criteria['specialty'])
            ->setParameter('serviceId', $criteria['service']);

        if (!empty($criteria['doctor'])) {
            $qb->andWhere('d.id = :doctorId')
                ->setParameter('doctorId', $criteria['doctor']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check for scheduling conflicts
     */
    public function hasConflictingSlots(Doctor $doctor, \DateTimeInterface $date, \DateTimeInterface $startTime, \DateTimeInterface $endTime): bool
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

        return count($qb->getQuery()->getResult()) > 0;
    }

    /**
     * Get schedule with all slots for a specific date range
     */
    public function findScheduleByDateRange(Doctor $doctor, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ds')
            ->leftJoin('ds.timeSlots', 'ts')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->setParameter('doctor', $doctor)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ds.date', 'ASC')
            ->addOrderBy('ts.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}