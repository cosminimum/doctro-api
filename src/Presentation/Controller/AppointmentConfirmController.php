<?php

namespace App\Presentation\Controller;

use App\Application\Story\AppointmentConfirmStory;
use App\Domain\Dto\ApiResponseDto;
use App\Domain\Dto\AppointmentConfirmRequestDto;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class AppointmentConfirmController
{
    public function __construct(
        private readonly AppointmentConfirmStory $appointmentConfirmStory
    ) {
    }

    #[Route('/api/appointment', name: 'api_appointment_confirm', methods: ['POST'])]
    public function confirm(#[MapRequestPayload] AppointmentConfirmRequestDto $requestDto): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $results = $this->appointmentConfirmStory->confirm($requestDto);

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
