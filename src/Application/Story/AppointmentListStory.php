<?php

namespace App\Application\Story;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Domain\Dto\AppointmentListResponseDto;

class AppointmentListStory
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository
    ) {
    }

    public function list(?AppointmentListRequestDto $requestDto): AppointmentListResponseDto
    {
        $rows = $this->appointmentRepository->getAppointmentListByFilters($requestDto);

        return new AppointmentListResponseDto($rows);
    }
}