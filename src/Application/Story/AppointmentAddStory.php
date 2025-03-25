<?php

namespace App\Application\Story;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Application\Repository\DoctorRepositoryInterface;
use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Infrastructure\Repository\TimeSlotRepository;

readonly class AppointmentAddStory
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private EntityManagerInterface $entityManager,
        private PatientRepositoryInterface $patientRepository,
        private Security $security,
        private HospitalServiceRepository $hospitalServiceRepository,
        private DoctorRepositoryInterface $doctorRepository,
        private TimeSlotRepository $timeSlotRepository,
        private DoctorScheduleRepository $doctorScheduleRepository,
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

    // @TODO Implement
    public function edit() {

    }

    public function saveAppointment(Appointment $appointment, ?string $guestEmail = null): void
    {
        $patient = $this->security->getUser();

        if (!$patient && $guestEmail) {
            $patient = $this->createOrGetPatient($guestEmail);
        }

        if (!$patient) {
            throw new \RuntimeException('No patient found or created');
        }

        $appointment->setPatient($patient);

        // Validate slot availability
        if (!$this->isSlotAvailable($appointment)) {
            throw new \RuntimeException('Selected time slot is no longer available');
        }

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();
    }

    private function createOrGetPatient(string $email): Patient
    {
        $existingPatient = $this->patientRepository->findOneBy(['email' => $email]);

        if ($existingPatient) {
            return $existingPatient;
        }

        $patient = $this->patientRepository->addPatient(new UserCreateRequestDto($email));
        # Create new account

        return $patient;
    }

    public function findAvailableSlots(array $criteria): array
    {
        $slots = $this->doctorScheduleRepository->findAvailableSlots($criteria);

        $groupedSlots = [];
        /** @var DoctorSchedule $slot */
        foreach ($slots as $slot) {
            $date = $slot->getDate()->format('Y-m-d');
            $doctorId = $slot->getDoctor()->getId();

            // Initialize doctor data if not exists for this date and doctor
            if (!isset($groupedSlots[$date][$doctorId])) {
                $groupedSlots[$date][$doctorId] = [
                    'id' => $doctorId,
                    'firstName' => $slot->getDoctor()->getFirstName(),
                    'lastName' => $slot->getDoctor()->getLastName(),
                    'slots' => []
                ];
            }

            /** @var TimeSlot $timeslot */
            foreach ($slot->getTimeSlots() as $timeslot) {
                $groupedSlots[$date][$doctorId]['slots'][] = [
                    'id' => $timeslot->getId(),
                    'startTime' => $timeslot->getStartTime()->format('H:i'),
                    'endTime' => $timeslot->getEndTime()->format('H:i')
                ];
            }
        }

        return $groupedSlots;
    }

    private function isSlotAvailable(Appointment $appointment): bool
    {
        $existingAppointment = $this->entityManager->getRepository(Appointment::class)
            ->findOneBy([
                'doctor' => $appointment->getDoctor(),
                'appointmentDate' => $appointment->getAppointmentDate(),
            ]);

        return !$existingAppointment || $existingAppointment->getId() === $appointment->getId();
    }

    public function getServicesBySpecialty(int $specialtyId): array
    {
        return $this->hospitalServiceRepository->findServicesBySpecialty($specialtyId);
    }

    public function getDoctorsByService(int $serviceId): array
    {
        return $this->doctorRepository->findDoctorsByService($serviceId);
    }
}
