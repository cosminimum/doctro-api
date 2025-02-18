<?php

namespace App\Presentation\Controller;

use App\Application\Story\DoctorListStory;
use App\Domain\Dto\ApiResponseDto;
use App\Domain\Dto\DoctorListRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Annotation\Route;

class DoctorListController extends AbstractController
{
    public function __construct(
        private readonly DoctorListStory $doctorListStory
    ) {
    }

    #[Route('/api/doctors', name: 'api_doctor_list', methods: ['GET'])]
    public function list(#[MapQueryString] ?DoctorListRequestDto $requestDto): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $doctorList = $this->doctorListStory->list($requestDto);

            $response->setData(
                $doctorList->toArray()
            );
        } catch (\Throwable $throwable) {
            $response->setIsError(true)
                ->setCode(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->setErrors(['general_error', $throwable->getMessage(), $throwable->getTrace()]);

            // todo: log error
        }

        return $this->json(
            $response->toArray(),
            $response->getCode()
        );
    }
}
