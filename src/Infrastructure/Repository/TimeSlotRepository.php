<?php
namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\TimeSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TimeSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeSlot::class);
    }

    public function findAvailableSlotsByDoctor(
        Doctor $doctor,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ts')
            ->leftJoin('ts.schedule', 'ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->setParameter('doctor', $doctor)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isBooked', false)
            ->orderBy('ds.date', 'ASC')
            ->addOrderBy('ts.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverlappingSlots(
        Doctor $doctor,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeSlotId = null
    ): array {
        $qb = $this->createQueryBuilder('ts')
            ->leftJoin('ts.schedule', 'ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date = :date')
            ->andWhere('ts.startTime < :endTime')
            ->andWhere('ts.endTime > :startTime')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeSlotId) {
            $qb->andWhere('ts.id != :excludeSlotId')
                ->setParameter('excludeSlotId', $excludeSlotId);
        }

        return $qb->getQuery()->getResult();
    }
}