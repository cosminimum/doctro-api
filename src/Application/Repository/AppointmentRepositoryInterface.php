<?php

namespace App\Application\Repository;

use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\User;

interface AppointmentRepositoryInterface
{
    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int;

    /** @return AppointmentDto[] */
    public function getAppointmentListByFilters(?AppointmentListRequestDto $requestDto): array;
}
