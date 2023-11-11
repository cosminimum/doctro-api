<?php

namespace App\Presentation\Controller;

use App\Application\Story\DoctorListStory;
use App\Domain\Dto\ApiResponseDto;
use App\Domain\Dto\DoctorListRequestDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

class DoctorListController extends AbstractController
{
    public function __construct(
        private readonly DoctorListStory $doctorListStory
    ) {
    }

    public function list(#[MapRequestPayload] DoctorListRequestDto $requestDto): JsonResponse
    {
        $response = new ApiResponseDto();

        try {
            $results = $this->doctorListStory->list($requestDto);

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
