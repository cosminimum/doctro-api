<?php

namespace App\Infrastructure\Repository;

use App\Infrastructure\Entity\HospitalSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HospitalSettingsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, HospitalSettings::class);
    }
}
