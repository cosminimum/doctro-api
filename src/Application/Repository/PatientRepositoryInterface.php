<?php

namespace App\Application\Repository;

use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\User;

interface PatientRepositoryInterface
{
    public function addPatient(UserCreateRequestDto $userData): User;
}
