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

    //getpractitioners
    //getappointments
    //getpractitionerroles ⟶specialitate + servicii medic
    //get slots
    //get schedules
    //gethealthcareservices ⟶nomenclator de servicii
    public function register(User $user, string $plainPassword): int
    {
        $userId = $this->patientRepository->addDoctor($user, $plainPassword);

        // todo: additional stuff after register action

        return $userId;
    }
}
