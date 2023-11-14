<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\DoctorListRequestDto;

class DoctorListStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository
    ) {
    }

    public function list(DoctorListRequestDto $requestDto): array
    {
        $doctorList = $this->doctorRepository->getDoctorByFilters($requestDto);

        // todo: filter by available dates? -- external call

        return $doctorList;
    }
}
