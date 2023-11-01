<?php

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController
{
    #[Route(
        '/test', name: 'api_test_route', methods: ['GET']
    )]
    public function testRoute(): JsonResponse
    {
        return new JsonResponse([2, 10, 2, 10, random_int(1, 1000000)]);
    }
}
