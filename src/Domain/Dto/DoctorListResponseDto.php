<?php

namespace App\Domain\Dto;

class DoctorListResponseDto
{
    /** @param DoctorDto[] $rows */
    public function __construct(
        private readonly array $rows
    ) {
    }

    public function toArray(): array
    {
        return array_map(static function ($row) {
            return $row->toArray();
        }, $this->rows);
    }
}
