<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\MedicalSpecialtyRepositoryInterface;
use App\Infrastructure\Entity\MedicalSpecialty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MedicalSpecialtyRepository extends ServiceEntityRepository implements MedicalSpecialtyRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, MedicalSpecialty::class);
    }

    public function findByCode(string $code): ?MedicalSpecialty
    {
        return $this->findOneBy(['code' => $code]);
    }
}
