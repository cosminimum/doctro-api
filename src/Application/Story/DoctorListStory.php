<?php

namespace App\Application\Story;

use App\Application\Factory\DoctorSourceFactory;
use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\DoctorListResponseDto;

class DoctorListStory
{
    public function __construct(
        private readonly DoctorSourceFactory $doctorSourceFactory
    ) {
    }

    public function list(?DoctorListRequestDto $requestDto): DoctorListResponseDto
    {
        $doctorSource = $this->doctorSourceFactory->create();
        $rows = $doctorSource->getDoctorsByFilters($requestDto);

        // todo: filter by available dates? -- external call -- how?

        return new DoctorListResponseDto($rows);
    }
}