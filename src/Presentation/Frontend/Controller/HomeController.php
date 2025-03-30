<?php

namespace App\Presentation\Frontend\Controller;

use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function home(
        AppointmentRepository $appointmentRepository,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        HospitalServiceRepository $hospitalServiceRepository,
        EntityManagerInterface $em
    ): Response {
        if ($this->getUser() && in_array(Patient::BASE_ROLE, $this->getUser()->getRoles())) {
            return $this->render('pages/appointments/identified.html.twig', [
                'appointments' => $appointmentRepository->findBy(['patient' => $this->getUser()]),
                'specialties' => $medicalSpecialtyRepository->findAll(),
                'services' => $hospitalServiceRepository->findAll(),
            ]);
        }

        if ($this->getUser() && in_array(Doctor::BASE_ROLE, $this->getUser()->getRoles())) {
            $doctor = $this->getUser();

            $schedules = $em->getRepository(DoctorSchedule::class)
                ->createQueryBuilder('ds')
                ->where('ds.doctor = :doctor')
                ->setParameter('doctor', $doctor)
                ->getQuery()
                ->getResult();

            $doctorSchedules = [];
            /** @var DoctorSchedule $schedule */
            foreach ($schedules as $schedule) {
                $slotsArray = [];
                foreach ($schedule->getTimeSlots() as $slot) {
                    $slotsArray[] = [
                        'startTime' => $slot->getStartTime()->format('H:i:s'),
                        'endTime'   => $slot->getEndTime()->format('H:i:s'),
                        'isBooked'  => $slot->isBooked(),
                    ];
                }
                $doctorSchedules[] = [
                    'date'  => $schedule->getDate()->format('Y-m-d'),
                    'slots' => $slotsArray,
                ];
            }

            $appointmentsEntities = $em->getRepository(Appointment::class)
                ->createQueryBuilder('a')
                ->join('a.timeSlot', 'ts')
                ->join('ts.schedule', 'ds')
                ->where('ds.doctor = :doctor')
                ->setParameter('doctor', $doctor)
                ->getQuery()
                ->getResult();


            $appointments = [];
            /** @var Appointment $appointment */
            foreach ($appointmentsEntities as $appointment) {
                $timeSlot = $appointment->getTimeSlot();
                $schedule = $timeSlot->getSchedule();
                $appointments[] = [
                    'id'    => $appointment->getId(),
                    'title' => $appointment->getPatient()->getFirstName() . ' ' .
                        $appointment->getPatient()->getLastName(),
                    'start' => $schedule->getDate()->format('Y-m-d') . 'T' .
                        $timeSlot->getStartTime()->format('H:i:s'),
                    'end'   => $schedule->getDate()->format('Y-m-d') . 'T' .
                        $timeSlot->getEndTime()->format('H:i:s'),
                ];
            }
            $services = $hospitalServiceRepository->findServicesByDoctor($this->getUser());
            return $this->render('pages/doctor/index.html.twig', [
                'appointments'     => json_encode($appointments),
                'doctorSchedules'  => json_encode($doctorSchedules),
                'services' => $services,
            ]);
        }

        if ($this->getUser() && in_array(User::ROLE_ADMIN, $this->getUser()->getRoles())) {
            $appointments = $appointmentRepository->findAll();

            // Get chart data
            $appointmentsByDay = $this->getAppointmentsByDayOfWeek($em);
            $appointmentsBySpecialty = $this->getAppointmentsBySpecialty($em);
            $appointmentsByHour = $this->getAppointmentsByHour($em);

            return $this->render('pages/admin/index.html.twig', [
                'appointments' => $appointments,
                'chartData' => [
                    'appointmentsByDay' => $appointmentsByDay,
                    'appointmentsBySpecialty' => $appointmentsBySpecialty,
                    'appointmentsByHour' => $appointmentsByHour
                ]
            ]);
        }

        return $this->render('pages/appointments/anonymous.html.twig');
    }

    #[Route('/schedules', name: 'homepage_schedules', methods: ['GET'])]
    public function schedules(AppointmentRepository $appointmentRepository, EntityManagerInterface $em): Response
    {
        return $this->render('pages/admin/schedules.html.twig', [
            'appointments' => $appointmentRepository->findAll()
        ]);
    }

    /**
     * Get appointment counts by day of week (Monday to Sunday)
     */
    private function getAppointmentsByDayOfWeek(EntityManagerInterface $em): array
    {
        // Define day mapping (we'll manually count by day)
        $dayNameMap = [
            1 => 'Luni',
            2 => 'Marți',
            3 => 'Miercuri',
            4 => 'Joi',
            5 => 'Vineri',
            6 => 'Sâmbătă',
            7 => 'Duminică'
        ];

        // Initialize the results array with all days set to 0
        $appointmentsByDay = [
            'categories' => array_values($dayNameMap),
            'data' => array_fill(0, 7, 0)
        ];

        // Get all appointments with schedule dates
        $appointments = $em->createQueryBuilder()
            ->select('a', 's.date')
            ->from('App\Infrastructure\Entity\Appointment', 'a')
            ->join('a.timeSlot', 'ts')
            ->join('ts.schedule', 's')
            ->getQuery()
            ->getResult();

        // Manually count appointments by day of week
        foreach ($appointments as $appointment) {
            $scheduleDate = $appointment[0]->getTimeSlot()->getSchedule()->getDate();
            $dayOfWeek = (int)$scheduleDate->format('N'); // 1 (Monday) to 7 (Sunday)

            // Increment the count for this day
            $appointmentsByDay['data'][$dayOfWeek - 1]++;
        }

        return $appointmentsByDay;
    }

    /**
     * Get appointment counts by specialty
     */
    private function getAppointmentsBySpecialty(EntityManagerInterface $em): array
    {
        // Get all appointments with their specialties
        $appointments = $em->createQueryBuilder()
            ->select('a', 'ms')
            ->from('App\Infrastructure\Entity\Appointment', 'a')
            ->join('a.medicalSpecialty', 'ms')
            ->getQuery()
            ->getResult();

        // Count appointments by specialty
        $specialtyCounts = [];
        foreach ($appointments as $appointment) {
            $specialty = $appointment->getMedicalSpecialty();
            $specialtyName = $specialty->getName();

            if (!isset($specialtyCounts[$specialtyName])) {
                $specialtyCounts[$specialtyName] = 0;
            }

            $specialtyCounts[$specialtyName]++;
        }

        // Sort by count in descending order
        arsort($specialtyCounts);

        // Limit to top 5 specialties
        $specialtyCounts = array_slice($specialtyCounts, 0, 5, true);

        // Format for chart
        $appointmentsBySpecialty = [
            'categories' => array_keys($specialtyCounts),
            'data' => array_values($specialtyCounts)
        ];

        return $appointmentsBySpecialty;
    }

    /**
     * Get appointment counts by hour of day
     */
    private function getAppointmentsByHour(EntityManagerInterface $em): array
    {
        // Initialize hours with labels and zero counts
        $appointmentsByHour = [
            'categories' => [],
            'data' => []
        ];

        // Fill in hour labels (8 AM to 5 PM)
        for ($hour = 8; $hour <= 17; $hour++) {
            $appointmentsByHour['categories'][] = $hour . ' ' . ($hour < 12 ? 'AM' : 'PM');
            $appointmentsByHour['data'][] = 0;
        }

        // Get all appointments with their time slots
        $appointments = $em->createQueryBuilder()
            ->select('a', 'ts')
            ->from('App\Infrastructure\Entity\Appointment', 'a')
            ->join('a.timeSlot', 'ts')
            ->getQuery()
            ->getResult();

        // Manually count appointments by hour
        foreach ($appointments as $appointment) {
            $startTime = $appointment->getTimeSlot()->getStartTime();
            $hour = (int)$startTime->format('H');

            // Only count hours between 8 AM and 5 PM
            if ($hour >= 8 && $hour <= 17) {
                $index = $hour - 8;
                $appointmentsByHour['data'][$index]++;
            }
        }

        return $appointmentsByHour;
    }
}