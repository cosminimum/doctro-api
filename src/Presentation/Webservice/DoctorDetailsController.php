<?php

namespace App\Presentation\Webservice;

use App\Application\Story\DoctorDetailsStory;
use App\Domain\Dto\ApiResponseDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            $doctorDetails = $this->doctorDetailsStory->details($doctorId);

            if ($doctorDetails === null) {
                throw new NotFoundHttpException();
            }

            $response->setData($doctorDetails?->toArray() ?? []);
        } catch (NotFoundHttpException $exception) {
            $response->setCode(Response::HTTP_NOT_FOUND)
                ->setErrors(['doctor_not_found']);
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
