<?php

namespace App\Presentation\Controller;

use App\Infrastructure\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TestController
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route(
        '/api/test', name: 'api_test_route', methods: ['GET']
    )]
    public function testRoute(): JsonResponse
    {
        return new JsonResponse([2, 10, 2, 10, random_int(1, 1000000)]);
    }

    #[Route(
        '/api/add-test-user', name: 'api_add_test_user', methods: ['GET']
    )]
    public function addTestUser(): JsonResponse
    {
        $newUserId = $this->userRepository->addUser([
            'email' => 'test@doctro.tld',
            'roles' => ['ROLE_TEST'],
            'password' => '123456'
        ]);

        return new JsonResponse(['newUserId' => $newUserId]);
    }
}
