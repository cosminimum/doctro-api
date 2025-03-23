<?php

namespace App\Infrastructure\Datasource;

use App\Application\Datasource\DoctorSourceInterface;
use App\Domain\Dto\DoctorDetailsDto;
use App\Domain\Dto\DoctorListRequestDto;
use App\Infrastructure\Repository\DoctorRepository;

class LocalDoctorSource implements DoctorSourceInterface
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository
    ) {
    }

    public function getDoctorsByFilters(?DoctorListRequestDto $requestDto): array
    {
        return $this->doctorRepository->getDoctorListByFilters($requestDto);
    }

    public function getDoctorDetails(int $doctorId): ?DoctorDetailsDto
    {
        return $this->doctorRepository->getDoctorDetails($doctorId);
    }

    public function findDoctorsByService(int $serviceId): array
    {
        return $this->doctorRepository->findDoctorsByService($serviceId);
    }
}