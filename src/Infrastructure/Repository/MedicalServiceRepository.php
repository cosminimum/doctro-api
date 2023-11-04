<?php

namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\MedicalService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MedicalServiceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, MedicalService::class);
    }
}
