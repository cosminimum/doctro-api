<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;

class DoctorDetailsStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository
    ) {
    }

    public function details(int $doctorId): array
    {
        // todo: get doctor details
        // todo: get external calendar

        return [];
    }
}
