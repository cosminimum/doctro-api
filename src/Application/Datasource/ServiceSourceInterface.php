<?php

namespace App\Application\Datasource;

use App\Infrastructure\Entity\MedicalSpecialty;

interface ServiceSourceInterface
{
    public function findServicesBySpecialty(int $specialtyId): array;
    public function findServicesByDoctor($doctor): array;
}