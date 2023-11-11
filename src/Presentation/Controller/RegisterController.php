<?php

namespace App\Presentation\Controller;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly DoctorRepositoryInterface  $doctorRepository
    ) {
    }

    #[Route('/register/patient', name: 'register', methods: ['POST'])]
    public function registerPatient(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $userId = $this->patientRepository->addPatient($requestDto);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }

    #[Route('/register/doctor', name: 'register_doctor', methods: ['POST'])]
    public function registerDoctor(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $userId = $this->doctorRepository->addDoctor($requestDto);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }
}
