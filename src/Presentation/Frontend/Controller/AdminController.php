<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\DoctorRegisterStory;
use App\Application\Story\PatientRegisterStory;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\HospitalSettings;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\DoctorRepository;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\HospitalSettingsRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use App\Infrastructure\Repository\PatientRepository;
use App\Infrastructure\Repository\TimeSlotRepository;
use App\Presentation\Frontend\Form\AdminAppointmentFormType;
use App\Presentation\Frontend\Form\AdminDoctorFormType;
use App\Presentation\Frontend\Form\AdminServiceFormType;
use App\Presentation\Frontend\Form\AdminSpecialtyFormType;
use App\Presentation\Frontend\Form\HospitalSettingsFormType;
use App\Presentation\Frontend\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin/doctors', name: 'admin_doctors', methods: ['GET', 'POST'])]
    public function doctorList(
        Request $request,
        DoctorRepository $doctorRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminDoctorFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Doctor $doctor */
            $doctor = $form->getData();

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $hashedPassword = $passwordHasher->hashPassword(
                $doctor,
                $plainPassword
            );

            $doctor->setPassword($hashedPassword);
            $doctor->setRoles([Doctor::BASE_ROLE]);

            $em->persist($doctor);
            $em->flush();

            $this->addFlash('success', 'Doctorul a fost creat cu succes.');
            return $this->redirectToRoute('admin_doctors');
        }

        return $this->render('pages/admin/doctors/index.html.twig', [
            'doctors'    => $doctorRepository->findAll(),
            'doctorForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/doctors/{id}/edit', name: 'admin_edit_doctor', methods: ['GET', 'POST'])]
    public function editDoctor(
        Request $request,
        DoctorRepository $doctorRepository,
        UserPasswordHasherInterface $passwordHasher,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $doctor = $doctorRepository->find($id);
        if (!$doctor) {
            throw $this->createNotFoundException('Doctorul nu a fost găsit.');
        }

        $form = $this->createForm(AdminDoctorFormType::class, $doctor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $doctor,
                    $plainPassword
                );
                $doctor->setPassword($hashedPassword);
            }

            $em->flush();
            $this->addFlash('success', 'Doctorul a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_doctors');
        }

        return $this->render('pages/admin/doctors/edit.html.twig', [
            'doctorForm' => $form->createView(),
            'doctor'     => $doctor,
        ]);
    }

    #[Route('/admin/doctors/{id}/delete', name: 'admin_delete_doctor', methods: ['POST'])]
    public function deleteDoctor(
        Request $request,
        Doctor $doctor,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$doctor->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($doctor);
                $em->flush();
                $this->addFlash('success', 'Doctorul a fost șters cu succes.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Doctorul nu poate fi șters deoarece are programări asociate.');
            }
        }

        return $this->redirectToRoute('admin_doctors');
    }

    #[Route('/admin/services', name: 'admin_services', methods: ['GET', 'POST'])]
    public function serviceList(
        Request $request,
        HospitalServiceRepository $hospitalServiceRepository,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminServiceFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var HospitalService $service */
            $service = $form->getData();
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Serviciul a fost creat cu succes.');
            return $this->redirectToRoute('admin_services');
        }

        return $this->render('pages/admin/services/index.html.twig', [
            'services'    => $hospitalServiceRepository->findAll(),
            'serviceForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/services/{id}/edit', name: 'admin_edit_service', methods: ['GET', 'POST'])]
    public function editService(
        Request $request,
        HospitalServiceRepository $hospitalServiceRepository,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $service = $hospitalServiceRepository->find($id);
        if (!$service) {
            throw $this->createNotFoundException('Serviciul nu a fost găsit.');
        }
        $form = $this->createForm(AdminServiceFormType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Serviciul a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_services');
        }

        return $this->render('pages/admin/services/edit.html.twig', [
            'serviceForm' => $form->createView(),
            'service'     => $service,
        ]);
    }

    #[Route('/admin/services/{id}/delete', name: 'admin_delete_service', methods: ['POST'])]
    public function deleteService(
        Request $request,
        HospitalService $service,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($service);
                $em->flush();
                $this->addFlash('success', 'Serviciul a fost șters cu succes.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Serviciul nu poate fi șters deoarece are programări asociate.');
            }
        }

        return $this->redirectToRoute('admin_services');
    }

    #[Route('/admin/specialties', name: 'admin_specialties', methods: ['GET', 'POST'])]
    public function adminSpecialties(
        Request $request,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(AdminSpecialtyFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var MedicalSpecialty $service */
            $specialty = $form->getData();
            $em->persist($specialty);
            $em->flush();
            $this->addFlash('success', 'Specialitatea a fost creat cu succes.');
            return $this->redirectToRoute('admin_specialties');
        }

        return $this->render('pages/admin/specialties/index.html.twig', [
            'specialties'    => $medicalSpecialtyRepository->findAll(),
            'specialtyForm' => $form->createView(),
        ]);
    }

    #[Route('/admin/specialties/{id}/edit', name: 'admin_edit_specialty', methods: ['GET', 'POST'])]
    public function editSpecialty(
        Request $request,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        int $id,
        EntityManagerInterface $em
    ): Response {
        $specialty = $medicalSpecialtyRepository->find($id);
        if (!$specialty) {
            throw $this->createNotFoundException('Specialitatea nu a fost găsită.');
        }
        $form = $this->createForm(AdminSpecialtyFormType::class, $specialty);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Specialitatea a fost actualizat cu succes.');
            return $this->redirectToRoute('admin_specialties');
        }

        return $this->render('pages/admin/specialties/edit.html.twig', [
            'specialtyForm' => $form->createView(),
            'specialty'     => $specialty,
        ]);
    }

    #[Route('/admin/specialties/{id}/delete', name: 'admin_delete_specialty', methods: ['POST'])]
    public function deleteSpecialty(
        Request $request,
        MedicalSpecialty $specialty,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$specialty->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($specialty);
                $em->flush();
                $this->addFlash('success', 'Specialitatea a fost ștearsă cu succes.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Specialitatea nu poate fi ștearsă deoarece are servicii sau doctori asociați.');
            }
        }

        return $this->redirectToRoute('admin_specialties');
    }

    #[Route('/admin/settings', name: 'admin_settings')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        HospitalSettingsRepository $settingsRepo
    ): Response {
        $settingsEntity = $settingsRepo->findOneBy([]);
        if (!$settingsEntity) {
            $settingsEntity = new HospitalSettings();
            $em->persist($settingsEntity);
            $em->flush();
        }

        $form = $this->createForm(HospitalSettingsFormType::class, $settingsEntity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Setările au fost actualizate cu succes.');
            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('pages/admin/settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/admin/appointments', name: 'admin_appointments')]
    public function appointmentsList(AppointmentRepository $appointmentRepository): Response
    {
        return $this->render('pages/admin/appointments/index.html.twig', [
            'appointments' => $appointmentRepository->findAll()
        ]);
    }
    
    #[Route('/admin/appointments/new', name: 'admin_add_appointment')]
    public function addAppointment(
        Request $request,
        EntityManagerInterface $em,
        PatientRepository $patientRepo,
        DoctorRepository $doctorRepo,
        DoctorScheduleRepository $doctorScheduleRepository,
        PatientRegisterStory $patientRegisterStory
    ): Response {
        $form = $this->createForm(AdminAppointmentFormType::class);
        $form->handleRequest($request);

        $selectedDateTime = $request->query->get('date');
        if ($selectedDateTime) {
            try {
                $defaultAppointmentStart = new \DateTime($selectedDateTime);
                $form->get('appointmentStart')->setData($defaultAppointmentStart);
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $doctor = $data['doctor'];
            if (!$doctor) {
                $this->addFlash('error', 'Selectați un medic.');
                return $this->redirectToRoute('admin_add_appointment');
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
                return $this->redirectToRoute('admin_appointments');
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
                return $this->redirectToRoute('admin_appointments');
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
            $smsUrl = "https://app.2waysms.io/smsapi/index?key=4652CE6812E7E7&campaign=282&routeid=3&type=text&contacts=40724520457&senderid=3861&msg={$encodedMessage}";

            try {
                $smsResponse = file_get_contents($smsUrl);
            } catch (\Exception $smsException) {
                // Log error but don't interrupt the flow
            }
            return $this->redirectToRoute('admin_appointments');
        }

        // Get today's date
        $today = new \DateTime();
        $startOfWeek = (clone $today)->modify('monday this week');
        $endOfWeek   = (clone $today)->modify('sunday this week');
        
        // Default to empty doctorSchedules and appointments
        $doctorSchedules = [];
        $appointments = [];

        return $this->render('pages/admin/appointments/new.html.twig', [
            'form' => $form->createView(),
            'appointments' => json_encode($appointments),
            'doctorSchedules' => json_encode($doctorSchedules),
            'doctors' => $doctorRepo->findAll(), // Pass all doctors to the template
        ]);
    }
    
    #[Route('/admin/appointments/{id}/edit', name: 'admin_edit_appointment')]
    public function editAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepo,
        EntityManagerInterface $em,
        DoctorRepository $doctorRepo,
        DoctorScheduleRepository $doctorScheduleRepository,
        PatientRepository $patientRepo
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
            'doctor'           => $appointment->getDoctor(),
            'specialty'        => $appointment->getMedicalSpecialty(),
            'service'          => $appointment->getHospitalService(),
            'appointmentStart' => $oldAppointmentStart,
            'firstName'        => $appointment->getPatient()->getFirstName(),
            'lastName'         => $appointment->getPatient()->getLastName(),
            'email'            => $appointment->getPatient()->getEmail(),
            'phone'            => $appointment->getPatient()->getPhone(),
            'cnp'              => $appointment->getPatient()->getCnp(),
        ];

        $form = $this->createForm(AdminAppointmentFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $doctor = $data['doctor'];
            if (!$doctor) {
                $this->addFlash('error', 'Selectați un medic.');
                return $this->redirectToRoute('admin_edit_appointment', ['id' => $id]);
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
            $appointment->setDoctor($doctor);

            $newAppointmentStart = $data['appointmentStart'];
            $oldStartStr = $oldAppointmentStart->format('Y-m-d H:i');
            $newStartStr = $newAppointmentStart->format('Y-m-d H:i');

            $timeChanged = $oldStartStr !== $newStartStr;
            $serviceChanged = $appointment->getHospitalService()->getId() !== $newService->getId();
            $doctorChanged = $appointment->getDoctor()->getId() !== $doctor->getId();

            if ($timeChanged || $serviceChanged || $doctorChanged) {
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
                    return $this->render('pages/admin/appointments/edit.html.twig', [
                        'form' => $form->createView(),
                        'appointment' => $appointment,
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
                    return $this->render('pages/admin/appointments/edit.html.twig', [
                        'form' => $form->createView(),
                        'appointment' => $appointment,
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
            return $this->redirectToRoute('admin_appointments');
        }

        $doctor = $appointment->getDoctor();
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
        /** @var Appointment $appointmentEntity */
        foreach ($appointmentsEntities as $appointmentEntity) {
            $timeSlot = $appointmentEntity->getTimeSlot();
            $schedule = $timeSlot->getSchedule();
            $appointments[] = [
                'id'    => $appointmentEntity->getId(),
                'title' => $appointmentEntity->getPatient()->getFirstName() . ' ' .
                    $appointmentEntity->getPatient()->getLastName() . ' - ' .
                    $appointmentEntity->getMedicalSpecialty()->getName(),
                'start' => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getStartTime()->format('H:i:s'),
                'end'   => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getEndTime()->format('H:i:s'),
            ];
        }

        return $this->render('pages/admin/appointments/edit.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'appointments' => json_encode($appointments),
            'doctorSchedules' => json_encode($doctorSchedules),
            'doctors' => $doctorRepo->findAll(), // Pass all doctors to the template
        ]);
    }
    
    #[Route('/admin/appointments/{id}/delete', name: 'admin_delete_appointment', methods: ['POST'])]
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

        return $this->redirectToRoute('admin_appointments');
    }
    
    #[Route('/admin/api/doctor-specialties/{id}', name: 'admin_api_doctor_specialties', methods: ['GET'])]
    public function getDoctorSpecialties(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $doctor = $em->getRepository(Doctor::class)->find($id);
        if (!$doctor) {
            return new JsonResponse(['error' => 'Doctor not found'], 404);
        }
        
        // Get doctor's specialties
        $specialties = [];
        foreach ($doctor->getMedicalSpecialties() as $specialty) {
            $specialties[] = [
                'id' => $specialty->getId(),
                'name' => $specialty->getName()
            ];
        }
        
        return new JsonResponse(['specialties' => $specialties]);
    }
    
    #[Route('/admin/api/doctor-services/{id}', name: 'admin_api_doctor_services', methods: ['GET'])]
    public function getDoctorServices(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $doctor = $em->getRepository(Doctor::class)->find($id);
        if (!$doctor) {
            return new JsonResponse(['error' => 'Doctor not found'], 404);
        }
        
        // Get doctor's services with their specialties
        $services = [];
        foreach ($doctor->getHospitalServices() as $service) {
            // Make sure the service has a specialty
            if ($service->getMedicalSpecialty()) {
                $services[] = [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'specialtyId' => $service->getMedicalSpecialty()->getId(),
                    'specialtyName' => $service->getMedicalSpecialty()->getName(),
                    'duration' => $service->getDuration(),
                    'price' => $service->getPrice()
                ];
            }
        }
        
        return new JsonResponse(['services' => $services]);
    }
    
    #[Route('/admin/api/doctor-schedule/{id}', name: 'admin_api_doctor_schedule', methods: ['GET'])]
    public function getDoctorSchedule(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $doctor = $em->getRepository(Doctor::class)->find($id);
        if (!$doctor) {
            return new JsonResponse(['error' => 'Doctor not found'], 404);
        }
        
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

        $availableSlots = [];
        $blockedSlots = [];
        
        /** @var DoctorSchedule $schedule */
        foreach ($schedules as $schedule) {
            $scheduleDate = $schedule->getDate()->format('Y-m-d');
            
            foreach ($schedule->getTimeSlots() as $slot) {
                $slotData = [
                    'date' => $scheduleDate,
                    'startTime' => $slot->getStartTime()->format('H:i:s'),
                    'endTime' => $slot->getEndTime()->format('H:i:s'),
                ];
                
                if ($slot->isBooked()) {
                    $blockedSlots[] = $slotData;
                } else {
                    $availableSlots[] = $slotData;
                }
            }
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
        /** @var Appointment $appointmentEntity */
        foreach ($appointmentsEntities as $appointmentEntity) {
            $timeSlot = $appointmentEntity->getTimeSlot();
            $schedule = $timeSlot->getSchedule();
            $appointments[] = [
                'id'    => $appointmentEntity->getId(),
                'title' => $appointmentEntity->getPatient()->getFirstName() . ' ' .
                    $appointmentEntity->getPatient()->getLastName() . ' - ' .
                    $appointmentEntity->getMedicalSpecialty()->getName(),
                'start' => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getStartTime()->format('H:i:s'),
                'end'   => $schedule->getDate()->format('Y-m-d') . 'T' .
                    $timeSlot->getEndTime()->format('H:i:s'),
            ];
        }
        
        return new JsonResponse([
            'availableSlots' => $availableSlots,
            'blockedSlots' => $blockedSlots,
            'appointments' => $appointments
        ]);
    }
    
    /**
     * Generate a unique placeholder email based on patient names
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
}