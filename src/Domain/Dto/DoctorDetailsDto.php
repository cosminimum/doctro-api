<?php

namespace App\Domain\Dto;

class DoctorDetailsDto extends DoctorDto
{
    private const KEY_SPECIALTIES = 'specialties';
    private const KEY_SPECIALTY_ID = 'specialty_id';
    private const KEY_SPECIALTY_CODE = 'specialty_code';
    private const KEY_SPECIALTY_NAME = 'specialty_name';

    private const KEY_HOSPITAL_SERVICES = 'hospital_services';
    private const KEY_HOSPITAL_SERVICE_ID = 'hospital_service_id';
    private const KEY_HOSPITAL_SERVICE_NAME = 'hospital_service_name';
    private const KEY_HOSPITAL_ID = 'hospital_id';
    private const KEY_HOSPITAL_NAME = 'hospital_name';
    private const KEY_MEDICAL_SERVICE_ID = 'medical_service_id';
    private const KEY_MEDICAL_SERVICE_NAME = 'medical_service_name';
    private const KEY_MEDICAL_SERVICE_CODE = 'medical_service_code';

    private array $specialties = [];
    private array $hospitalServices = [];

    public function addSpecialty(
        int $specialtyId,
        string $specialtyCode,
        string $specialtyName
    ): self {
        $this->specialties[] = [
            self::KEY_SPECIALTY_ID => $specialtyId,
            self::KEY_SPECIALTY_CODE => $specialtyCode,
            self::KEY_SPECIALTY_NAME => $specialtyName
        ];

        return $this;
    }

    public function addHospitalService(
        int $hospitalServiceId,
        string $hospitalServiceName,
        int $hospitalId,
        string $hospitalName,
        int $medicalServiceId,
        string $medicalServiceName,
        string $medicalServiceCode
    ) : self {
        $this->hospitalServices[] = [
            self::KEY_HOSPITAL_SERVICE_ID => $hospitalServiceId,
            self::KEY_HOSPITAL_SERVICE_NAME => $hospitalServiceName,
            self::KEY_HOSPITAL_ID => $hospitalId,
            self::KEY_HOSPITAL_NAME => $hospitalName,
            self::KEY_MEDICAL_SERVICE_ID => $medicalServiceId,
            self::KEY_MEDICAL_SERVICE_NAME => $medicalServiceName,
            self::KEY_MEDICAL_SERVICE_CODE => $medicalServiceCode
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [
            self::KEY_ID => $this->id,
            self::KEY_EMAIL => $this->email,
            self::KEY_FIRST_NAME => $this->firstName,
            self::KEY_LAST_NAME => $this->lastName,
            self::KEY_CNP => $this->cnp,
            self::KEY_PHONE => $this->phone,
            self::KEY_SPECIALTIES => $this->specialties,
            self::KEY_HOSPITAL_SERVICES => $this->hospitalServices
        ];
    }
}
