<?php

namespace App\Application\Repository;

use App\Domain\Dto\PatientCreateRequestDto;

interface PatientRepositoryInterface
{
    public function addPatient(PatientCreateRequestDto $userData): int;
}
