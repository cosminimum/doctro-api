<?php

namespace App\Application\Story;

use App\Application\Factory\DoctorSourceFactory;
use App\Domain\Dto\DoctorDetailsDto;

class DoctorDetailsStory
{
    public function __construct(
        private readonly DoctorSourceFactory $doctorSourceFactory
    ) {
    }

    public function details(int $doctorId): ?DoctorDetailsDto
    {
        $doctorSource = $this->doctorSourceFactory->create();
        $doctor = $doctorSource->getDoctorDetails($doctorId);

        // todo: get external calendar -- external call -- how?

        return $doctor;
    }
}