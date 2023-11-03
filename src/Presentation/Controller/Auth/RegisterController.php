<?php

namespace App\Presentation\Controller\Auth;

use App\Application\Repository\UserRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(#[MapRequestPayload] UserCreateRequestDto $requestDto): JsonResponse
    {
        $userId = $this->userRepository->addUser($requestDto);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }
}
