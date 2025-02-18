<?php

namespace App\Presentation\Webservice\Crud;

use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PatientController extends AbstractController
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker
    ) {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route(
        '/api/crud/patient', name: 'api_crud_patient_create', methods: ['POST']
    )]
    public function create(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $user = $this->patientRepository->addPatient($requestDto);

        return $this->json(['user' => $user], Response::HTTP_OK);
    }

    #[Route(
        '/api/crud/patient/{userId}', name: 'api_crud_patient_read', methods: ['GET']
    )]
    public function read(int $userId): JsonResponse
    {
        // todo: get patient

        return $this->json([], Response::HTTP_OK);
    }

    #[Route(
        '/api/crud/patient/{userId}', name: 'api_crud_patient_update', methods: ['PUT']
    )]
    public function update(int $userId, array $userData): JsonResponse
    {
        // todo update patient

        return $this->json([], Response::HTTP_OK);
    }

    #[Route(
        '/api/crud/patient/{userId}', name: 'api_crud_patient_delete', methods: ['DELETE']
    )]
    public function delete(int $userId): JsonResponse
    {
        // todo: delete patient

        return $this->json([], Response::HTTP_OK);
    }
}
