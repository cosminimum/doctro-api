<?php

namespace App\Infrastructure\Datasource;

use App\Application\DataSource\ServiceSourceInterface;
use App\Infrastructure\Repository\HospitalServiceRepository;

class LocalServiceSource implements ServiceSourceInterface
{
    public function __construct(
        private readonly HospitalServiceRepository $hospitalServiceRepository
    ) {
    }

    public function findServicesBySpecialty(int $specialtyId): array
    {
        return $this->hospitalServiceRepository->findServicesBySpecialty($specialtyId);
    }

    public function findServicesByDoctor($doctor): array
    {
        return $this->hospitalServiceRepository->findServicesByDoctor($doctor);
    }
}