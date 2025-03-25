<?php

namespace App\Application\Story;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\DoctorListResponseDto;

class DoctorListStory
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository
    ) {
    }

    public function list(?DoctorListRequestDto $requestDto): DoctorListResponseDto
    {
        $rows = $this->doctorRepository->getDoctorListByFilters($requestDto);

        // todo: filter by available dates? -- external call -- how?

        return new DoctorListResponseDto($rows);
    }
}