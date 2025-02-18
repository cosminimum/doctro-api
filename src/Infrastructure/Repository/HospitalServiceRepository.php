<?php

namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\HospitalService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

class HospitalServiceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, HospitalService::class);
    }

    public function findServicesBySpecialty(int $specialtyId): array
    {
        return $this->createQueryBuilder('hs')
            ->leftJoin('hs.doctors', 'd')
            ->leftJoin('d.medicalSpecialties', 'ms')
            ->where('ms.id = :specialtyId')
            ->setParameter('specialtyId', $specialtyId)
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    public function findServicesByDoctor(UserInterface $doctor): array
    {
        return $this->createQueryBuilder('hs')
            ->innerJoin('hs.doctors', 'd')
            ->where('d = :doctor')
            ->setParameter('doctor', $doctor)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find services with available slots for a date range
     */
    public function findServicesWithAvailability(
        int $specialtyId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('hs')
            ->leftJoin('hs.doctors', 'd')
            ->leftJoin('d.schedules', 'ds')
            ->leftJoin('ds.timeSlots', 'ts')
            ->leftJoin('d.medicalSpecialties', 'ms')
            ->where('ms.id = :specialtyId')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->setParameter('specialtyId', $specialtyId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isBooked', false)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
