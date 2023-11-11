<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;

class DoctorRegisterStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $patientRepository
    ) {
    }

    public function register(UserCreateRequestDto $requestDto): int
    {
        $userId = $this->patientRepository->addDoctor($requestDto);

        // todo: additional stuff after register action

        return $userId;
    }
}
