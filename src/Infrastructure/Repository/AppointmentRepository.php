<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Infrastructure\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRepository extends ServiceEntityRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, Appointment::class);
    }
}
