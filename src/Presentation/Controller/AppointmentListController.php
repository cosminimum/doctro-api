<?php

namespace App\Presentation\Controller;

use App\Application\Story\AppointmentListStory;
use App\Domain\Dto\ApiResponseDto;
use App\Domain\Dto\AppointmentListRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class AppointmentListController extends AbstractController
{
    public function __construct(
        private readonly AppointmentListStory $appointmentListStory
    ) {
    }

    #[Route('/api/appointments', name: 'api_appointment_list', methods: ['GET'])]
    public function list(#[MapRequestPayload] AppointmentListRequestDto $requestDto): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $results = $this->appointmentListStory->list($requestDto);

            $response->setData($results);
        } catch (\Throwable $throwable) {
            $response->setCode(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->setErrors(['general_error']);

            // todo: log error
        }

        return $this->json(
            $response->toArray(),
            $response->getCode()
        );
    }
}
