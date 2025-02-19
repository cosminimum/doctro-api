<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\AppointmentAddStory;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Repository\AppointmentRepository;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
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
        private readonly MedicalSpecialtyRepository $medicalSpecialtyRepository,
        private readonly HospitalServiceRepository $hospitalServiceRepository
    ) {}

    #[Route('/appointment', name: 'app_appointment_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(AppointmentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $appointment = $this->appointmentStory->add(
                    new AppointmentAddRequestDto(
                        $data['doctorId'],
                        $data['specialtyId'],
                        $data['serviceId'],
                        $data['slotId']
                    ),
                    $this->getUser()
                );

                $this->addFlash('success', 'Appointment scheduled successfully!');

                return $this->redirectToRoute('homepage');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }
        return $this->render('pages/appointments/index.html.twig', [
            'form' => $form->createView(),
            'specialties' => $this->medicalSpecialtyRepository->findAll(),
            'isEdit' => false,
        ]);
    }

    #[Route('/appointment/filter', name: 'app_appointment_filter', methods: ['GET'])]
    public function filterAppointments(Request $request, AppointmentRepository $appointmentRepository): Response
    {
        $filterType = $request->query->get('filterType', 'upcoming');
        $status     = $request->query->get('status', 'all');
        $specialty  = $request->query->get('specialty', '');
        $service    = $request->query->get('service', '');
        $query      = $request->query->get('query', '');
        $responseType = $request->query->get('responseType', 'twig');

        $qb = $appointmentRepository->createQueryBuilder('a')
            ->join('a.timeSlot', 'ts')
            ->join('ts.schedule', 's')
            ->join('a.doctor', 'd')
            ->join('a.medicalSpecialty', 'ms')
            ->join('a.hospitalService', 'hs')
            ->andWhere('a.patient = :patient')
            ->setParameter('patient', $this->getUser())
            ->orderBy('s.date', 'ASC');

        if ($filterType === 'upcoming') {
            $qb->andWhere('s.date >= :today')
                ->setParameter('today', new \DateTime());
        } elseif ($filterType === 'past') {
            $qb->andWhere('s.date < :today')
                ->setParameter('today', new \DateTime());
        }

        if ($status === 'active') {
            $qb->andWhere('a.isActive = :active')
                ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('a.isActive = :active')
                ->setParameter('active', false);
        }

        if (!empty($specialty)) {
            $qb->andWhere('ms.id = :specialty')
                ->setParameter('specialty', $specialty);
        }

        if (!empty($service)) {
            $qb->andWhere('hs.id = :service')
                ->setParameter('service', $service);
        }

        if (!empty($query)) {
            $qb->andWhere('d.firstName LIKE :search OR d.lastName LIKE :search')
                ->setParameter('search', '%' . $query . '%');
        }

        $appointments = $qb->getQuery()->getResult();

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
                $this->addFlash('error', $e->getMessage());
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
        $slots = $this->appointmentStory->findAvailableSlots([
            'specialty' => $request->query->getInt('specialty'),
            'service' => $request->query->getInt('service'),
            'doctor' => $request->query->get('doctor'),
            'startDate' => new \DateTime(),
            'endDate' => (new \DateTime())->modify('+3 months'),
        ]);

        return $this->render('pages/appointments/components/slots.html.twig', [
            'slots' => $slots,
            'specialty' => $this->medicalSpecialtyRepository->find($request->query->getInt('specialty')),
            'service' => $this->hospitalServiceRepository->find($request->query->getInt('specialty')),
            'user' => $this->getUser(),
        ]);
    }
}
