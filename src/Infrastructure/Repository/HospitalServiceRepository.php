<?php

namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\HospitalService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HospitalServiceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, HospitalService::class);
    }
}
