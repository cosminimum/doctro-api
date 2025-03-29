<?php

namespace App\Presentation\Frontend\Controller;

use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\Patient;
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
            return $this->render('pages/admin/index.html.twig', [
                'appointments' => $appointmentRepository->findAll()
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
}
