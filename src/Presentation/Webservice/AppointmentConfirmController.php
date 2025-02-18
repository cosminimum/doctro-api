<?php

namespace App\Presentation\Webservice;

use App\Application\Story\AppointmentAddStory;
use App\Domain\Dto\ApiResponseDto;
use App\Domain\Dto\AppointmentAddRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class AppointmentConfirmController extends AbstractController
{
    public function __construct(
        private readonly AppointmentAddStory $appointmentAddStory
    ) {
    }

    #[Route('/api/appointment', name: 'api_appointment_add', methods: ['POST'])]
    public function confirm(#[MapRequestPayload] AppointmentAddRequestDto $requestDto): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $appointmentId = $this->appointmentAddStory->add(
                $requestDto,
                $this->getUser()
            );

            $response->setData(['appointment_id' => $appointmentId]);
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
