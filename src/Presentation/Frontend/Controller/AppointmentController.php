<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\AppointmentAddStory;
use App\Application\Story\AppointmentListStory;
use App\Application\Story\PatientRegisterStory;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\DoctorRepository;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use App\Infrastructure\Repository\TimeSlotRepository;
use App\Presentation\Frontend\Form\AppointmentFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppointmentController extends AbstractController
{
    public function __construct(
        private readonly AppointmentAddStory $appointmentStory,
        private readonly AppointmentListStory $appointmentListStory,
        private readonly MedicalSpecialtyRepository $medicalSpecialtyRepository,
        private readonly HospitalServiceRepository $hospitalServiceRepository
    ) {}

    #[Route('/appointment', name: 'app_appointment_new')]
    public function addPatientAppointment(
        Request $request,
        EntityManagerInterface $em,
        DoctorRepository $doctorRepository,
        DoctorScheduleRepository $doctorScheduleRepository,
        PatientRegisterStory $patientRegisterStory,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        TimeSlotRepository $timeSlotRepository,
        HospitalServiceRepository $hospitalServiceRepository,
        AppointmentRepository $appointmentRepository,
    ): Response
    {
        $form = $this->createForm(AppointmentFormType::class);
        $form->handleRequest($request);

        $selectedDateTime = $request->query->get('date');
        if ($selectedDateTime) {
            try {
                $defaultAppointmentStart = new \DateTime($selectedDateTime);
                $form->get('appointmentStart')->setData($defaultAppointmentStart);
            } catch (\Exception $e) {
                // opțional: logare eroare
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $patient = $this->getUser();
            if (!$patient) {
                try {
                    $patient = $patientRegisterStory->register(new UserCreateRequestDto(
                        $data['email'],
                        $data['firstName'],
                        $data['lastName'],
                        $data['cnp'],
                        $data['phone'],
                        base64_encode(random_bytes(10))
                    ));
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Nu s-a putut crea contul');
                    return $this->render('pages/appointments/index.html.twig', [
                        'form' => $form->createView(),
                        'specialties' => $medicalSpecialtyRepository->findAll(),
                        'isEdit' => false
                    ]);
                }
            }

            try {
                $appointmentAddRequest = new AppointmentAddRequestDto(
                    $data['doctorId'],
                    $data['specialtyId'],
                    $data['serviceId'],
                    $data['slotId']
                );

                $appointmentId = $this->appointmentStory->add($appointmentAddRequest, $patient);

                $this->addFlash('success', 'Programare creată cu succes!');

                $appointment = $appointmentRepository->find($appointmentId);
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
                $smsUrl = $this->getParameter('sms_url') . $encodedMessage;

                try {
                    $smsResponse = file_get_contents($smsUrl);
                } catch (\Exception $smsException) {
                    // Log error but don't interrupt the flow
                }


                return $this->redirectToRoute('homepage');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Nu s-a putut crea programarea: ' . $e->getMessage());
                return $this->render('pages/appointments/index.html.twig', [
                    'form' => $form->createView(),
                    'specialties' => $medicalSpecialtyRepository->findAll(),
                    'isEdit' => false
                ]);
            }
        }

        return $this->render('pages/appointments/index.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
            'specialties' => $medicalSpecialtyRepository->findAll()
        ]);
    }


    #[Route('/appointment/filter', name: 'app_appointment_filter', methods: ['GET'])]
    public function filterAppointments(Request $request): Response
    {
        $filterType = $request->query->get('filterType', 'upcoming');
        $status     = $request->query->get('status', 'all');
        $specialty  = $request->query->get('specialty', '');
        $service    = $request->query->get('service', '');
        $query      = $request->query->get('query', '');
        $responseType = $request->query->get('responseType', 'twig');

        $requestDto = new AppointmentListRequestDto(
            null, // No hospital ID filtering for now
            !empty($specialty) ? (int)$specialty : null,
            $query ?: null,
            null, // No date filtering
            $status !== 'all' ? $status : null
        );

        // Get appointments using the story
        $responseDto = $this->appointmentListStory->list($requestDto);
        $appointments = $responseDto->toArray();

        if ($responseType === 'twig') {
            return $this->render('pages/appointments/components/_appointments_list.html.twig', [
                'appointments' => $appointments,
            ]);
        }

        return $this->json($appointments);
    }

    #[Route('/appointment/{id}', name: 'app_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment): Response
    {
        $formData = [
            'doctorId'    => $appointment->getDoctor()->getId(),
            'specialtyId' => $appointment->getMedicalSpecialty()->getId(),
            'serviceId'   => $appointment->getHospitalService()->getId(),
            'slotId'      => $appointment->getTimeSlot()->getId(),
            'firstName' => $appointment->getPatient()->getFirstName(),
            'lastName'  => $appointment->getPatient()->getLastName(),
            'email'     => $appointment->getPatient()->getEmail(),
            'phone'     => $appointment->getPatient()->getPhone(),
            'cnp' => $appointment->getPatient()->getCnp(),
        ];

        $form = $this->createForm(AppointmentFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->appointmentStory->edit($appointment);

                $this->addFlash('success', 'Appointment updated successfully!');

                return $this->redirectToRoute('homepage', [
                    'id' => $appointment->getId()
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ne pare rău. Vă rugăm să reîncercați');
            }
        }

        return $this->render('pages/appointments/index.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'specialties' => $this->medicalSpecialtyRepository->findAll(),
            'services' => $this->hospitalServiceRepository->findAll(),
            'isEdit' => true
        ]);
    }

    #[Route('/appointment/{id}/pdf', name: 'app_appointment_pdf', methods: ['GET'])]
    public function pdf(Appointment $appointment): Response
    {
        $html = $this->renderView('pdf/appointment_details.html.twig', [
            'appointment' => $appointment,
        ]);

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();
        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="appointment_' . $appointment->getId() . '.pdf"',
        ]);
    }

    #[Route('/appointments/{id}/delete', name: 'app_delete_appointment')]
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

    # @TODO We need to move this from this controller
    #[Route('/api/services', name: 'api_services', methods: ['GET'])]
    public function getServices(Request $request): JsonResponse
    {
        $specialtyId = $request->query->getInt('specialty');
        $services = $this->appointmentStory->getServicesBySpecialty($specialtyId);

        $normalizedServices = array_map(function(HospitalService $service) {
            return [
                'id' => $service->getId(),
                'name' => $service->getName(),
            ];
        }, $services);

        return $this->json($normalizedServices);
    }

    #[Route('/api/doctors', name: 'api_doctors', methods: ['GET'])]
    public function getDoctors(Request $request): JsonResponse
    {
        $serviceId = $request->query->getInt('service');
        $doctors = $this->appointmentStory->getDoctorsByService($serviceId);

        $normalizedDoctors = array_map(function(Doctor $doctor) {
            return [
                'id' => $doctor->getId(),
                'name' => $doctor->getFirstName() . ' ' . $doctor->getLastName(),
            ];
        }, $doctors);

        return $this->json($normalizedDoctors);
    }

    #[Route('/api/slots', name: 'api_slots', methods: ['GET'])]
    public function getSlots(Request $request): Response
    {
        $specialty = $request->query->getInt('specialty');
        $service = $request->query->getInt('service');
        $doctor = $request->query->get('doctor');

        $dateFrom = $request->query->get('dateFrom');
        $startDate = $dateFrom ? new \DateTime($dateFrom) : new \DateTime();

        $dateTo = $request->query->get('dateTo');
        $endDate = $dateTo ? new \DateTime($dateTo) : (new \DateTime())->modify('+3 months');

        $slots = $this->appointmentStory->findAvailableSlots([
            'specialty' => $specialty,
            'service' => $service,
            'doctor' => $doctor,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return $this->render('pages/appointments/components/slots.html.twig', [
            'slots' => $slots,
            'specialty' => $this->medicalSpecialtyRepository->find($specialty),
            'service' => $this->hospitalServiceRepository->find($service),
            'user' => $this->getUser(),
        ]);
    }
}