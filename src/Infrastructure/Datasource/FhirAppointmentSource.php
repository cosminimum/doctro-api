<?php

namespace App\Infrastructure\Datasource;

use App\Application\DataSource\AppointmentSourceInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Service\FhirApiClient;
use Psr\Log\LoggerInterface;


class FhirAppointmentSource implements AppointmentSourceInterface
{
    public function __construct(
        private readonly FhirApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int
    {
        $fhirAppointment = [
            'resourceType' => 'Appointment',
            'status' => 'booked',
            'participant' => [
                [
                    'actor' => [
                        'reference' => 'Patient/' . $user->getId(),
                        'display' => $user->getFirstName() . ' ' . $user->getLastName()
                    ],
                    'status' => 'accepted'
                ],
                [
                    'actor' => [
                        'reference' => 'Practitioner/' . $requestDto->getDoctorId()
                    ],
                    'status' => 'accepted'
                ]
            ],
            'serviceCategory' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-category',
                            'code' => 'specialty-' . $requestDto->getSpecialtyId(),
                            'display' => 'Specialty ID ' . $requestDto->getSpecialtyId()
                        ]
                    ]
                ]
            ],
            'serviceType' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-type',
                            'code' => 'service-' . $requestDto->getHospitalServiceId(),
                            'display' => 'Service ID ' . $requestDto->getHospitalServiceId()
                        ]
                    ]
                ]
            ],
        ];

        try {
            $response = $this->apiClient->post('/api/HInterop/CreateAppointment', $fhirAppointment);

            if (isset($response['id'])) {
                return (int)$response['id'];
            }

            $this->logger->error('FHIR API did not return an appointment ID', ['response' => $response]);
            throw new \RuntimeException('Failed to create appointment via FHIR API');
        } catch (\Exception $e) {
            $this->logger->error('Failed to create appointment via FHIR API', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAppointmentsByFilters(?AppointmentListRequestDto $requestDto): array
    {
        $queryParams = [];

        if ($requestDto !== null) {
            if ($requestDto->getHospitalId() !== null) {
                $queryParams['location'] = $requestDto->getHospitalId();
            }

            if ($requestDto->getSpecialtyId() !== null) {
                $queryParams['specialty'] = $requestDto->getSpecialtyId();
            }

            if ($requestDto->getDoctorName() !== null) {
                $queryParams['practitioner'] = $requestDto->getDoctorName();
            }

            if ($requestDto->getDate() !== null) {
                $queryParams['date'] = $requestDto->getDate()->format('Y-m-d');
            }

            if ($requestDto->getStatus() !== null) {
                $queryParams['status'] = $requestDto->getStatus();
            }
        }

        try {
            $response = $this->apiClient->get('/api/HInterop/GetAppointments', $queryParams);

            $appointments = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Appointment') {
                        $appointments[] = $this->mapFhirAppointmentToDto($entry['resource']);
                    }
                }
            }

            return $appointments;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch appointments from FHIR API', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getAppointmentById(int $appointmentId): ?AppointmentDto
    {
        try {
            $response = $this->apiClient->get('/api/HInterop/GetAppointments', ['identifier' => $appointmentId]);

            if (isset($response['entry'][0]['resource']) &&
                $response['entry'][0]['resource']['resourceType'] === 'Appointment') {
                return $this->mapFhirAppointmentToDto($response['entry'][0]['resource']);
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch appointment by ID from FHIR API',
                ['id' => $appointmentId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function findAvailableSlots(array $criteria): array
    {
        $queryParams = [];

        if (isset($criteria['specialty'])) {
            $queryParams['specialty'] = $criteria['specialty'];
        }

        if (isset($criteria['service'])) {
            $queryParams['serviceType'] = $criteria['service'];
        }

        if (isset($criteria['doctor'])) {
            $queryParams['practitioner'] = $criteria['doctor'];
        }

        if (isset($criteria['startDate'])) {
            $queryParams['date_start'] = $criteria['startDate']->format('Y-m-d');
        }

        if (isset($criteria['endDate'])) {
            $queryParams['date_end'] = $criteria['endDate']->format('Y-m-d');
        }

        try {
            $response = $this->apiClient->get('/api/HInterop/GetAvailableSlots', $queryParams);

            $groupedSlots = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource'])) {
                        $slot = $entry['resource'];
                        $date = substr($slot['start'], 0, 10);
                        $doctorId = null;
                        $doctorName = '';

                        if (isset($slot['schedule']['actor'])) {
                            foreach ($slot['schedule']['actor'] as $actor) {
                                if (isset($actor['reference']) &&
                                    strpos($actor['reference'], 'Practitioner/') === 0) {
                                    $doctorId = (int)substr($actor['reference'], 13);
                                    $doctorName = $actor['display'] ?? 'Unknown Doctor';
                                    break;
                                }
                            }
                        }

                        if ($doctorId) {
                            if (!isset($groupedSlots[$date][$doctorId])) {
                                $nameParts = explode(' ', $doctorName, 2);
                                $firstName = $nameParts[0] ?? '';
                                $lastName = $nameParts[1] ?? '';

                                $groupedSlots[$date][$doctorId] = [
                                    'id' => $doctorId,
                                    'firstName' => $firstName,
                                    'lastName' => $lastName,
                                    'slots' => []
                                ];
                            }

                            $startTime = substr($slot['start'], 11, 5);
                            $endTime = substr($slot['end'], 11, 5);

                            $groupedSlots[$date][$doctorId]['slots'][] = [
                                'id' => $slot['id'],
                                'startTime' => $startTime,
                                'endTime' => $endTime
                            ];
                        }
                    }
                }
            }

            return $groupedSlots;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch available slots from FHIR API', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function mapFhirAppointmentToDto(array $fhirAppointment): AppointmentDto
    {
        $dto = new AppointmentDto();

        if (isset($fhirAppointment['id'])) {
            $dto->setAppointmentId((int)$fhirAppointment['id']);
        }

        $patientId = null;
        $patientName = '';

        if (isset($fhirAppointment['participant'])) {
            foreach ($fhirAppointment['participant'] as $participant) {
                if (isset($participant['actor']['reference']) &&
                    strpos($participant['actor']['reference'], 'Patient/') === 0) {
                    $patientId = (int)substr($participant['actor']['reference'], 8);
                    $patientName = $participant['actor']['display'] ?? '';
                    break;
                }
            }
        }

        if ($patientId) {
            $dto->setPatientId($patientId);
            $dto->setPatientName($patientName);
        }

        $doctorId = null;
        $doctorName = '';

        if (isset($fhirAppointment['participant'])) {
            foreach ($fhirAppointment['participant'] as $participant) {
                if (isset($participant['actor']['reference']) &&
                    strpos($participant['actor']['reference'], 'Practitioner/') === 0) {
                    $doctorId = (int)substr($participant['actor']['reference'], 13);
                    $doctorName = $participant['actor']['display'] ?? '';
                    break;
                }
            }
        }

        if ($doctorId) {
            $dto->setDoctorId($doctorId);
            $dto->setDoctorName($doctorName);
        }

        $specialtyId = null;
        $specialtyName = '';

        if (isset($fhirAppointment['serviceCategory'][0]['coding'][0])) {
            $coding = $fhirAppointment['serviceCategory'][0]['coding'][0];
            if (isset($coding['code']) && strpos($coding['code'], 'specialty-') === 0) {
                $specialtyId = (int)substr($coding['code'], 10);
                $specialtyName = $coding['display'] ?? '';
            }
        }

        if ($specialtyId) {
            $dto->setSpecialtyId($specialtyId);
            $dto->setSpecialtyName($specialtyName);
        }

        $serviceId = null;
        $serviceName = '';

        if (isset($fhirAppointment['serviceType'][0]['coding'][0])) {
            $coding = $fhirAppointment['serviceType'][0]['coding'][0];
            if (isset($coding['code']) && strpos($coding['code'], 'service-') === 0) {
                $serviceId = (int)substr($coding['code'], 8);
                $serviceName = $coding['display'] ?? '';
            }
        }

        if ($serviceId) {
            $dto->setHospitalServiceId($serviceId);
            $dto->setHospitalServiceName($serviceName);
        }

        if (isset($fhirAppointment['start'])) {
            $dto->setAppointmentDate(new \DateTime($fhirAppointment['start']));
        }

        $dto->setHospitalId(1); // Default to a placeholder ID
        $dto->setHospitalName('Main Hospital'); // Default name

        return $dto;
    }
}