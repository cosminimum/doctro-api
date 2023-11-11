<?php

namespace App\Application\Repository;

use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\UserCreateRequestDto;

interface DoctorRepositoryInterface
{
    public function addDoctor(UserCreateRequestDto $userData): int;
    public function getDoctorByFilters(DoctorListRequestDto $requestDto): array;
}
