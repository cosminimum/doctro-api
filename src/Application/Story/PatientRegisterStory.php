<?php

namespace App\Application\Story;

use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;

class PatientRegisterStory
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository
    ) {
    }

    public function register(UserCreateRequestDto $requestDto): int
    {
        $userId = $this->patientRepository->addPatient($requestDto);

        // todo: additional stuff after register action

        return $userId;
    }
}
