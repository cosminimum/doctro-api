<?php

namespace App\Application\Repository;

use App\Domain\Dto\UserCreateRequestDto;

interface PatientRepositoryInterface
{
    public function addPatient(UserCreateRequestDto $userData): int;
}
