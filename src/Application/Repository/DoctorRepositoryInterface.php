<?php

namespace App\Application\Repository;

use App\Domain\Dto\DoctorDetailsDto;
use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\DoctorDto;
use App\Domain\Dto\UserCreateRequestDto;

interface DoctorRepositoryInterface
{
    public function addDoctor(UserCreateRequestDto $userData): int;

    /** @return DoctorDto[] */
    public function getDoctorListByFilters(?DoctorListRequestDto $requestDto): array;

    public function getDoctorDetails(int $doctorId): ?DoctorDetailsDto;
}
