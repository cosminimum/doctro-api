<?php

namespace App\Infrastructure\Datasource;

use App\Application\Datasource\ServiceSourceInterface;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Service\FhirApiClient;
use Psr\Log\LoggerInterface;

class FhirServiceSource implements ServiceSourceInterface
{
    public function __construct(
        private readonly FhirApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function findServicesBySpecialty(int $specialtyId): array
    {
        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles', [
                'specialty' => $specialtyId
            ]);

            $services = [];
            $processedIds = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'PractitionerRole') {
                        $role = $entry['resource'];

                        if (isset($role['code'])) {
                            foreach ($role['code'] as $code) {
                                if (isset($code['coding'][0])) {
                                    $coding = $code['coding'][0];
                                    $serviceId = (int)$coding['code'];

                                    if (in_array($serviceId, $processedIds)) {
                                        continue;
                                    }

                                    $processedIds[] = $serviceId;

                                    $service = new HospitalService();
                                    $service->setId($serviceId);
                                    $service->setName($coding['display'] ?? 'Service ' . $serviceId);
                                    $service->setCode($coding['code']);
                                    $service->setDescription($coding['display'] ?? '');
                                    $service->setPrice('0'); // Default price
                                    $service->setDuration('15'); // Default duration
                                    $service->setMode(HospitalService::AMB_MODE); // Default mode
                                    $service->setIsActive(true);

                                    $services[] = $service;
                                }
                            }
                        }
                    }
                }
            }

            return $services;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch services by specialty from FHIR API',
                ['specialtyId' => $specialtyId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function findServicesByDoctor($doctor): array
    {
        $doctorId = is_object($doctor) ? $doctor->getId() : $doctor;

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles', [
                'practitioner' => $doctorId
            ]);

            $services = [];
            $processedIds = [];

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $entry) {
                    if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'PractitionerRole') {
                        $role = $entry['resource'];

                        if (isset($role['code'])) {
                            foreach ($role['code'] as $code) {
                                if (isset($code['coding'][0])) {
                                    $coding = $code['coding'][0];
                                    $serviceId = (int)$coding['code'];

                                    if (in_array($serviceId, $processedIds)) {
                                        continue;
                                    }

                                    $processedIds[] = $serviceId;

                                    $service = new HospitalService();
                                    $service->setId($serviceId);
                                    $service->setName($coding['display'] ?? 'Service ' . $serviceId);
                                    $service->setCode($coding['code']);
                                    $service->setDescription($coding['display'] ?? '');
                                    $service->setPrice('0'); // Default price
                                    $service->setDuration('15');
                                    $service->setMode(HospitalService::AMB_MODE);
                                    $service->setIsActive(true);

                                    $services[] = $service;
                                }
                            }
                        }
                    }
                }
            }

            return $services;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch services by doctor from FHIR API',
                ['doctorId' => $doctorId, 'error' => $e->getMessage()]);
            return [];
        }
    }
}