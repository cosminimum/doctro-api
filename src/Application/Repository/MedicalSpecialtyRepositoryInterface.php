<?php

namespace App\Application\Repository;

use App\Infrastructure\Entity\MedicalSpecialty;

interface MedicalSpecialtyRepositoryInterface
{
    public function findByCode(string $code): ?MedicalSpecialty;
}
