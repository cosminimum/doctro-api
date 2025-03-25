<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\DoctorDetailsDto;

class DoctorDetailsStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository
    ) {
    }

    public function details(int $doctorId): ?DoctorDetailsDto
    {
        $doctor = $this->doctorRepository->getDoctorDetails($doctorId);

        // todo: get external calendar -- external call -- how?

        return $doctor;
    }
}