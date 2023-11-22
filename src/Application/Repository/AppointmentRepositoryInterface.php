<?php

namespace App\Application\Repository;

use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\User;

interface AppointmentRepositoryInterface
{
    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int;
}
