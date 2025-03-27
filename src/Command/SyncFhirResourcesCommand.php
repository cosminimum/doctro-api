<?php

namespace App\Command;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorSchedule;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Repository\DoctorScheduleRepository;
use App\Infrastructure\Repository\UserRepository;
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
    private DoctorScheduleRepository $doctorScheduleRepository;

    private UserRepository $userRepository;
    private DoctorRepositoryInterface $doctorRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        FhirApiClient $apiClient,
        LoggerInterface $logger,
        UserRepository $userRepository,
        DoctorScheduleRepository $doctorScheduleRepository,
        DoctorRepositoryInterface $doctorRepository,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->doctorScheduleRepository = $doctorScheduleRepository;
        $this->doctorRepository = $doctorRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $startTime = microtime(true);

        $this->logger->info('Starting FHIR resources synchronization');
        $output->writeln('Starting FHIR resources synchronization...');

        try {
//            $this->syncMedicalSpecialties();
//            $this->syncPractitioners();
//            $this->syncHealthcareServices();
//            $this->syncPractitionerRoles();
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
                    $idHis = $practitioner['id'] ?? null;
                    if (!$idHis) {
                        $this->logger->warning('Practitioner without ID', ['data' => json_encode($practitioner)]);
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['idHis' => $idHis]);
                    $isNew = false;

                    if (!$doctor) {
                        $doctor = new Doctor();
                        $doctor->setIdHis($idHis);
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
                        $email = 'doctor_' . $idHis . '@example.com';
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
                        $cnp = str_pad((string) $idHis, 13, '0', STR_PAD_LEFT);
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
                        'idHis' => $practitioner['id'] ?? 'unknown',
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
                    if (!isset($role['practitioner']['reference'])) {
                        $this->logger->warning('PractitionerRole without practitioner reference', ['data' => json_encode($role)]);
                        $errors++;
                        continue;
                    }

                    $practitionerId = null;
                    if (preg_match('/Practitioner\/(\w+)/', $role['practitioner']['reference'], $matches)) {
                        $practitionerId = $matches[1];
                    }

                    if (!$practitionerId) {
                        $this->logger->warning('Could not extract practitioner ID from reference', ['reference' => $role['practitioner']['reference']]);
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['idHis' => $practitionerId]);

                    if (!$doctor) {
                        $this->logger->warning('Doctor not found for practitioner role', ['idHis' => $practitionerId]);
                        $errors++;
                        continue;
                    }

                    // Associate specialties
                    if (isset($role['specialty']) && is_array($role['specialty'])) {
                        foreach ($role['specialty'] as $specialty) {
                            if (isset($specialty['coding'][0]['code'])) {
                                $specialtyCode = $specialty['coding'][0]['code'];
                                $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)->findOneBy(['code' => $specialtyCode]);

                                if ($medicalSpecialty && !$doctor->getMedicalSpecialties()->contains($medicalSpecialty)) {
                                    $doctor->addMedicalSpecialty($medicalSpecialty);
                                    $this->logger->info('Added specialty to doctor', [
                                        'doctorId' => $doctor->getId(),
                                        'specialtyId' => $medicalSpecialty->getId()
                                    ]);
                                }
                            }
                        }
                    }

                    // Associate services
                    if (isset($role['code']) && is_array($role['code'])) {
                        foreach ($role['code'] as $code) {
                            if (isset($code['coding'][0]['code'])) {
                                $serviceCode = $code['coding'][0]['code'];
                                $hospitalService = $this->entityManager->getRepository(HospitalService::class)->findOneBy(['code' => $serviceCode]);

                                if ($hospitalService && !$doctor->getHospitalServices()->contains($hospitalService)) {
                                    $doctor->addHospitalService($hospitalService);
                                    $this->logger->info('Added service to doctor', [
                                        'doctorId' => $doctor->getId(),
                                        'serviceId' => $hospitalService->getId()
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
                        'error' => $e->getMessage()
                    ]);
                    $errors++;
                }
            }

            $this->output->writeln("Practitioner roles: {$count} processed, {$errors} errors");
            $this->logger->info('Practitioner roles sync completed', [
                'processed' => $count,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync practitioner roles', ['error' => $e->getMessage()]);
            throw $e;
        }
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
                    $idHis = $service['id'] ?? null;
                    if (!$idHis) {
                        $this->logger->warning('HealthcareService without ID', ['data' => json_encode($service)]);
                        $errors++;
                        continue;
                    }

                    $hospitalService = $this->entityManager->getRepository(HospitalService::class)->findOneBy(['idHis' => $idHis]);
                    $isNew = false;

                    if (!$hospitalService) {
                        $hospitalService = new HospitalService();
                        $hospitalService->setIdHis($idHis);
                        $hospitalService->setIsActive(true);
                        $hospitalService->setColor('#3788d8');
                        $hospitalService->setMode(HospitalService::AMB_MODE);
                        $isNew = true;
                    }

                    $name = $service['name'] ?? 'Service ' . $idHis;
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
                        $code = 'CODE_' . $idHis;
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
                        'idHis' => $service['id'] ?? 'unknown',
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
            $date = date('Y-m-d');
            $response = $this->apiClient->get('/api/HInterop/GetSchedules?date=' . $date);

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->logger->warning('No schedules found or invalid response format');
                return;
            }

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Schedule') {
                    continue;
                }

                $schedule = $entry['resource'];
                $this->logger->info('New entry');

                try {
                    $idHis = $schedule['id'] ?? null;

                    if (!$idHis) {
                        $this->logger->warning('Schedule without ID', ['data' => json_encode($schedule)]);
                        continue;
                    }

                    if (isset($schedule['actor']) && is_array($schedule['actor'])) {
                        foreach ($schedule['actor'] as $actor) {
                            $practitionerId = $actor['identifier']['value'];

                            $doctor = $this->userRepository->findOneBy(['idHis' => $practitionerId]);
                            if (!$doctor) {
                                $this->logger->warning('Schedule without valid doctor reference', ['idHis' => $idHis]);
                                continue;
                            }

                            $date = new \DateTime('today');
                            if (isset($schedule['planningHorizon']['start'])) {
                                $date = new \DateTime($schedule['planningHorizon']['start']);
                            }

                            $doctorSchedule = $this->entityManager->getRepository(DoctorSchedule::class)
                                ->findOneBy([
                                    'doctor' => $doctor,
                                    'date' => $date,
                                    'idHis' => $idHis
                                ]);

                            if (!$doctorSchedule) {
                                $doctorSchedule = new DoctorSchedule();
                                $doctorSchedule->setDoctor($doctor);
                                $doctorSchedule->setDate($date);
                                $doctorSchedule->setIdHis($idHis);
                            }

                            $this->entityManager->persist($doctorSchedule);
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage(), [
                        'idHis' => $schedule['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->entityManager->flush();
            $this->logger->info('Schedules sync completed');
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync schedules', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncSlots(): void
    {
        $this->output->writeln('Synchronizing slots...');
        $this->logger->info('Fetching slots from FHIR API');
        $schedules = $this->doctorScheduleRepository->findAll();

        /** @var DoctorSchedule $schedule */
        foreach ($schedules as $schedule) {
            $response = $this->apiClient->get('/api/HInterop/GetSlots?schedule=' . $schedule->getIdHis());

            $stats = [
                'total'   => count($response['entry'] ?? []),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors'  => 0
            ];

            if (!isset($response['entry'])
                || !is_array($response['entry'])
            ) {
                $this->logger->error('Invalid FHIR bundle format: missing entries array');
            }

            foreach ($response['entry'] as $entry) {
                try {
                    // Skip non-Slot resources
                    if (!isset($entry['resource'])
                        || $entry['resource']['resourceType'] !== 'Slot'
                    ) {
                        $stats['skipped']++;
                        continue;
                    }

                    $slotResource = $entry['resource'];
                    $hisId        = $slotResource['id'] ?? null;

                    if (!$hisId) {
                        $this->logger->warning('Skipping Slot without ID');
                        $stats['skipped']++;
                        continue;
                    }

                    // Check if we already have this slot
                    $existingSlot
                        = $this->entityManager->getRepository(TimeSlot::class)
                        ->findOneBy(['idHis' => $hisId]);

                    // Extract basic slot information
                    $isBooked = ($slotResource['status'] ?? '') === 'busy';
                    $status   = $slotResource['status'] ?? null;

                    // Extract and parse time information
                    $startDateTime = null;
                    $endDateTime   = null;
                    $slotDate      = null;

                    if (isset($slotResource['start'])
                        && isset($slotResource['end'])
                    ) {
                        $startDateTime = new \DateTime($slotResource['start']);
                        $endDateTime   = new \DateTime($slotResource['end']);

                        $slotDate = clone $startDateTime;
                        $slotDate->setTime(0, 0, 0);
                    } else {
                        if (!$existingSlot) {
                            $this->logger->warning('Slot missing start/end times, cannot create new slot',
                                [
                                    'slotId' => $hisId
                                ]);
                            $stats['skipped']++;
                            continue;
                        }
                    }

                    // Get hospital service if available
                    $hospitalService = null;
                    if (isset($slotResource['serviceType'][0]['reference']['identifier']['value'])) {
                        $serviceHisId
                            = $slotResource['serviceType'][0]['reference']['identifier']['value'];
                        $hospitalService
                            = $this->entityManager->getRepository(HospitalService::class)
                            ->findOneBy(['idHis' => $serviceHisId]);
                    }

                    if ($existingSlot) {
                        // Update existing slot
                        $existingSlot->setIsBooked($isBooked);

                        if ($status) {
                            $existingSlot->setStatus($status);
                        }

                        if ($startDateTime && $endDateTime) {
                            // Create time-only objects
                            $startTime = new \DateTime();
                            $startTime->setTime(
                                (int)$startDateTime->format('H'),
                                (int)$startDateTime->format('i'),
                                (int)$startDateTime->format('s')
                            );

                            $endTime = new \DateTime();
                            $endTime->setTime(
                                (int)$endDateTime->format('H'),
                                (int)$endDateTime->format('i'),
                                (int)$endDateTime->format('s')
                            );

                            $existingSlot->setStartTime($startTime);
                            $existingSlot->setEndTime($endTime);
                        }

                        if ($hospitalService) {
                            $existingSlot->setHospitalService($hospitalService);
                        }

                        $this->entityManager->persist($existingSlot);
                        $stats['updated']++;
                    } else {
                        // We need to create a new slot - first find or create the schedule
                        $scheduleRef
                            = $slotResource['schedule']['identifier']['value']
                            ?? null;

                        if (!$scheduleRef) {
                            $this->logger->warning('Slot missing schedule reference, cannot create new slot',
                                [
                                    'slotId' => $hisId
                                ]);
                            $stats['skipped']++;
                            continue;
                        }

                        // Find existing schedule
                        $schedule
                            = $this->entityManager->getRepository(DoctorSchedule::class)
                            ->findOneBy(['idHis' => $scheduleRef]);

                        if (!$schedule) {
                            // Need to create a new schedule - extract doctor information from reference
                            // Assuming schedule ID has format like "2__422900000000080__26_03_2025__10:00__11__30"
                            $identifierParts = explode('__', $scheduleRef);

                            if (count($identifierParts) < 3) {
                                $this->logger->warning('Cannot parse doctor ID from schedule reference',
                                    [
                                        'scheduleRef' => $scheduleRef,
                                        'slotId'      => $hisId
                                    ]);
                                $stats['skipped']++;
                                continue;
                            }

                            $doctorHisId = $identifierParts[1] ?? null;

                            $doctor
                                = $this->entityManager->getRepository(Doctor::class)
                                ->findOneBy(['idHis' => $doctorHisId]);

                            if (!$doctor) {
                                $this->logger->warning('Doctor not found for this slot',
                                    [
                                        'doctorHisId' => $doctorHisId,
                                        'slotId'      => $hisId
                                    ]);
                                $stats['skipped']++;
                                continue;
                            }

                            // Create the schedule
                            $schedule = new DoctorSchedule();
                            $schedule->setIdHis($scheduleRef);
                            $schedule->setDoctor($doctor);
                            $schedule->setDate($slotDate);

                            $this->entityManager->persist($schedule);
                        }

                        // Now create the time slot
                        $newSlot = new TimeSlot();
                        $newSlot->setIdHis($hisId);
                        $newSlot->setSchedule($schedule);
                        $newSlot->setIsBooked($isBooked);

                        if ($status) {
                            $newSlot->setStatus($status);
                        }

                        // Create time-only objects for the slot
                        $startTime = new \DateTime();
                        $startTime->setTime(
                            (int)$startDateTime->format('H'),
                            (int)$startDateTime->format('i'),
                            (int)$startDateTime->format('s')
                        );

                        $endTime = new \DateTime();
                        $endTime->setTime(
                            (int)$endDateTime->format('H'),
                            (int)$endDateTime->format('i'),
                            (int)$endDateTime->format('s')
                        );

                        $newSlot->setStartTime($startTime);
                        $newSlot->setEndTime($endTime);

                        if ($hospitalService) {
                            $newSlot->setHospitalService($hospitalService);
                        }

                        $this->entityManager->persist($newSlot);
                        $stats['created']++;
                    }

                    $this->entityManager->flush();

                } catch (\Exception $e) {
                    $this->logger->error('Error processing FHIR Slot', [
                        'id'    => $hisId ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $stats['errors']++;
                }
            }
        }
    }

    private function syncAppointments(): void
    {
        $this->output->writeln('Synchronizing appointments...');
        $this->logger->info('Fetching appointments from FHIR API');
        $response = $this->apiClient->get('/api/HInterop/GetAppointments?status=pending');

        $stats = [
            'total' => count($response['entry'] ?? []),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        if (!isset($response['entry']) || !is_array($response['entry'])) {
            $this->logger->error('Invalid FHIR bundle format: missing entries array');
            return;
        }

        foreach ($response['entry'] as $entry) {
            try {
                // Skip non-Appointment resources
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Appointment') {
                    $stats['skipped']++;
                    continue;
                }

                $appointmentResource = $entry['resource'];
                $hisId = $appointmentResource['id'] ?? null;

                if (!$hisId) {
                    $this->logger->warning('Skipping Appointment without ID', ['resource' => json_encode($appointmentResource)]);
                    $stats['skipped']++;
                    continue;
                }

                // Check if we already have this appointment
                $existingAppointment = $this->entityManager->getRepository(Appointment::class)->findOneBy(['idHis' => $hisId]);

                // Extract appointment information
                $status = $appointmentResource['status'] ?? 'pending';
                $isActive = ($status === 'booked' || $status === 'fulfilled' || $status === 'pending');

                // Extract patient, doctor and service information
                $patientHisId = null;
                $doctorHisId = null;
                $serviceHisId = null;

                // Extract participant information
                if (isset($appointmentResource['participant']) && is_array($appointmentResource['participant'])) {
                    foreach ($appointmentResource['participant'] as $participant) {
                        if (isset($participant['actor']['identifier']['value'])) {
                            if (isset($participant['type'][0]['coding'][0]['code']) && $participant['type'][0]['coding'][0]['code'] === 'practitioner') {
                                $doctorHisId = $participant['actor']['identifier']['value'];
                            }
                        }
                    }
                }

                // Try to get patient information from subject if not found in participants
                if (isset($appointmentResource['subject']['identifier']['value'])) {
                    $patientHisId = $appointmentResource['subject']['identifier']['value'];
                }

                // Get service information
                if (isset($appointmentResource['serviceType'][0]['reference']['identifier']['value'])) {
                    $serviceHisId = $appointmentResource['serviceType'][0]['reference']['identifier']['value'];
                }

                // Check if we have the required information
                if (!$patientHisId || !$doctorHisId || !$serviceHisId) {
                    $this->logger->warning('Appointment missing required information', [
                        'appointmentId' => $hisId,
                        'patientHisId' => $patientHisId,
                        'doctorHisId' => $doctorHisId,
                        'serviceHisId' => $serviceHisId
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                // Find entities
                $patient = $this->entityManager->getRepository(Patient::class)->findOneBy(['idHis' => $patientHisId]);
                $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['idHis' => $doctorHisId]);
                $hospitalService = $this->entityManager->getRepository(HospitalService::class)->findOneBy(['idHis' => $serviceHisId]);

                // Create patient if not found (simplified version - you may want to expand this)
                if (!$patient) {
                    $patient = new Patient();
                    $patient->setIdHis($patientHisId);
                    $patient->setEmail('patient_' . $patientHisId . '@example.com');
                    $patient->setFirstName('Patient');
                    $patient->setLastName($patientHisId);
                    $patient->setCnp(str_pad($patientHisId, 13, '0', STR_PAD_LEFT));
                    $patient->setPhone('0000000000');
                    $patient->setPassword(password_hash(uniqid('', true), PASSWORD_BCRYPT));
                    $patient->setRoles([Patient::BASE_ROLE]);

                    $this->entityManager->persist($patient);
                    $this->entityManager->flush();
                }

                // Skip if doctor or service not found
                if (!$doctor || !$hospitalService) {
                    $this->logger->warning('Doctor or hospital service not found for appointment', [
                        'appointmentId' => $hisId,
                        'doctorFound' => (bool)$doctor,
                        'serviceFound' => (bool)$hospitalService
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                // Find medical specialty through hospital service
                $medicalSpecialty = $hospitalService->getMedicalSpecialty();
                if (!$medicalSpecialty) {
                    $this->logger->warning('Hospital service has no medical specialty', [
                        'appointmentId' => $hisId,
                        'serviceId' => $hospitalService->getId()
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                // Extract time slot information
                $slotIdentifier = null;
                if (isset($appointmentResource['slot'][0]['identifier']['value'])) {
                    $slotIdentifier = $appointmentResource['slot'][0]['identifier']['value'];
                }



                // Extract date/time information
                $startDateTime = null;
                $endDateTime = null;
                if (isset($appointmentResource['start']) && isset($appointmentResource['end'])) {
                    $startDateTime = new \DateTime($appointmentResource['start']);
                    $endDateTime = new \DateTime($appointmentResource['end']);
                } else {
                    $this->logger->warning('Appointment missing start/end times', [
                        'appointmentId' => $hisId
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                // Find or create time slot
                $timeSlot = null;
                if ($slotIdentifier) {
                    // Try to find existing slot
                    $timeSlot = $this->entityManager->getRepository(TimeSlot::class)->findOneBy(['idHis' => $slotIdentifier]);
                }

                if (!$timeSlot) {
                    // We need to find or create the schedule and time slot
                    $date = clone $startDateTime;
                    $date->setTime(0, 0, 0);

                    // Find or create schedule
                    $schedule = $this->entityManager->getRepository(DoctorSchedule::class)->findOneBy([
                        'doctor' => $doctor,
                        'date' => $date
                    ]);

                    if (!$schedule) {
                        $schedule = new DoctorSchedule();
                        $schedule->setDoctor($doctor);
                        $schedule->setDate($date);
                        $schedule->setIdHis('schedule_' . $hisId);
                        $this->entityManager->persist($schedule);
                        $this->entityManager->flush();
                    }

                    // Create new time slot
                    $startTime = new \DateTime();
                    $startTime->setTime(
                        (int)$startDateTime->format('H'),
                        (int)$startDateTime->format('i'),
                        (int)$startDateTime->format('s')
                    );

                    $endTime = new \DateTime();
                    $endTime->setTime(
                        (int)$endDateTime->format('H'),
                        (int)$endDateTime->format('i'),
                        (int)$endDateTime->format('s')
                    );

                    $timeSlot = new TimeSlot();
                    $timeSlot->setSchedule($schedule);
                    $timeSlot->setStartTime($startTime);
                    $timeSlot->setEndTime($endTime);
                    $timeSlot->setIsBooked(true); // Since we have an appointment, the slot is booked
                    $timeSlot->setHospitalService($hospitalService);
                    $timeSlot->setIdHis($slotIdentifier ?? 'slot_' . $hisId);

                    $this->entityManager->persist($timeSlot);
                    $this->entityManager->flush();
                }

                if ($existingAppointment) {
                    // Update existing appointment
                    $existingAppointment->setPatient($patient);
                    $existingAppointment->setDoctor($doctor);
                    $existingAppointment->setMedicalSpecialty($medicalSpecialty);
                    $existingAppointment->setHospitalService($hospitalService);
                    $existingAppointment->setTimeSlot($timeSlot);
                    $existingAppointment->setIsActive($isActive);

                    $this->entityManager->persist($existingAppointment);
                    $stats['updated']++;
                } else {
                    // Create new appointment
                    $newAppointment = new Appointment();
                    $newAppointment->setIdHis($hisId);
                    $newAppointment->setPatient($patient);
                    $newAppointment->setDoctor($doctor);
                    $newAppointment->setMedicalSpecialty($medicalSpecialty);
                    $newAppointment->setHospitalService($hospitalService);
                    $newAppointment->setTimeSlot($timeSlot);
                    $newAppointment->setIsActive($isActive);

                    $this->entityManager->persist($newAppointment);
                    $stats['created']++;
                }

                $this->entityManager->flush();

            } catch (\Exception $e) {
                $this->logger->error('Error processing FHIR Appointment', [
                    'id' => $hisId ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $stats['errors']++;
            }
        }
    }

    private function syncMedicalSpecialties(): void
    {
        $this->output->writeln('Synchronizing medical specialties...');
        $this->logger->info('Processing medical specialties from FHIR API');

        try {
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

                        if (isset($specialty['coding'][0]['code'])) {
                            $code = $specialty['coding'][0]['code'];
                            $name = $specialty['coding'][0]['code'];
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