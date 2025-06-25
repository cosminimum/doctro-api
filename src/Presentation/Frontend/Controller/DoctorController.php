<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\PatientRegisterStory;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\DoctorRepository;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use App\Infrastructure\Repository\PatientRepository;
use App\Infrastructure\Repository\TimeSlotRepository;
use App\Presentation\Frontend\Form\DoctorAppointmentFormType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DoctorController extends AbstractController
{
    #[Route('/doctor/appointments/new', name: 'doctor_add_appointment')]
    public function addAppointment(
        Request $request,
        EntityManagerInterface $em,
        PatientRepository $patientRepo,
        DoctorScheduleRepository $doctorScheduleRepository,
        PatientRegisterStory $patientRegisterStory,
        HospitalServiceRepository $hospitalServiceRepository,
    ): Response {
        $form = $this->createForm(DoctorAppointmentFormType::class);
        $form->handleRequest($request);

        $selectedDateTime = $request->query->get('date');
        if ($selectedDateTime) {
            try {
                $defaultAppointmentStart = new \DateTime($selectedDateTime);
                $form->get('appointmentStart')->setData($defaultAppointmentStart);
            } catch (\Exception $e) {
                //
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $doctor = $this->getUser();
            if (!$doctor) {
                throw $this->createAccessDeniedException('Nu sunteți autentificat ca medic.');
            }

            $email = !empty($data['email']) ? $data['email'] : $this->generateUniqueEmail($data['firstName'], $data['lastName'], $patientRepo);
            $cnp = !empty($data['cnp']) ? $data['cnp'] : $this->generateUniquePlaceholderCNP($patientRepo);

            // Look for patient by email or by phone if email is auto-generated
            $patient = null;
            if (!empty($data['email'])) {
                $patient = $patientRepo->findOneBy(['email' => $email]);
            }

            if (!$patient && !empty($data['phone'])) {
                $patient = $patientRepo->findOneBy(['phone' => $data['phone']]);
            }

            if (!$patient) {
                $patient = $patientRegisterStory->register(
                    new UserCreateRequestDto(
                        $email,
                        $data['firstName'],
                        $data['lastName'],
                        $cnp,
                        $data['phone'],
                        base64_encode(random_bytes(10))
                    )
                );
            }

            $specialty = $data['specialty'];
            $service   = $data['service'];
            $appointmentStart = $data['appointmentStart'];

            $duration = (int)$service->getDuration();
            $requiredSlots = $duration / 30;

            $appointmentDate = $appointmentStart->format('Y-m-d');
            $appointmentTime = $appointmentStart->format('H:i');

            $schedule = $doctorScheduleRepository->findOneBy([
                'doctor' => $doctor,
                'date'   => new \DateTime($appointmentDate),
            ]);
            if (!$schedule) {
                $this->addFlash('error', 'Nu există program pentru data selectată.');
                return $this->redirectToRoute('homepage');
            }

            $timeslots = $schedule->getTimeSlots()->toArray();
            usort($timeslots, function($a, $b) {
                return $a->getStartTime() <=> $b->getStartTime();
            });

            $block = [];
            foreach ($timeslots as $slot) {
                if ($slot->isBooked()) {
                    continue;
                }
                $slotStart = $slot->getStartTime()->format('H:i');

                if (empty($block)) {
                    if ($slotStart === $appointmentTime) {
                        $block[] = $slot;
                    }
                } else {
                    $prevSlot = end($block);
                    $expectedStart = $prevSlot->getEndTime()->format('H:i');
                    if ($slotStart === $expectedStart) {
                        $block[] = $slot;
                    } else {
                        break;
                    }
                }
                if (count($block) == $requiredSlots) {
                    break;
                }
            }

            if (count($block) < $requiredSlots) {
                $this->addFlash('error', 'Nu există suficiente intervale orare consecutive disponibile pentru serviciul selectat.');
                return $this->redirectToRoute('homepage');
            }

            foreach ($block as $slot) {
                $slot->setIsBooked(true);
                $em->persist($slot);
            }

            $appointment = new Appointment();
            $appointment->setPatient($patient);
            $appointment->setDoctor($doctor);
            $appointment->setMedicalSpecialty($specialty);
            $appointment->setHospitalService($service);
            $appointment->setTimeSlot($block[0]);
            $appointment->setIsActive(true);

            $em->persist($appointment);
            $em->flush();

            $this->addFlash('success', 'Programare creată cu succes!');

            $timeSlot = $appointment->getTimeSlot();
            $doctor = $appointment->getDoctor();
            $service = $appointment->getHospitalService();

            $appointmentDate = $timeSlot->getSchedule()->getDate()->format('d.m.Y');
            $appointmentTime = $timeSlot->getStartTime()->format('H:i');
            $doctorName = $doctor->getLastName() . ' ' . $doctor->getFirstName();
            $serviceName = $service->getName();
            // Create SMS message
            $message = "Confirmam programarea in data {$appointmentDate} ora {$appointmentTime} {$serviceName} Dr. {$doctorName}. Accesul se face cu acest sms.";
            $encodedMessage = urlencode($message);

            // Get patient phone number
            $phoneNumber = $patient->getPhone();
            // Remove leading zero if present
            if (substr($phoneNumber, 0, 1) === '0') {
                $phoneNumber = '4' . $phoneNumber;
            } elseif (substr($phoneNumber, 0, 1) !== '4') {
                $phoneNumber = '40' . $phoneNumber;
            }

            // Build and execute SMS API call
            $smsUrl = sprintf($this->getParameter('sms_url'), $phoneNumber, $encodedMessage);

            try {
                $smsResponse = file_get_contents($smsUrl);
            } catch (\Exception $smsException) {
                // Log error but don't interrupt the flow
            }
            return $this->redirectToRoute('homepage');
        }

        $doctor = $this->getUser();
        $today = new \DateTime();

        $startOfWeek = (clone $today)->modify('monday this week');
        $endOfWeek   = (clone $today)->modify('sunday this week');

        $schedules = $em->getRepository(DoctorSchedule::class)
            ->createQueryBuilder('ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startOfWeek->format('Y-m-d'))
            ->setParameter('end', $endOfWeek->format('Y-m-d'))
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
            ->andWhere('ds.date BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startOfWeek->format('Y-m-d'))
            ->setParameter('end', $endOfWeek->format('Y-m-d'))
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
                    $appointment->getPatient()->getLastName() . ' - ' .
                    $appointment->getMedicalSpecialty()->getName(),
                'start' => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getStartTime()->format('H:i:s'),
                'end'   => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getEndTime()->format('H:i:s'),
            ];
        }

        $services = $hospitalServiceRepository->findServicesByDoctor($this->getUser());

        return $this->render('pages/doctor/appointments/new.html.twig', [
            'form' => $form->createView(),
            'appointments' => json_encode($appointments),
            'doctorSchedules' => json_encode($doctorSchedules),
            'services' => $services,
        ]);
    }

    /**
     * Generate a unique placeholder email based on patient names
     * @TODO Move this into a service
     */
    private function generateUniqueEmail(string $firstName, string $lastName, PatientRepository $patientRepo): string
    {
        $hash = substr(md5(uniqid() . $firstName . $lastName), 0, 12);
        $baseEmail = $hash . '@generatedaccount.com';

        $counter = 0;
        $email = $baseEmail;

        while ($patientRepo->findOneBy(['email' => $email]) !== null && $counter < 10) {
            $counter++;
            $hash = substr(md5(uniqid() . $firstName . $lastName . $counter), 0, 12);
            $email = $hash . '@generatedaccount.com';
        }

        return $email;
    }

    /**
     * Generate a unique placeholder CNP
     * @TODO Move this into a service
     */
    private function generateUniquePlaceholderCNP(PatientRepository $patientRepo): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $cnp = '';

        for ($i = 0; $i < 13; $i++) {
            $cnp .= $chars[rand(0, strlen($chars) - 1)];
        }

        $counter = 0;
        while ($patientRepo->findOneBy(['cnp' => $cnp]) !== null && $counter < 10) {
            $counter++;
            $cnp = '';
            for ($i = 0; $i < 13; $i++) {
                $cnp .= $chars[rand(0, strlen($chars) - 1)];
            }
        }

        return $cnp;
    }

    #[Route('/doctor/appointments/{id}/approve', name: 'doctor_approve_appointment')]
    public function approveAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepo,
        EntityManagerInterface $em,
        DoctorScheduleRepository $doctorScheduleRepository
    ): Response {
        $appointment = $appointmentRepo->find($id);
        if (!$appointment) {
            throw $this->createNotFoundException('Programarea nu a fost găsită.');
        }

        $appointment->setIsActive(true);
        $em->persist($appointment);
        $em->flush();

        $this->addFlash('success', 'Programarea a fost aprobata cu succes!');
        return $this->redirectToRoute('doctor_appointments');

    }

    #[Route('/doctor/appointments/{id}/edit', name: 'doctor_edit_appointment')]
    public function editAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepo,
        EntityManagerInterface $em,
        DoctorScheduleRepository $doctorScheduleRepository,
        HospitalServiceRepository $hospitalServiceRepository
    ): Response {
        $appointment = $appointmentRepo->find($id);
        if (!$appointment) {
            throw $this->createNotFoundException('Programarea nu a fost găsită.');
        }

        $oldSchedule = $appointment->getTimeSlot()->getSchedule();
        $oldDateStr = $oldSchedule->getDate()->format('Y-m-d');
        $oldTimeStr = $appointment->getTimeSlot()->getStartTime()->format('H:i');
        $oldAppointmentStart = \DateTime::createFromFormat('Y-m-d H:i', $oldDateStr . ' ' . $oldTimeStr);

        $formData = [
            'specialty'        => $appointment->getMedicalSpecialty(),
            'service'          => $appointment->getHospitalService(),
            'appointmentStart' => $oldAppointmentStart,
            'firstName'        => $appointment->getPatient()->getFirstName(),
            'lastName'         => $appointment->getPatient()->getLastName(),
            'email'            => $appointment->getPatient()->getEmail(),
            'phone'            => $appointment->getPatient()->getPhone(),
            'cnp'              => $appointment->getPatient()->getCnp(),
        ];

        $form = $this->createForm(DoctorAppointmentFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $doctor = $this->getUser();
            if (!$doctor) {
                throw $this->createAccessDeniedException('Nu sunteți autentificat ca medic.');
            }

            $patient = $appointment->getPatient();
            $patient->setFirstName($data['firstName']);
            $patient->setLastName($data['lastName']);
            $patient->setEmail($data['email']);
            $patient->setPhone($data['phone']);
            $patient->setCnp($data['cnp']);
            $em->persist($patient);

            $newSpecialty = $data['specialty'];
            $newService   = $data['service'];
            $appointment->setMedicalSpecialty($newSpecialty);
            $appointment->setHospitalService($newService);

            $newAppointmentStart = $data['appointmentStart'];
            $oldStartStr = $oldAppointmentStart->format('Y-m-d H:i');
            $newStartStr = $newAppointmentStart->format('Y-m-d H:i');

            $timeChanged = $oldStartStr !== $newStartStr;
            $serviceChanged = $appointment->getHospitalService()->getId() !== $newService->getId();

            if ($timeChanged || $serviceChanged) {
                $oldDuration = (int) $appointment->getHospitalService()->getDuration();
                $oldRequiredSlots = $oldDuration / 30;

                $oldSlots = $oldSchedule->getTimeSlots()->toArray();
                usort($oldSlots, function ($a, $b) {
                    return $a->getStartTime() <=> $b->getStartTime();
                });
                $oldBlock = [];
                foreach ($oldSlots as $slot) {
                    $slotStartStr = $slot->getStartTime()->format('H:i');
                    if (empty($oldBlock)) {
                        if ($slotStartStr === $oldTimeStr) {
                            $oldBlock[] = $slot;
                        }
                    } else {
                        $prevSlot = end($oldBlock);
                        $expectedStart = $prevSlot->getEndTime()->format('H:i');
                        if ($slotStartStr === $expectedStart) {
                            $oldBlock[] = $slot;
                        } else {
                            break;
                        }
                    }
                    if (count($oldBlock) == $oldRequiredSlots) {
                        break;
                    }
                }

                foreach ($oldBlock as $slot) {
                    $slot->setIsBooked(false);
                    $em->persist($slot);
                }

                $newDuration = (int) $newService->getDuration();
                $newRequiredSlots = $newDuration / 30;
                $newDateStr = $newAppointmentStart->format('Y-m-d');
                $newTimeStr = $newAppointmentStart->format('H:i');

                $newSchedule = $doctorScheduleRepository->findOneBy([
                    'doctor' => $doctor,
                    'date'   => new \DateTime($newDateStr),
                ]);
                if (!$newSchedule) {
                    $this->addFlash('error', 'Nu există program pentru data selectată.');
                    return $this->render('pages/doctor/appointments/edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $newSlots = $newSchedule->getTimeSlots()->toArray();
                usort($newSlots, function ($a, $b) {
                    return $a->getStartTime() <=> $b->getStartTime();
                });
                $newBlock = [];
                foreach ($newSlots as $slot) {
                    if ($slot->isBooked()) {
                        continue;
                    }
                    $slotStartStr = $slot->getStartTime()->format('H:i');
                    if (empty($newBlock)) {
                        if ($slotStartStr === $newTimeStr) {
                            $newBlock[] = $slot;
                        }
                    } else {
                        $prevSlot = end($newBlock);
                        $expectedStart = $prevSlot->getEndTime()->format('H:i');
                        if ($slotStartStr === $expectedStart) {
                            $newBlock[] = $slot;
                        } else {
                            break;
                        }
                    }
                    if (count($newBlock) == $newRequiredSlots) {
                        break;
                    }
                }
                if (count($newBlock) < $newRequiredSlots) {
                    $this->addFlash('error', 'Nu există suficiente intervale orare consecutive disponibile pentru serviciul selectat.');
                    return $this->render('pages/doctor/appointments/edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                foreach ($newBlock as $slot) {
                    $slot->setIsBooked(true);
                    $em->persist($slot);
                }

                $appointment->setTimeSlot($newBlock[0]);
            }

            $em->persist($appointment);
            $em->flush();

            $this->addFlash('success', 'Programarea a fost actualizată cu succes!');
            return $this->redirectToRoute('homepage');
        }

        $doctor = $this->getUser();
        $today = new \DateTime();

        $startOfWeek = (clone $today)->modify('monday this week');
        $endOfWeek   = (clone $today)->modify('sunday this week');

        $schedules = $em->getRepository(DoctorSchedule::class)
            ->createQueryBuilder('ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startOfWeek->format('Y-m-d'))
            ->setParameter('end', $endOfWeek->format('Y-m-d'))
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
            ->andWhere('ds.date BETWEEN :start AND :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startOfWeek->format('Y-m-d'))
            ->setParameter('end', $endOfWeek->format('Y-m-d'))
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
                    $appointment->getPatient()->getLastName() . ' - ' .
                    $appointment->getMedicalSpecialty()->getName(),
                'start' => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getStartTime()->format('H:i:s'),
                'end'   => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getEndTime()->format('H:i:s'),
            ];
        }

        $services = $hospitalServiceRepository->findServicesByDoctor($this->getUser());
        return $this->render('pages/doctor/appointments/edit.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'appointments' => json_encode($appointments),
            'doctorSchedules' => json_encode($doctorSchedules),
            'services' => $services
        ]);
    }

    #[Route('/doctor/appointments/{id}/delete', name: 'doctor_delete_appointment')]
    public function deleteAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepo,
        EntityManagerInterface $em
    ): Response {
        $appointment = $appointmentRepo->find($id);
        if (!$appointment) {
            throw $this->createNotFoundException('Programarea nu a fost găsită.');
        }

        if ($this->isCsrfTokenValid('delete' . $appointment->getId(), $request->request->get('_token'))) {
            $appointment->getTimeSlot()->setIsBooked(false);
            $appointment->setIsActive(false);
            $em->persist($appointment);
            $em->flush();
            $this->addFlash('success', 'Programarea a fost anulată cu succes!');
        } else {
            $this->addFlash('error', 'Tokenul CSRF nu este valid.');
        }

        return $this->redirectToRoute('homepage');
    }

    #[Route('/doctor/service', name: 'doctor_services')]
    public function service(HospitalServiceRepository $hospitalServiceRepository): Response
    {
        return $this->render('pages/doctor/service.html.twig', [
            'services' => $hospitalServiceRepository->findServicesByDoctor($this->getUser()),
            'hospitalServices' => $hospitalServiceRepository->findBy(['isActive' => true])
        ]);
    }

    #[Route('/doctor/appointments', name: 'doctor_appointments')]
    public function appointments(AppointmentRepository $appointmentRepository): Response
    {
        return $this->render('pages/doctor/appointments/index.html.twig', [
            'appointments' => $appointmentRepository->findBy(['doctor' => $this->getUser()])
        ]);
    }

    #[Route('/doctor/service/{id}/link', name: 'doctor_link_service', methods: ['GET', 'POST'])]
    public function link(HospitalService $service, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$service->getDoctors()->contains($this->getUser())) {
            $service->addDoctor($this->getUser());
            $entityManager->persist($service);
            $entityManager->flush();

            $this->addFlash('success', 'Serviciul a fost alocat cu succes.');
        } else {
            $this->addFlash('warning', 'Acest serviciu este deja alocat');
        }

        return $this->json(["success" => true], 200);
    }

    #[Route('/doctor/service/{id}/unlink', name: 'doctor_unlink_service', methods: ['GET', 'POST'])]
    public function unlink(
        HospitalService $service,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $doctor = $this->getUser();

        if ($service->getDoctors()->contains($doctor)) {
            $service->getDoctors()->removeElement($doctor);
            $doctor->getHospitalServices()->removeElement($service);

            $entityManager->flush();

            $this->addFlash('success', 'Serviciul a fost dealocat');
        } else {
            $this->addFlash('warning', 'Serviciul nu a fost alocat');
        }

        return $this->json(["success" => true], 200);
    }

    #[Route('/doctor/schedule/configure', name: 'doctor_configure_schedule', methods: ['POST'])]
    public function configureSchedule(Request $request, EntityManagerInterface $em, HospitalServiceRepository $hospitalServiceRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['repeatUntil'], $data['schedules'], $data['serviceId'])) {
            $this->addFlash('error', 'Payload invalid');
            return new JsonResponse(['success' => false, 'message' => 'Payload invalid'], 400);
        }

        $serviceId = $data['serviceId'];
        $service = $hospitalServiceRepository->find($serviceId);
        if (!$service) {
            $this->addFlash('error', 'Serviciul selectat nu a fost gasit');
            return new JsonResponse(['success' => false, 'message' => 'Serviciul selectat nu a fost găsit'], 400);
        }

        $repeatUntil = \DateTime::createFromFormat('d-m-Y', $data['repeatUntil']);
        if (!$repeatUntil) {
            $this->addFlash('error', 'Data "repeta pana la" invalidă');
            return new JsonResponse(['success' => false, 'message' => 'Data "repeta pana la" invalidă'], 400);
        }

        $repeatWeekType = $data['repeatWeekType'] ?? 'all';

        $doctor = $this->getUser();
        $today = new \DateTime();
        $interval = new \DateInterval('P1D');

        $repeatUntilInclusive = (clone $repeatUntil)->modify('+1 day');
        $period = new \DatePeriod($today, $interval, $repeatUntilInclusive);

        $dayNameToNumber = [
            'Monday'    => 1,
            'Tuesday'   => 2,
            'Wednesday' => 3,
            'Thursday'  => 4,
            'Friday'    => 5,
            'Saturday'  => 6,
            'Sunday'    => 7,
        ];

        foreach ($period as $date) {
            $dayNumber = (int)$date->format('N'); // 1-7

            $weekNumber = (int)$date->format('W');
            $isEvenWeek = $weekNumber % 2 === 0;

            if ($repeatWeekType === 'even' && !$isEvenWeek) {
                continue;
            }

            if ($repeatWeekType === 'odd' && $isEvenWeek) {
                continue;
            }

            $scheduleConfig = null;
            foreach ($data['schedules'] as $dayName => $config) {
                if (isset($dayNameToNumber[$dayName]) && $dayNameToNumber[$dayName] === $dayNumber && !empty($config['active'])) {
                    $scheduleConfig = $config;
                    break;
                }
            }

            if ($scheduleConfig) {
                $existingSchedule = $em->getRepository(DoctorSchedule::class)->findOneBy([
                    'doctor' => $doctor,
                    'date'   => $date
                ]);

                if ($existingSchedule) {
                    foreach ($existingSchedule->getTimeSlots() as $slot) {
                        if (!$slot->isBooked()) {
                            $em->remove($slot);
                        } else {
                            continue 2;
                        }
                    }
                } else {
                    $existingSchedule = new DoctorSchedule();
                    $existingSchedule->setDoctor($doctor);
                    $existingSchedule->setDate(clone $date);
                    $em->persist($existingSchedule);
                }

                $startTime = \DateTime::createFromFormat('H:i', $scheduleConfig['start']);
                $endTime   = \DateTime::createFromFormat('H:i', $scheduleConfig['end']);
                if (!$startTime || !$endTime) {
                    $this->addFlash('error', 'Format orar invalid');
                    return new JsonResponse(['success' => false, 'message' => 'Format orar invalid'], 400);
                }

                $currentSlotStart = (clone $date)->setTime((int)$startTime->format('H'), (int)$startTime->format('i'));
                $slotEndTime = (clone $date)->setTime((int)$endTime->format('H'), (int)$endTime->format('i'));

                while ($currentSlotStart < $slotEndTime) {
                    $currentSlotEnd = (clone $currentSlotStart)->modify('+30 minutes');
                    if ($currentSlotEnd > $slotEndTime) {
                        break;
                    }

                    $timeSlot = new TimeSlot();
                    $timeSlot->setSchedule($existingSchedule);
                    $timeSlot->setStartTime(clone $currentSlotStart);
                    $timeSlot->setEndTime(clone $currentSlotEnd);
                    $timeSlot->setIsBooked(false);
                    $timeSlot->setHospitalService($service);

                    $em->persist($timeSlot);
                    $existingSchedule->getTimeSlots()->add($timeSlot);

                    $currentSlotStart = $currentSlotEnd;
                }
            }
        }

        $em->flush();
        $this->addFlash('success', 'Program configurat cu succes.');
        return new JsonResponse(['success' => true, 'message' => 'Program configurat cu succes.']);
    }

    #[Route('/doctor/schedule/block', name: 'doctor_block_slots', methods: ['POST'])]
    public function blockSlots(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['start'], $data['end'])) {
            $this->addFlash('error', 'Eroare generică');
            return new JsonResponse(['success' => false, 'message' => 'Payload invalid'], 400);
        }

        $startDateTime = \DateTime::createFromFormat('d-m-Y H:i', $data['start']);
        $endDateTime   = \DateTime::createFromFormat('d-m-Y H:i', $data['end']);
        if (!$startDateTime || !$endDateTime) {
            $this->addFlash('error', 'Format dată/oră invalid');
            return new JsonResponse(['success' => false, 'message' => ''], 400);
        }
        if ($startDateTime >= $endDateTime) {
            $this->addFlash('error', 'Data de început trebuie să fie înaintea datei de sfârșit');
            return new JsonResponse(['success' => false, 'message' => 'Data de început trebuie să fie înaintea datei de sfârșit'], 400);
        }

        $doctor = $this->getUser();

        $schedules = $em->getRepository(DoctorSchedule::class)
            ->createQueryBuilder('ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->setParameter('doctor', $doctor)
            ->setParameter('startDate', $startDateTime->format('Y-m-d'))
            ->setParameter('endDate', $endDateTime->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $slotsToBlock = [];
        foreach ($schedules as $schedule) {
            foreach ($schedule->getTimeSlots() as $slot) {
                $slotDateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $schedule->getDate()->format('Y-m-d') . ' ' . $slot->getStartTime()->format('H:i:s')
                );
                if ($slotDateTime >= $startDateTime && $slotDateTime < $endDateTime) {
                    if ($slot->isBooked()) {
                        $this->addFlash('error', 'Nu puteți bloca sloturi care au deja o programare.');
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Nu puteți bloca sloturi care au deja o programare.'
                        ], 400);
                    }
                    $slotsToBlock[] = $slot;
                }
            }
        }

        foreach ($slotsToBlock as $slot) {
            $slot->setIsBooked(true);
            $em->persist($slot);
        }

        $em->flush();
        $this->addFlash('error', 'Sloturile au fost blocate cu succes.');
        return new JsonResponse(['success' => true, 'message' => 'Sloturile au fost blocate cu succes.']);
    }

    #[Route('/doctor/schedule/remove', name: 'doctor_remove_slots', methods: ['POST'])]
    public function removeSlots(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['start'], $data['end'])) {
            $this->addFlash('error', 'Eroare generică');
            return new JsonResponse(['success' => false, 'message' => 'Payload invalid'], 400);
        }

        $startDateTime = \DateTime::createFromFormat('d-m-Y H:i', $data['start']);
        $endDateTime   = \DateTime::createFromFormat('d-m-Y H:i', $data['end']);
        if (!$startDateTime || !$endDateTime) {
            $this->addFlash('error', 'Format dată/oră invalid');
            return new JsonResponse(['success' => false, 'message' => 'Format dată/oră invalid'], 400);
        }
        if ($startDateTime >= $endDateTime) {
            $this->addFlash('error', 'Data de început trebuie să fie înaintea datei de sfârșit');
            return new JsonResponse(['success' => false, 'message' => 'Data de început trebuie să fie înaintea datei de sfârșit'], 400);
        }

        $doctor = $this->getUser();

        $schedules = $em->getRepository(DoctorSchedule::class)
            ->createQueryBuilder('ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->setParameter('doctor', $doctor)
            ->setParameter('startDate', $startDateTime->format('Y-m-d'))
            ->setParameter('endDate', $endDateTime->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $slotsToRemove = [];
        foreach ($schedules as $schedule) {
            foreach ($schedule->getTimeSlots() as $slot) {
                $slotDateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $schedule->getDate()->format('Y-m-d') . ' ' . $slot->getStartTime()->format('H:i:s')
                );
                if ($slotDateTime >= $startDateTime && $slotDateTime < $endDateTime) {
                    if ($slot->isBooked()) {
                        $this->addFlash('error', 'Nu puteți șterge sloturi care au deja o programare.');
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Nu puteți șterge sloturi care au deja o programare.'
                        ], 400);
                    }
                    $slotsToRemove[] = $slot;
                }
            }
        }

        foreach ($slotsToRemove as $slot) {
            $em->remove($slot);
        }

        $em->flush();
        $this->addFlash('success', 'Sloturile au fost șterse cu succes.');
        return new JsonResponse(['success' => true, 'message' => 'Sloturile au fost șterse cu succes.']);
    }

    #[Route('/doctor/appointments/today/pdf', name: 'doctor_appointments_today_pdf', methods: ['GET'])]
    public function appointmentsTodayPdf(EntityManagerInterface $em): Response
    {
        $doctor = $this->getUser();
        $today = new \DateTime();

        $appointments = $em->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->join('a.timeSlot', 'ts')
            ->join('ts.schedule', 'ds')
            ->where('ds.doctor = :doctor')
            ->andWhere('ds.date = :today')
            ->setParameter('doctor', $doctor)
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $html = $this->renderView('pdf/appointments_today.html.twig', [
            'appointments' => $appointments,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }

    #[Route('/doctor/schedules', name: 'doctor_manage_schedules')]
    public function manageSchedules(EntityManagerInterface $em): Response
    {
        $doctor = $this->getUser();

        $schedules = $em->getRepository(DoctorSchedule::class)
            ->createQueryBuilder('ds')
            ->where('ds.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->orderBy('ds.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/doctor/schedule.html.twig', [
            'schedules' => $schedules
        ]);
    }

    #[Route('/doctor/slot/{id}/delete', name: 'doctor_delete_slot', methods: ['POST'])]
    public function deleteSlot(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $slot = $em->getRepository(TimeSlot::class)->find($id);

        if (!$slot) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Intervalul orar nu a fost găsit'
            ], 404);
        }

        if ($slot->getSchedule()->getDoctor()->getId() !== $this->getUser()->getId()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nu aveți permisiunea de a șterge acest interval orar'
            ], 403);
        }

        if ($slot->isBooked()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nu puteți șterge un interval orar rezervat'
            ], 400);
        }

        $appointments = $em->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.timeSlot = :timeSlot')
            ->setParameter('timeSlot', $slot)
            ->getQuery()
            ->getResult();

        if (count($appointments) > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nu puteți șterge un interval orar care are programări'
            ], 400);
        }

        try {
            $em->remove($slot);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Intervalul orar a fost șters cu succes'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'A apărut o eroare la ștergerea intervalului orar: ' . $e->getMessage()
            ], 500);
        }
    }
}
