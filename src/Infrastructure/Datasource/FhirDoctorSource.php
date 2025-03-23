<?php

namespace App\Infrastructure\Datasource;

use App\Application\DataSource\DoctorSourceInterface;
use App\Domain\Dto\DoctorDetailsDto;
use App\Domain\Dto\DoctorDto;
use App\Domain\Dto\DoctorListRequestDto;
use App\Infrastructure\Service\FhirApiClient;
use Psr\Log\LoggerInterface;

class FhirDoctorSource implements DoctorSourceInterface
{
    public function __construct(
        private readonly FhirApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDoctorsByFilters(?DoctorListRequestDto $requestDto): array
    {
        $queryParams = [];

        if ($requestDto !== null) {
            if ($requestDto->getHospitalId() !== null) {
                $queryParams['organization'] = $requestDto->getHospitalId();
            }

            if ($requestDto->getSpecialtyId() !== null) {
                $queryParams['specialty'] = $requestDto->getSpecialtyId();
            }

            if ($requestDto->getDoctorName() !== null) {
                $name = $requestDto->getDoctorName();
                if (strpos($name, ' ') !== false) {
                    list($firstName, $lastName) = explode(' ', $name, 2);
                    $queryParams['given'] = $firstName;
                    $queryParams['family'] = $lastName;
                } else {
                    $queryParams['name'] = $name;
                }
            }

            if ($requestDto->getServiceId() !== null) {
                $queryParams['service'] = $requestDto->getServiceId();
            }
        }

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitioners', $queryParams);
            $doctors = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Practitioner') {
                        $doctors[] = $this->mapFhirPractitionerToDto($entry['resource']);
                    }
                }
            }

            return $doctors;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch doctors from FHIR API', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getDoctorDetails(int $doctorId): ?DoctorDetailsDto
    {
        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitioners', ['identifier' => $doctorId]);

            if (!isset($response['entry'][0]['resource']) ||
                $response['entry'][0]['resource']['resourceType'] !== 'Practitioner') {
                return null;
            }

            $practitioner = $response['entry'][0]['resource'];
            $doctorDetails = $this->mapFhirPractitionerToDetailsDto($practitioner);
            $rolesResponse = $this->apiClient->get('/api/HInterop/GetPractitionerRoles', ['practitioner' => $doctorId]);

            if (isset($rolesResponse['entry']) && is_array($rolesResponse['entry'])) {
                foreach ($rolesResponse['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'PractitionerRole') {
                        $this->enrichDoctorDetailsWithRole($doctorDetails, $entry['resource']);
                    }
                }
            }

            return $doctorDetails;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch doctor details from FHIR API',
                ['id' => $doctorId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function findDoctorsByService(int $serviceId): array
    {
        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles', ['service' => $serviceId]);

            $doctors = [];
            $processedIds = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'PractitionerRole') {
                        $role = $entry['resource'];

                        if (isset($role['practitioner']['reference']) &&
                            strpos($role['practitioner']['reference'], 'Practitioner/') === 0) {
                            $practitionerId = (int)substr($role['practitioner']['reference'], 13);

                            if (in_array($practitionerId, $processedIds)) {
                                continue;
                            }

                            $processedIds[] = $practitionerId;

                            $practitionerResponse = $this->apiClient->get('/api/HInterop/GetPractitioners',
                                ['identifier' => $practitionerId]);

                            if (isset($practitionerResponse['entry'][0]['resource']) &&
                                $practitionerResponse['entry'][0]['resource']['resourceType'] === 'Practitioner') {
                                $doctors[] = $this->mapFhirPractitionerToDto($practitionerResponse['entry'][0]['resource']);
                            }
                        }
                    }
                }
            }

            return $doctors;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch doctors by service from FHIR API',
                ['serviceId' => $serviceId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function mapFhirPractitionerToDto(array $fhirPractitioner): DoctorDto
    {
        $dto = new DoctorDto();

        if (isset($fhirPractitioner['id'])) {
            $dto->setId((int)$fhirPractitioner['id']);
        }

        if (isset($fhirPractitioner['telecom'])) {
            foreach ($fhirPractitioner['telecom'] as $telecom) {
                if ($telecom['system'] === 'email') {
                    $dto->setEmail($telecom['value']);
                    break;
                }
            }
        }

        if (isset($fhirPractitioner['name'][0])) {
            $name = $fhirPractitioner['name'][0];

            if (isset($name['given'][0])) {
                $dto->setFirstName($name['given'][0]);
            }

            if (isset($name['family'])) {
                $dto->setLastName($name['family']);
            }
        }

        if (isset($fhirPractitioner['identifier'])) {
            foreach ($fhirPractitioner['identifier'] as $identifier) {
                if (isset($identifier['system']) && $identifier['system'] === 'urn:oid:1.2.3.4.5.6.7') {
                    $dto->setCnp($identifier['value']);
                    break;
                }
            }
        }

        if (isset($fhirPractitioner['telecom'])) {
            foreach ($fhirPractitioner['telecom'] as $telecom) {
                if ($telecom['system'] === 'phone') {
                    $dto->setPhone($telecom['value']);
                    break;
                }
            }
        }

        return $dto;
    }

    private function mapFhirPractitionerToDetailsDto(array $fhirPractitioner): DoctorDetailsDto
    {
        $dto = new DoctorDetailsDto();

        if (isset($fhirPractitioner['id'])) {
            $dto->setId((int)$fhirPractitioner['id']);
        }

        if (isset($fhirPractitioner['telecom'])) {
            foreach ($fhirPractitioner['telecom'] as $telecom) {
                if ($telecom['system'] === 'email') {
                    $dto->setEmail($telecom['value']);
                    break;
                }
            }
        }

        if (isset($fhirPractitioner['name'][0])) {
            $name = $fhirPractitioner['name'][0];

            if (isset($name['given'][0])) {
                $dto->setFirstName($name['given'][0]);
            }

            if (isset($name['family'])) {
                $dto->setLastName($name['family']);
            }
        }

        if (isset($fhirPractitioner['identifier'])) {
            foreach ($fhirPractitioner['identifier'] as $identifier) {
                if (isset($identifier['system']) && $identifier['system'] === 'urn:oid:1.2.3.4.5.6.7') {
                    $dto->setCnp($identifier['value']);
                    break;
                }
            }
        }

        if (isset($fhirPractitioner['telecom'])) {
            foreach ($fhirPractitioner['telecom'] as $telecom) {
                if ($telecom['system'] === 'phone') {
                    $dto->setPhone($telecom['value']);
                    break;
                }
            }
        }

        return $dto;
    }

    private function enrichDoctorDetailsWithRole(DoctorDetailsDto $dto, array $role): void
    {
        if (isset($role['specialty'])) {
            foreach ($role['specialty'] as $specialty) {
                if (isset($specialty['coding'][0])) {
                    $coding = $specialty['coding'][0];
                    $specialtyId = (int)$coding['code'];
                    $specialtyCode = $coding['code'];
                    $specialtyName = $coding['display'] ?? '';

                    $dto->addSpecialty($specialtyId, $specialtyCode, $specialtyName);
                }
            }
        }

        if (isset($role['code'])) {
            foreach ($role['code'] as $code) {
                if (isset($code['coding'][0])) {
                    $coding = $code['coding'][0];
                    $serviceId = (int)$coding['code'];
                    $serviceName = $coding['display'] ?? '';

                    $hospitalId = 1;
                    $hospitalName = 'Main Hospital';
                    $medicalServiceName = $serviceName;
                    $medicalServiceCode = $coding['code'];

                    $dto->addHospitalService(
                        $serviceId,
                        $serviceName,
                        $hospitalId,
                        $hospitalName,
                        $medicalServiceName,
                        $medicalServiceCode
                    );
                }
            }
        }
    }
}