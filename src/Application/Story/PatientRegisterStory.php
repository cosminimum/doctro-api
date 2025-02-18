<?php

namespace App\Application\Story;

use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\User;

class PatientRegisterStory
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository
    ) {
    }

    public function register(UserCreateRequestDto $requestDto): User
    {
        return $this->patientRepository->addPatient($requestDto);
    }
}
