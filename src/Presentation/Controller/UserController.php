<?php

namespace App\Presentation\Controller;

use App\Application\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route(
        '/api/user', name: 'api_user_create', methods: ['POST']
    )]
    public function create(array $userData): JsonResponse
    {
        $userId = $this->userRepository->addUser($userData);

        return $this->json(['user_id' => $userId], Response::HTTP_OK);
    }

    #[Route(
        '/api/user/{userId}', name: 'api_user_read', methods: ['GET']
    )]
    public function read(int $userId): JsonResponse
    {
        // todo: get user

        return $this->json([], Response::HTTP_OK);
    }

    #[Route(
        '/api/user/{userId}', name: 'api_user_update', methods: ['PUT']
    )]
    public function update(int $userId, array $userData): JsonResponse
    {
        // todo update user

        return $this->json([], Response::HTTP_OK);
    }

    #[Route(
        '/api/user/{userId}', name: 'api_user_delete', methods: ['DELETE']
    )]
    public function delete(int $userId): JsonResponse
    {
        // todo: delete user

        return $this->json([], Response::HTTP_OK);
    }
}
