<?php

namespace App\Application\Story;

use App\Domain\Dto\AppointmentListRequestDto;

class AppointmentListStory
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository
    ) {
    }

    public function list(AppointmentListRequestDto $requestDto): array
    {
        // todo: get appointments by filters

        return [];
    }
}
