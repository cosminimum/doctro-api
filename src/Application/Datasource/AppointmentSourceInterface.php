<?php

namespace App\Application\Datasource;

use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\User;

interface AppointmentSourceInterface
{
    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int;
    public function getAppointmentsByFilters(?AppointmentListRequestDto $requestDto): array;
    public function getAppointmentById(int $appointmentId): ?AppointmentDto;
    public function findAvailableSlots(array $criteria): array;
}