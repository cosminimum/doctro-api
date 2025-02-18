<?php

namespace App\Presentation\Webservice;

use App\Domain\Dto\ApiResponseDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function home(): JsonResponse
    {
        return $this->json(
            (new ApiResponseDto())
                ->setData([
                    'who_am_i' => 'Doctro API',
                    'who_you_are' => $this->container->get('request_stack')->getCurrentRequest()->getClientIp()
                ])
        );
    }
}
