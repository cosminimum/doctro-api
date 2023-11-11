<?php

namespace App\Presentation\Controller;

use App\Application\Story\DoctorRegisterStory;
use App\Application\Story\PatientRegisterStory;
use App\Domain\Dto\UserCreateRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly PatientRegisterStory $patientRegisterStory,
        private readonly DoctorRegisterStory $doctorRegisterStory
    ) {
    }

    #[Route('/register/patient', name: 'register', methods: ['POST'])]
    public function registerPatient(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $userId = $this->patientRegisterStory->register($requestDto);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }

    #[Route('/register/doctor', name: 'register_doctor', methods: ['POST'])]
    public function registerDoctor(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $userId = $this->doctorRegisterStory->register($requestDto);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }
}
