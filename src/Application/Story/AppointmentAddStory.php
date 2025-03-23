<?php

namespace App\Application\Story;

use App\Application\Factory\AppointmentSourceFactory;
use App\Application\Factory\DoctorSourceFactory;
use App\Application\Factory\ServiceSourceFactory;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\PatientRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class AppointmentAddStory
{
    public function __construct(
        private AppointmentSourceFactory $appointmentSourceFactory,
        private EntityManagerInterface $entityManager,
        private PatientRepositoryInterface $patientRepository,
        private Security $security,
        private ServiceSourceFactory $serviceSourceFactory,
        private DoctorSourceFactory $doctorSourceFactory,
    ) {
    }

    public function add(
        AppointmentAddRequestDto $requestDto,
        UserInterface|User $user
    ): int {
        $appointmentSource = $this->appointmentSourceFactory->create();
        $appointmentId = $appointmentSource->addAppointment(
            $requestDto,
            $user
        );

        // todo: email notification

        return $appointmentId;
    }

    // @TODO Implement
    public function edit() {

    }

    public function findAvailableSlots(array $criteria): array
    {
        $appointmentSource = $this->appointmentSourceFactory->create();
        return $appointmentSource->findAvailableSlots($criteria);
    }

    public function getServicesBySpecialty(int $specialtyId): array
    {
        $serviceSource = $this->serviceSourceFactory->create();
        return $serviceSource->findServicesBySpecialty($specialtyId);
    }

    public function getDoctorsByService(int $serviceId): array
    {
        $doctorSource = $this->doctorSourceFactory->create();
        return $doctorSource->findDoctorsByService($serviceId);
    }
}