<?php

namespace App\Domain\Dto;

class AppointmentDto
{
    private const KEY_APPOINTMENT_ID = 'appointment_id';
    private const KEY_PATIENT_ID = 'patient_id';
    private const KEY_PATIENT_NAME = 'patient_name';
    private const KEY_DOCTOR_ID = 'doctor_id';
    private const KEY_DOCTOR_NAME = 'doctor_name';
    private const KEY_SPECIALTY_ID = 'specialty_id';
    private const KEY_SPECIALTY_NAME = 'specialty_name';
    private const KEY_HOSPITAL_SERVICE_ID = 'hospital_service_id';
    private const KEY_HOSPITAL_SERVICE_NAME = 'hospital_service_name';
    private const KEY_HOSPITAL_ID = 'hospital_id';
    private const KEY_HOSPITAL_NAME = 'hospital_name';
    private const KEY_APPOINTMENT_DATE = 'appointment_date';

    private int $appointmentId;
    private int $patientId;
    private string $patientName;
    private int $doctorId;
    private string $doctorName;
    private int $specialtyId;
    private string $specialtyName;
    private int $hospitalServiceId;
    private string $hospitalServiceName;
    private int $hospitalId;
    private string $hospitalName;
    private \DateTime $appointmentDate;

    public function setAppointmentId(int $appointmentId): self
    {
        $this->appointmentId = $appointmentId;
        return $this;
    }

    public function setPatientId(int $patientId): self
    {
        $this->patientId = $patientId;
        return $this;
    }

    public function setPatientName(string $patientName): self
    {
        $this->patientName = $patientName;
        return $this;
    }

    public function setDoctorId(int $doctorId): self
    {
        $this->doctorId = $doctorId;
        return $this;
    }

    public function setDoctorName(string $doctorName): self
    {
        $this->doctorName = $doctorName;
        return $this;
    }

    public function setSpecialtyId(int $specialtyId): self
    {
        $this->specialtyId = $specialtyId;
        return $this;
    }

    public function setSpecialtyName(string $specialtyName): self
    {
        $this->specialtyName = $specialtyName;
        return $this;
    }

    public function setHospitalServiceId(int $hospitalServiceId): self
    {
        $this->hospitalServiceId = $hospitalServiceId;
        return $this;
    }

    public function setHospitalServiceName(string $hospitalServiceName): self
    {
        $this->hospitalServiceName = $hospitalServiceName;
        return $this;
    }

    public function setHospitalId(int $hospitalId): self
    {
        $this->hospitalId = $hospitalId;
        return $this;
    }

    public function setHospitalName(string $hospitalName): self
    {
        $this->hospitalName = $hospitalName;
        return $this;
    }

    public function setAppointmentDate(\DateTime $appointmentDate): self
    {
        $this->appointmentDate = $appointmentDate;
        return $this;
    }

    public function toArray(): array
    {
        return [
            self::KEY_APPOINTMENT_ID => $this->appointmentId,
            self::KEY_PATIENT_ID => $this->patientId,
            self::KEY_PATIENT_NAME => $this->patientName,
            self::KEY_DOCTOR_ID => $this->doctorId,
            self::KEY_DOCTOR_NAME => $this->doctorName,
            self::KEY_SPECIALTY_ID => $this->specialtyId,
            self::KEY_SPECIALTY_NAME => $this->specialtyName,
            self::KEY_HOSPITAL_SERVICE_ID => $this->hospitalServiceId,
            self::KEY_HOSPITAL_SERVICE_NAME => $this->hospitalServiceName,
            self::KEY_HOSPITAL_ID => $this->hospitalId,
            self::KEY_HOSPITAL_NAME => $this->hospitalName,
            self::KEY_APPOINTMENT_DATE => $this->appointmentDate
        ];
    }
}
