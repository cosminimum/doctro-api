<?php

namespace App\Presentation\Controller;

use App\Application\Story\DoctorDetailsStory;
use App\Domain\Dto\ApiResponseDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DoctorDetailsController extends AbstractController
{
    public function __construct(
        private readonly DoctorDetailsStory $doctorDetailsStory
    ) {
    }

    #[Route('/api/doctor/{doctorId}', name: 'api_doctor_details', methods: ['GET'])]
    public function details(int $doctorId): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $results = $this->doctorDetailsStory->details($doctorId);

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
