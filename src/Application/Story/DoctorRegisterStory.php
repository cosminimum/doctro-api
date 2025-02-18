<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\User;

class DoctorRegisterStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $patientRepository
    ) {
    }

    public function register(User $user): int
    {
        $userId = $this->patientRepository->addDoctor($user);

        // todo: additional stuff after register action

        return $userId;
    }
}
