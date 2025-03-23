<?php

namespace App\Application\Story;

use App\Application\Factory\AppointmentSourceFactory;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Domain\Dto\AppointmentListResponseDto;

class AppointmentListStory
{
    public function __construct(
        private readonly AppointmentSourceFactory $appointmentSourceFactory
    ) {
    }

    public function list(?AppointmentListRequestDto $requestDto): AppointmentListResponseDto
    {
        $appointmentSource = $this->appointmentSourceFactory->create();
        $rows = $appointmentSource->getAppointmentsByFilters($requestDto);

        return new AppointmentListResponseDto($rows);
    }
}