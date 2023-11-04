<?php

namespace App\Application\Repository;

use App\Domain\Dto\DoctorCreateRequestDto;

interface DoctorRepositoryInterface
{
    public function addDoctor(DoctorCreateRequestDto $userData): int;
}
