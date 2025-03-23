<?php

namespace App\Application\Datasource;

use App\Domain\Dto\DoctorDetailsDto;
use App\Domain\Dto\DoctorDto;
use App\Domain\Dto\DoctorListRequestDto;

/**
 * Interface for doctor data sources (local database or external API)
 */
interface DoctorSourceInterface
{
    /**
     * Get a list of doctors based on filter criteria
     *
     * @return DoctorDto[]
     */
    public function getDoctorsByFilters(?DoctorListRequestDto $requestDto): array;

    /**
     * Get detailed information about a doctor
     */
    public function getDoctorDetails(int $doctorId): ?DoctorDetailsDto;

    /**
     * Find doctors by service ID
     *
     * @return array
     */
    public function findDoctorsByService(int $serviceId): array;
}