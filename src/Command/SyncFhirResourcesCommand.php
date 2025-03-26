<?php

namespace App\Command;

use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Service\FhirApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sync-fhir-resources',
    description: 'Synchronizes resources from FHIR API with local database every minute',
)]
class SyncFhirResourcesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private FhirApiClient $apiClient;
    private LoggerInterface $logger;
    private OutputInterface $output;

    public function __construct(
        EntityManagerInterface $entityManager,
        FhirApiClient $apiClient,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->apiClient = $apiClient;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $startTime = microtime(true);

        $this->logger->info('Starting FHIR resources synchronization');
        $output->writeln('Starting FHIR resources synchronization...');

        try {
            $this->syncMedicalSpecialties();
            $this->syncPractitioners();
            $this->syncHealthcareServices();
            $this->syncPractitionerRoles();
            $this->syncSchedules();
            $this->syncSlots();
            $this->syncAppointments();

            $this->entityManager->flush();

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->logger->info('FHIR resources synchronization completed', ['execution_time' => $executionTime]);
            $output->writeln("Synchronization completed successfully in {$executionTime} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Error during FHIR synchronization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $output->writeln('Error during synchronization: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncPractitioners(): void
    {
        $this->output->writeln('Synchronizing practitioners...');
        $this->logger->info('Fetching practitioners from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitioners?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No practitioners found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Practitioner') {
                    continue;
                }

                $practitioner = $entry['resource'];

                try {
                    $hisId = $practitioner['id'] ?? null;
                    if (!$hisId) {
                        $this->logger->warning('Practitioner without ID', ['data' => json_encode($practitioner)]);
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['hisId' => $hisId]);
                    $isNew = false;

                    if (!$doctor) {
                        $doctor = new Doctor();
                        $doctor->setHisId($hisId);
                        $doctor->setRoles([Doctor::BASE_ROLE]);
                        $doctor->setPassword(password_hash(uniqid('', true), PASSWORD_BCRYPT));
                        $isNew = true;
                    }

                    $email = '';
                    if (isset($practitioner['telecom']) && is_array($practitioner['telecom'])) {
                        foreach ($practitioner['telecom'] as $telecom) {
                            if ($telecom['system'] === 'email') {
                                $email = $telecom['value'];
                                break;
                            }
                        }
                    }

                    if (empty($email)) {
                        $email = 'doctor_' . $hisId . '@example.com';
                    }

                    $phone = '';
                    if (isset($practitioner['telecom']) && is_array($practitioner['telecom'])) {
                        foreach ($practitioner['telecom'] as $telecom) {
                            if ($telecom['system'] === 'phone') {
                                $phone = $telecom['value'];
                                break;
                            }
                        }
                    }

                    $firstName = '';
                    $lastName = '';
                    if (isset($practitioner['name'][0])) {
                        $name = $practitioner['name'][0];
                        $firstName = $name['given'][0] ?? '';
                        $lastName = $name['family'] ?? '';
                    }

                    $cnp = '';
                    if (isset($practitioner['identifier']) && is_array($practitioner['identifier'])) {
                        foreach ($practitioner['identifier'] as $identifier) {
                            if (isset($identifier['system']) && $identifier['system'] === 'urn:oid:1.2.3.4.5.6.7') {
                                $cnp = $identifier['value'];
                                break;
                            }
                        }
                    }

                    if (empty($cnp)) {
                        $cnp = str_pad((string) $hisId, 13, '0', STR_PAD_LEFT);
                    }

                    $doctor->setEmail($email);
                    $doctor->setFirstName($firstName);
                    $doctor->setLastName($lastName);
                    $doctor->setCnp($cnp);
                    $doctor->setPhone($phone);

                    $this->entityManager->persist($doctor);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing practitioner', [
                        'hisId' => $practitioner['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Practitioners: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Practitioners sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync practitioners', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncPractitionerRoles(): void
    {
        $this->output->writeln('Synchronizing practitioner roles...');
        $this->logger->info('Fetching practitioner roles from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No practitioner roles found or invalid response format');
                return;
            }

            $count = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'PractitionerRole') {
                    continue;
                }

                $role = $entry['resource'];

                try {
                    // Extract practitioner ID from the correct field in the FHIR resource
                    $practitionerId = null;

                    // Check for the correct practitioner identifier structure based on the JSON example
                    if (isset($role['practitioner']['identifier']['value'])) {
                        $practitionerId = $role['practitioner']['identifier']['value'];
                    }
                    // Fallback to reference extraction if the identifier structure is different
                    elseif (isset($role['practitioner']['reference'])) {
                        if (preg_match('/Practitioner\/(\w+)/', $role['practitioner']['reference'], $matches)) {
                            $practitionerId = $matches[1];
                        }
                    }

                    if (!$practitionerId) {
                        $this->logger->warning('Could not extract practitioner ID', [
                            'roleId' => $role['id'] ?? 'unknown',
                            'practitionerData' => json_encode($role['practitioner'] ?? [])
                        ]);
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['hisId' => $practitionerId]);

                    if (!$doctor) {
                        $this->logger->warning('Doctor not found for practitioner role', [
                            'hisId' => $practitionerId,
                            'roleId' => $role['id'] ?? 'unknown'
                        ]);
                        $errors++;
                        continue;
                    }

                    // Associate specialties - improved mapping
                    if (isset($role['specialty']) && is_array($role['specialty'])) {
                        foreach ($role['specialty'] as $specialty) {
                            $specialtyCode = $this->extractCodeFromCoding($specialty);

                            if ($specialtyCode) {
                                $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)
                                    ->findOneBy(['code' => $specialtyCode]);

                                if ($medicalSpecialty && !$doctor->getMedicalSpecialties()->contains($medicalSpecialty)) {
                                    $doctor->addMedicalSpecialty($medicalSpecialty);
                                    $this->logger->info('Added specialty to doctor', [
                                        'doctorId' => $doctor->getId(),
                                        'specialtyId' => $medicalSpecialty->getId(),
                                        'specialtyCode' => $specialtyCode
                                    ]);
                                } elseif (!$medicalSpecialty) {
                                    $this->logger->warning('Medical specialty not found', [
                                        'code' => $specialtyCode,
                                        'doctorId' => $doctor->getId()
                                    ]);
                                }
                            }
                        }
                    }

                    // Associate services - improved mapping
                    if (isset($role['code']) && is_array($role['code'])) {
                        foreach ($role['code'] as $code) {
                            $serviceCode = $this->extractCodeFromCoding($code);

                            if ($serviceCode) {
                                $hospitalService = $this->entityManager->getRepository(HospitalService::class)
                                    ->findOneBy(['code' => $serviceCode]);

                                if ($hospitalService && !$doctor->getHospitalServices()->contains($hospitalService)) {
                                    $doctor->addHospitalService($hospitalService);
                                    $this->logger->info('Added service to doctor', [
                                        'doctorId' => $doctor->getId(),
                                        'serviceId' => $hospitalService->getId(),
                                        'serviceCode' => $serviceCode
                                    ]);
                                } elseif (!$hospitalService) {
                                    $this->logger->warning('Hospital service not found', [
                                        'code' => $serviceCode,
                                        'doctorId' => $doctor->getId()
                                    ]);
                                }
                            }
                        }
                    }

                    $this->entityManager->persist($doctor);
                    $count++;
                } catch (\Exception $e) {
                    $this->logger->error('Error processing practitioner role', [
                        'roleId' => $role['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errors++;
                }
            }

            // Flush the entity manager to persist all changes at once
            $this->entityManager->flush();

            $this->output->writeln("Practitioner roles: {$count} processed, {$errors} errors");
            $this->logger->info('Practitioner roles sync completed', [
                'processed' => $count,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync practitioner roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Helper method to extract code from coding array
     *
     * @param array $codingContainer The array containing coding information
     * @return string|null The extracted code or null if not found
     */
    private function extractCodeFromCoding(array $codingContainer): ?string
    {
        // Check for the structure in the example JSON
        if (isset($codingContainer['coding'][0]['code'])) {
            return $codingContainer['coding'][0]['code'];
        }

        // Alternative structure check if needed
        if (isset($codingContainer['coding']) && is_array($codingContainer['coding'])) {
            foreach ($codingContainer['coding'] as $coding) {
                if (isset($coding['code'])) {
                    return $coding['code'];
                }
            }
        }

        return null;
    }

    private function syncHealthcareServices(): void
    {
        $this->output->writeln('Synchronizing healthcare services...');
        $this->logger->info('Fetching healthcare services from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetHealthcareServices?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No healthcare services found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'HealthcareService') {
                    continue;
                }

                $service = $entry['resource'];

                try {
                    $hisId = $service['id'] ?? null;
                    if (!$hisId) {
                        $this->logger->warning('HealthcareService without ID', ['data' => json_encode($service)]);
                        $errors++;
                        continue;
                    }

                    $hospitalService = $this->entityManager->getRepository(HospitalService::class)->findOneBy(['hisId' => $hisId]);
                    $isNew = false;

                    if (!$hospitalService) {
                        $hospitalService = new HospitalService();
                        $hospitalService->setHisId($hisId);
                        $hospitalService->setIsActive(true);
                        $hospitalService->setColor('#3788d8');
                        $hospitalService->setMode(HospitalService::AMB_MODE);
                        $isNew = true;
                    }

                    $name = $service['name'] ?? 'Service ' . $hisId;
                    $description = '';
                    if (isset($service['comment'])) {
                        $description = $service['comment'];
                    } elseif (isset($service['description'])) {
                        $description = $service['description'];
                    }

                    $code = '';
                    if (isset($service['type']) && is_array($service['type'])) {
                        foreach ($service['type'] as $type) {
                            if (isset($type['coding'][0]['code'])) {
                                $code = $type['coding'][0]['code'];
                                break;
                            }
                        }
                    }

                    if (empty($code)) {
                        $code = 'CODE_' . $hisId;
                    }

                    $duration = 30; // Default duration
                    if (isset($service['appointmentDuration'])) {
                        $duration = intval($service['appointmentDuration']);
                    }

                    $price = '0'; // Default price
                    if (isset($service['extraDetails'])) {
                        if (preg_match('/price[:\s]+(\d+)/i', $service['extraDetails'], $matches)) {
                            $price = $matches[1];
                        }
                    }

                    $specialtyId = null;
                    if (isset($service['specialty']) && is_array($service['specialty'])) {
                        foreach ($service['specialty'] as $specialty) {
                            if (isset($specialty['coding'][0]['code'])) {
                                $specialtyCode = $specialty['coding'][0]['code'];
                                $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->findOneBy(['code' => $specialtyCode]);

                                if ($medicalSpecialty) {
                                    $specialtyId = $medicalSpecialty->getId();
                                    break;
                                }
                            }
                        }
                    }

                    $hospitalService->setName($name);
                    $hospitalService->setDescription($description);
                    $hospitalService->setCode($code);
                    $hospitalService->setDuration((string) $duration);
                    $hospitalService->setPrice($price);

                    if ($specialtyId) {
                        $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->find($specialtyId);
                        if ($medicalSpecialty) {
                            $hospitalService->setMedicalSpecialty($medicalSpecialty);
                        }
                    }

                    $this->entityManager->persist($hospitalService);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing healthcare service', [
                        'hisId' => $service['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Healthcare services: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Healthcare services sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync healthcare services', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncSchedules(): void
    {
        $this->output->writeln('Synchronizing schedules...');
        $this->logger->info('Fetching schedules from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetSchedules?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No schedules found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Schedule') {
                    continue;
                }

                $schedule = $entry['resource'];

                try {
                    $hisId = $schedule['id'] ?? null;
                    if (!$hisId) {
                        $this->logger->warning('Schedule without ID', ['data' => json_encode($schedule)]);
                        $errors++;
                        continue;
                    }

                    $doctorId = null;
                    if (isset($schedule['actor']) && is_array($schedule['actor'])) {
                        foreach ($schedule['actor'] as $actor) {
                            if (isset($actor['reference']) && strpos($actor['reference'], 'Practitioner/') === 0) {
                                $practitionerId = substr($actor['reference'], 13);
                                $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['hisId' => $practitionerId]);
                                if ($doctor) {
                                    $doctorId = $doctor->getId();
                                    break;
                                }
                            }
                        }
                    }

                    if (!$doctorId) {
                        $this->logger->warning('Schedule without valid doctor reference', ['hisId' => $hisId]);
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->find($doctorId);

                    $date = new \DateTime('today');
                    if (isset($schedule['planningHorizon']['start'])) {
                        $date = new \DateTime($schedule['planningHorizon']['start']);
                    }

                    $doctorSchedule = $this->entityManager->getRepository(DoctorSchedule::class)
                        ->findOneBy([
                            'doctor' => $doctor,
                            'date' => $date,
                            'hisId' => $hisId
                        ]);

                    $isNew = false;
                    if (!$doctorSchedule) {
                        $doctorSchedule = new DoctorSchedule();
                        $doctorSchedule->setDoctor($doctor);
                        $doctorSchedule->setDate($date);
                        $doctorSchedule->setHisId($hisId);
                        $isNew = true;
                    }

                    $this->entityManager->persist($doctorSchedule);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing schedule', [
                        'hisId' => $schedule['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Schedules: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Schedules sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync schedules', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncSlots(): void
    {
        $this->output->writeln('Synchronizing slots...');
        $this->logger->info('Fetching slots from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetSlots?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No slots found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Slot') {
                    continue;
                }

                $slot = $entry['resource'];

                try {
                    $hisId = $slot['id'] ?? null;
                    if (!$hisId) {
                        $this->logger->warning('Slot without ID', ['data' => json_encode($slot)]);
                        $errors++;
                        continue;
                    }

                    $scheduleId = null;
                    if (isset($slot['schedule']['reference']) && strpos($slot['schedule']['reference'], 'Schedule/') === 0) {
                        $scheduleHisId = substr($slot['schedule']['reference'], 9);
                        $doctorSchedule = $this->entityManager->getRepository(DoctorSchedule::class)->findOneBy(['hisId' => $scheduleHisId]);
                        if ($doctorSchedule) {
                            $scheduleId = $doctorSchedule->getId();
                        }
                    }

                    if (!$scheduleId) {
                        $this->logger->warning('Slot without valid schedule reference', ['hisId' => $hisId]);
                        $errors++;
                        continue;
                    }

                    $schedule = $this->entityManager->getRepository(DoctorSchedule::class)->find($scheduleId);

                    $timeSlot = $this->entityManager->getRepository(TimeSlot::class)
                        ->findOneBy([
                            'schedule' => $schedule,
                            'hisId' => $hisId
                        ]);

                    $isNew = false;
                    if (!$timeSlot) {
                        $timeSlot = new TimeSlot();
                        $timeSlot->setSchedule($schedule);
                        $timeSlot->setHisId($hisId);
                        $isNew = true;
                    }

                    $startTime = new \DateTime('08:00');
                    if (isset($slot['start'])) {
                        $startDateTime = new \DateTime($slot['start']);
                        $startTime = $startDateTime;
                    }

                    $endTime = new \DateTime('08:30');
                    if (isset($slot['end'])) {
                        $endDateTime = new \DateTime($slot['end']);
                        $endTime = $endDateTime;
                    }

                    $isBooked = false;
                    if (isset($slot['status']) && $slot['status'] === 'busy') {
                        $isBooked = true;
                    }

                    $serviceId = null;
                    if (isset($slot['serviceType']) && is_array($slot['serviceType'])) {
                        foreach ($slot['serviceType'] as $serviceType) {
                            if (isset($serviceType['coding'][0]['code'])) {
                                $serviceCode = $serviceType['coding'][0]['code'];
                                $hospitalService = $this->entityManager->getRepository(HospitalService::class)->findOneBy(['code' => $serviceCode]);
                                if ($hospitalService) {
                                    $serviceId = $hospitalService->getId();
                                    break;
                                }
                            }
                        }
                    }

                    $timeSlot->setStartTime($startTime);
                    $timeSlot->setEndTime($endTime);
                    $timeSlot->setIsBooked($isBooked);

                    if ($serviceId) {
                        $hospitalService = $this->entityManager->getRepository(HospitalService::class)->find($serviceId);
                        if ($hospitalService) {
                            $timeSlot->setHospitalService($hospitalService);
                        }
                    }

                    $this->entityManager->persist($timeSlot);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing slot', [
                        'hisId' => $slot['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Slots: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Slots sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync slots', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncAppointments(): void
    {
        $this->output->writeln('Synchronizing appointments...');
        $this->logger->info('Fetching appointments from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetAppointments?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No appointments found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Appointment') {
                    continue;
                }

                $appointment = $entry['resource'];

                try {
                    $hisId = $appointment['id'] ?? null;
                    if (!$hisId) {
                        $this->logger->warning('Appointment without ID', ['data' => json_encode($appointment)]);
                        $errors++;
                        continue;
                    }

                    $existingAppointment = $this->entityManager->getRepository(Appointment::class)->findOneBy(['hisId' => $hisId]);
                    $isNew = false;

                    if (!$existingAppointment) {
                        $existingAppointment = new Appointment();
                        $existingAppointment->setHisId($hisId);
                        $existingAppointment->setIsActive(true);
                        $isNew = true;
                    }

                    $patientId = null;
                    $doctorId = null;

                    if (isset($appointment['participant']) && is_array($appointment['participant'])) {
                        foreach ($appointment['participant'] as $participant) {
                            if (isset($participant['actor']['reference'])) {
                                if (strpos($participant['actor']['reference'], 'Patient/') === 0) {
                                    $patientHisId = substr($participant['actor']['reference'], 8);
                                    $patient = $this->entityManager->getRepository(Doctor::class)->findOneBy(['hisId' => $patientHisId]);
                                    if ($patient) {
                                        $patientId = $patient->getId();
                                    }
                                } elseif (strpos($participant['actor']['reference'], 'Practitioner/') === 0) {
                                    $practitionerHisId = substr($participant['actor']['reference'], 13);
                                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['hisId' => $practitionerHisId]);
                                    if ($doctor) {
                                        $doctorId = $doctor->getId();
                                    }
                                }
                            }
                        }
                    }

                    if (!$patientId || !$doctorId) {
                        $this->logger->warning('Appointment without valid patient or doctor reference', ['hisId' => $hisId]);
                        $errors++;
                        continue;
                    }

                    $patient = $this->entityManager->getRepository(Doctor::class)->find($patientId);
                    $doctor = $this->entityManager->getRepository(Doctor::class)->find($doctorId);

                    $specialtyId = null;
                    if (isset($appointment['serviceCategory']) && is_array($appointment['serviceCategory'])) {
                        foreach ($appointment['serviceCategory'] as $category) {
                            if (isset($category['coding'][0]['code'])) {
                                $code = $category['coding'][0]['code'];
                                if (strpos($code, 'specialty-') === 0) {
                                    $specialtyId = intval(substr($code, 10));
                                    $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->find($specialtyId);
                                    break;
                                }
                            }
                        }
                    }

                    $serviceId = null;
                    if (isset($appointment['serviceType']) && is_array($appointment['serviceType'])) {
                        foreach ($appointment['serviceType'] as $service) {
                            if (isset($service['coding'][0]['code'])) {
                                $code = $service['coding'][0]['code'];
                                if (strpos($code, 'service-') === 0) {
                                    $serviceId = intval(substr($code, 8));
                                    $hospitalService = $this->entityManager->getRepository(HospitalService::class)->find($serviceId);
                                    break;
                                }
                            }
                        }
                    }

                    if (!$specialtyId || !$serviceId) {
                        $this->logger->warning('Appointment without valid specialty or service reference', ['hisId' => $hisId]);
                        $errors++;
                        continue;
                    }

                    $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->find($specialtyId);
                    $hospitalService = $this->entityManager->getRepository(HospitalService::class)->find($serviceId);

                    // Find time slot
                    $timeSlotId = null;
                    $appointmentDate = null;
                    if (isset($appointment['start'])) {
                        $appointmentDate = new \DateTime($appointment['start']);
                        $scheduleDate = (clone $appointmentDate)->setTime(0, 0, 0);
                        $startTime = (clone $appointmentDate)->setDate(1970, 1, 1);

                        $doctorSchedules = $this->entityManager->getRepository(DoctorSchedule::class)
                            ->findBy([
                                'doctor' => $doctor,
                                'date' => $scheduleDate
                            ]);

                        foreach ($doctorSchedules as $schedule) {
                            $timeSlot = $this->entityManager->getRepository(TimeSlot::class)
                                ->findOneBy([
                                    'schedule' => $schedule,
                                    'startTime' => $startTime
                                ]);

                            if ($timeSlot) {
                                $timeSlotId = $timeSlot->getId();
                                break;
                            }
                        }
                    }

                    if (!$timeSlotId) {
                        $this->logger->warning('Appointment without valid time slot', ['hisId' => $hisId]);

                        // Create a schedule and time slot if needed
                        if ($appointmentDate) {
                            $scheduleDate = (clone $appointmentDate)->setTime(0, 0, 0);
                            $startTime = (clone $appointmentDate)->setDate(1970, 1, 1);
                            $endTime = (clone $startTime)->modify('+30 minutes');

                            $schedule = $this->entityManager->getRepository(DoctorSchedule::class)
                                ->findOneBy([
                                    'doctor' => $doctor,
                                    'date' => $scheduleDate
                                ]);

                            if (!$schedule) {
                                $schedule = new DoctorSchedule();
                                $schedule->setDoctor($doctor);
                                $schedule->setDate($scheduleDate);
                                $schedule->setHisId('auto_' . $hisId);
                                $this->entityManager->persist($schedule);
                            }

                            $timeSlot = new TimeSlot();
                            $timeSlot->setSchedule($schedule);
                            $timeSlot->setStartTime($startTime);
                            $timeSlot->setEndTime($endTime);
                            $timeSlot->setIsBooked(true);
                            $timeSlot->setHisId('auto_' . $hisId);
                            $timeSlot->setHospitalService($hospitalService);
                            $this->entityManager->persist($timeSlot);

                            $timeSlotId = $timeSlot->getId();
                        } else {
                            $errors++;
                            continue;
                        }
                    }

                    $timeSlot = $this->entityManager->getRepository(TimeSlot::class)->find($timeSlotId);

                    $existingAppointment->setPatient($patient);
                    $existingAppointment->setDoctor($doctor);
                    $existingAppointment->setMedicalSpecialty($medicalSpecialty);
                    $existingAppointment->setHospitalService($hospitalService);
                    $existingAppointment->setTimeSlot($timeSlot);

                    // Mark the appointment as active or inactive based on status
                    if (isset($appointment['status'])) {
                        $isActive = $appointment['status'] === 'booked' || $appointment['status'] === 'fulfilled';
                        $existingAppointment->setIsActive($isActive);
                    }

                    $this->entityManager->persist($existingAppointment);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing appointment', [
                        'hisId' => $appointment['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Appointments: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Appointments sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync appointments', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncMedicalSpecialties(): void
    {
        $this->output->writeln('Synchronizing medical specialties...');
        $this->logger->info('Processing medical specialties from FHIR API');

        try {
            // Since medical specialties might be referenced in codes/codings, we'll create a basic set
            // based on the PractitionerRoles response
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles?active=true');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No practitioner roles found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;
            $specialtyCodes = [];

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'PractitionerRole') {
                    continue;
                }

                $role = $entry['resource'];

                if (isset($role['specialty']) && is_array($role['specialty'])) {
                    foreach ($role['specialty'] as $specialty) {
                        if (isset($specialty['coding'][0]['code']) && isset($specialty['coding'][0]['display'])) {
                            $code = $specialty['coding'][0]['code'];
                            $name = $specialty['coding'][0]['display'];
                            $specialtyCodes[$code] = $name;
                        }
                    }
                }
            }

            foreach ($specialtyCodes as $code => $name) {
                try {
                    $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->findOneBy(['code' => $code]);
                    $isNew = false;

                    if (!$medicalSpecialty) {
                        $medicalSpecialty = new MedicalSpecialty();
                        $medicalSpecialty->setCode($code);
                        $medicalSpecialty->setIsActive(true);
                        $isNew = true;
                    }

                    $medicalSpecialty->setName($name);

                    $this->entityManager->persist($medicalSpecialty);

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing medical specialty', [
                        'code' => $code,
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Medical specialties: {$count} new, {$updated} updated, {$errors} errors");
            $this->logger->info('Medical specialties sync completed', [
                'new' => $count,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync medical specialties', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}