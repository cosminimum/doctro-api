<?php

namespace App\Application\Story;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class AppointmentAddStory
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository
    ) {
    }

    public function add(
        AppointmentAddRequestDto $requestDto,
        UserInterface|User $user
    ): int {
        $appointmentId = $this->appointmentRepository->addAppointment(
            $requestDto,
            $user
        );

        // todo: email notification

        return $appointmentId;
    }
}
