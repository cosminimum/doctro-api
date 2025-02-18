<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Controller;

use App\Application\Story\AppointmentAddStory;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use App\Presentation\Frontend\Form\AppointmentFormType;
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

    #[Route('/appointment/{id}', name: 'app_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $appointment);

        $form = $this->createForm(AppointmentFormType::class, $appointment);
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
            'isEdit' => true
        ]);
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
