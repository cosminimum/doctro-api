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
use App\Infrastructure\Repository\HospitalServiceRepository;
use App\Infrastructure\Repository\MedicalSpecialtyRepository;
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
    private MedicalSpecialtyRepository $medicalSpecialtyRepository;
    private HospitalServiceRepository $hospitalServiceRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        FhirApiClient $apiClient,
        LoggerInterface $logger,
        UserRepository $userRepository,
        DoctorScheduleRepository $doctorScheduleRepository,
        DoctorRepositoryInterface $doctorRepository,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        HospitalServiceRepository $hospitalServiceRepository,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->apiClient = $apiClient;
        $this->userRepository = $userRepository;
        $this->doctorScheduleRepository = $doctorScheduleRepository;
        $this->doctorRepository = $doctorRepository;
        $this->medicalSpecialtyRepository = $medicalSpecialtyRepository;
        $this->hospitalServiceRepository = $hospitalServiceRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $startTime = microtime(true);

        $this->output->writeln('Starting FHIR resources synchronization');
        $this->output->writeln('Starting FHIR resources synchronization...');

        try {
            $this->syncPatients();
            $this->syncPractitioners();
            $this->syncHealthcareServices();
            $this->syncPractitionerRoles();
            $this->syncSchedules();
            $this->syncSlots();
            $this->syncAppointments();

            $this->entityManager->flush();

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->output->writeln('FHIR resources synchronization completed');
            $this->output->writeln("Synchronization completed successfully in {$executionTime} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->output->writeln('Error during FHIR synchronization');

            $this->output->writeln('Error during synchronization: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    private function syncPatients(): void
    {
        $this->output->writeln('Synchronizing patients...');
        $this->output->writeln('Fetching patients from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPatients?active=true');
            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('No patients found or invalid response format');
                return;
            }

            $count = 0;
            $updated = 0;
            $errors = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Patient') {
                    continue;
                }

                $patient = $entry['resource'];

                try {
                    $idHis = $patient['id'] ?? null;
                    if (!$idHis) {
                        $this->output->writeln('Patient without ID');
                        $errors++;
                        continue;
                    }

                    $existingPatient = $this->entityManager->getRepository(Patient::class)->findOneBy(['idHis' => $idHis]);
                    $isNew = false;

                    if (!$existingPatient) {
                        $existingPatient = new Patient();
                        $existingPatient->setIdHis($idHis);
                        $existingPatient->setRoles([Patient::BASE_ROLE]);
                        $existingPatient->setPassword(password_hash(uniqid('', true), PASSWORD_BCRYPT));
                        $isNew = true;
                    }

                    $email = '';
                    if (isset($patient['telecom']) && is_array($patient['telecom'])) {
                        foreach ($patient['telecom'] as $telecom) {
                            if ($telecom['system'] === 'email') {
                                $email = $telecom['value'];
                                break;
                            }
                        }
                    }

                    if (empty($email)) {
                        $email = 'patient_' . $idHis . '@example.com';
                    }

                    $phone = '';
                    if (isset($patient['telecom']) && is_array($patient['telecom'])) {
                        foreach ($patient['telecom'] as $telecom) {
                            if ($telecom['system'] === 'phone') {
                                $phone = $telecom['value'];
                                break;
                            }
                        }
                    }

                    $firstName = '';
                    $lastName = '';
                    if (isset($patient['name'][0])) {
                        $name = $patient['name'][0];
                        if (isset($name['given']) && is_array($name['given'])) {
                            $firstName = implode(' ', $name['given']);
                        } else {
                            $firstName = $name['given'] ?? '';
                        }
                        $lastName = $name['family'] ?? '';
                    }

                    $cnp = '';
                    if (isset($patient['identifier']) && is_array($patient['identifier'])) {
                        foreach ($patient['identifier'] as $identifier) {
                            if (isset($identifier['system']) && $identifier['system'] === 'http://snomed.info/sct') {
                                $cnp = $identifier['value'];
                                break;
                            }
                        }
                    }

                    if (empty($cnp)) {
                        $cnp = str_pad((string) $idHis, 13, '0', STR_PAD_LEFT);
                    }

                    $existingPatient->setEmail($email);
                    $existingPatient->setFirstName($firstName);
                    $existingPatient->setLastName($lastName);
                    $existingPatient->setCnp($cnp);
                    $existingPatient->setPhone($phone);
                    $existingPatient->setIsActive(true);

                    $this->entityManager->persist($existingPatient);
                    $this->entityManager->flush();

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->output->writeln('Error processing patient: ' . $e->getMessage());
                    $errors++;
                }
            }

            $this->output->writeln("Patients: {$count} new, {$updated} updated, {$errors} errors");
            $this->output->writeln('Patients sync completed');
        } catch (\Exception $e) {
            $this->output->writeln('Failed to sync patients: ' . $e->getMessage());
            throw $e;
        }
    }

    private function syncPractitioners(): void
    {
        $this->output->writeln('Synchronizing practitioners...');
        $this->output->writeln('Fetching practitioners from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitioners?active=true&role=app_doctor');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('No practitioners found or invalid response format');
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
                        $this->output->writeln('Practitioner without ID');
                        $errors++;
                        continue;
                    }

                    $doctor = $this->entityManager->getRepository(Doctor::class)->findOneBy(['idHis' => $idHis]);
                    $isNew = false;

                    if (!$doctor) {
                        $doctor = new Doctor();
                        $doctor->setIdHis($idHis);
                        $doctor->setRoles([Doctor::BASE_ROLE]);
                        $doctor->setPassword(password_hash('x', PASSWORD_BCRYPT));
                        $isNew = true;
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

                    $email = strtolower($firstName) . '.' . strtolower($lastName);

                    $user = $this->userRepository->findOneBy(['email' => $email]);
                    if ($user) {
                        $this->output->writeln("User found {$firstName} {$lastName}. Skip");
                        continue;
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
                    $this->entityManager->flush();
                    try {
                        $this->entityManager->flush();
                    } catch (\Exception $e) {
                        $this->output->writeln("Failed to update practitioner {$firstName} {$lastName}");
                        $errors++;
                        continue;
                    }

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->output->writeln("Error processing practitioner {$e->getMessage()}");
                    $errors++;
                }
            }

            $this->output->writeln("Practitioners: {$count} new, {$updated} updated, {$errors} errors");
            $this->output->writeln('Practitioners sync completed');
        } catch (\Exception $e) {
            $this->output->writeln("Failed to sync practitioners: {$e->getMessage()}");
            throw $e;
        }
    }

    private function syncPractitionerRoles(): void
    {
        $this->output->writeln('Synchronizing practitioner roles...');
        $this->output->writeln('Fetching practitioner roles from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles?active=true&role=app_doctor');

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('No practitioner roles found or invalid response format');
                return;
            }

            $count = 0;
            $errors = 0;
            $specialtiesCreated = 0;
            $servicesCreated = 0;

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'PractitionerRole') {
                    continue;
                }

                $role = $entry['resource'];

                try {
                    if (!isset($role['practitioner']['identifier'])) {
                        $this->output->writeln('PractitionerRole without practitioner reference');
                        $errors++;
                        continue;
                    }

                    $practitionerId = $role['practitioner']['identifier']['value'];

                    if (!$practitionerId) {
                        $this->output->writeln('Could not extract practitioner ID from reference');
                        $errors++;
                        continue;
                    }

                    $doctor = $this->userRepository->findOneBy(['idHis' => $practitionerId]);

                    if (!$doctor) {
                        $this->output->writeln('Doctor not found for practitioner role');
                        $errors++;
                        continue;
                    }

                    // Associate specialties
                    if (isset($role['specialty']) && is_array($role['specialty'])) {
                        foreach ($role['specialty'] as $specialty) {
                            if (isset($specialty['coding'][0]['code'])) {
                                $specialtyCode = $specialty['coding'][0]['code'];
                                $specialtyName = $specialty['coding'][0]['display'] ?? $specialtyCode;

                                $medicalSpecialty = $this->medicalSpecialtyRepository->findOneBy(['code' => $specialtyCode]);

                                // Create specialty if it doesn't exist
                                if (!$medicalSpecialty) {
                                    $medicalSpecialty = new MedicalSpecialty();
                                    $medicalSpecialty->setCode($specialtyCode);
                                    $medicalSpecialty->setName($specialtyName);
                                    $medicalSpecialty->setIsActive(true);
                                    $this->entityManager->persist($medicalSpecialty);
                                    $this->entityManager->flush();
                                    $specialtiesCreated++;
                                    $this->output->writeln("Created new specialty: {$specialtyName} with code {$specialtyCode}");
                                }

                                if (!$doctor->getMedicalSpecialties()->contains($medicalSpecialty)) {
                                    $doctor->addMedicalSpecialty($medicalSpecialty);
                                    $this->output->writeln("Added specialty {$specialtyName} to doctor {$doctor->getFirstName()} {$doctor->getLastName()}");
                                }
                            }
                        }
                    }

                    // Associate services
                    if (isset($role['healthcareService']) && is_array($role['healthcareService'])) {
                        foreach ($role['healthcareService'] as $service) {
                            if (isset($service['identifier']['value'])) {
                                $serviceCode = $service['identifier']['value'];

                                /** @var HospitalService $hospitalService */
                                $hospitalService = $this->hospitalServiceRepository->findOneBy(['idHis' => $serviceCode]);

                                // Create service if it doesn't exist
                                if (!$hospitalService) {
                                    $this->output->writeln("Service not found for service {$serviceCode}");
                                    continue;
                                }

                                if (!$doctor->getHospitalServices()->contains($hospitalService)) {
                                    $doctor->addHospitalService($hospitalService);
                                    $this->output->writeln("Added service {$hospitalService->getName()} to doctor {$doctor->getFirstName()} {$doctor->getLastName()}");
                                }
                            }
                        }
                    }

                    $this->entityManager->persist($doctor);
                    $this->entityManager->flush();
                    $count++;

                } catch (\Exception $e) {
                    $this->output->writeln('Error processing practitioner role: ' . $e->getMessage());
                    $errors++;
                }
            }

            // Final flush to save any remaining changes
            $this->entityManager->flush();

            $this->output->writeln("Practitioner roles: {$count} processed, {$specialtiesCreated} specialties created, {$servicesCreated} services created, {$errors} errors");
            $this->output->writeln('Practitioner roles sync completed');
        } catch (\Exception $e) {
            $this->output->writeln('Failed to sync practitioner roles: ' . $e->getMessage());
            throw $e;
        }
    }

    private function syncHealthcareServices(): void
    {
        $this->output->writeln('Synchronizing healthcare services...');
        $this->output->writeln('Fetching healthcare services from FHIR API');

        try {
            $response = $this->apiClient->get('/api/HInterop/GetHealthcareServices?category=APPSERV');
            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('No healthcare services found or invalid response format');
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
                        $this->output->writeln('HealthcareService without ID');
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
                    } else {
                        // Default description if none provided
                        $description = $name;
                    }

                    $code = '';
                    // Extract code from identifier or type
                    if (isset($service['identifier']) && is_array($service['identifier'])) {
                        foreach ($service['identifier'] as $identifier) {
                            if (isset($identifier['value'])) {
                                $code = $identifier['value'];
                                break;
                            }
                        }
                    } elseif (isset($service['type']) && is_array($service['type'])) {
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

                    // Find medical specialty from category if available
                    $specialtyId = null;
                    $categoryCode = null;
                    if (isset($service['category']) && is_array($service['category'])) {
                        foreach ($service['category'] as $category) {
                            if (isset($category['coding'][0]['code'])) {
                                $categoryCode = $category['coding'][0]['code'];
                                break;
                            }
                        }
                    }

                    if ($categoryCode) {
                        $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)
                            ->findOneBy(['code' => $categoryCode]);

                        if (!$medicalSpecialty) {
                            // Create a new specialty if not found
                            $medicalSpecialty = new MedicalSpecialty();
                            $medicalSpecialty->setCode($categoryCode);
                            $medicalSpecialty->setName($service['category'][0]['text'] ?? 'Specialty ' . $categoryCode);
                            $medicalSpecialty->setIsActive(true);
                            $this->entityManager->persist($medicalSpecialty);
                            $this->entityManager->flush();
                        }

                        $specialtyId = $medicalSpecialty->getId();
                    } else {
                        // Fallback to first specialty if no category found
                        $medicalSpecialty = $this->entityManager->getRepository(MedicalSpecialty::class)
                            ->findOneBy([], ['id' => 'ASC']);

                        if ($medicalSpecialty) {
                            $specialtyId = $medicalSpecialty->getId();
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
                    $this->entityManager->flush();

                    if ($isNew) {
                        $count++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->output->writeln('Error processing healthcare service: ' . $e->getMessage());
                    $errors++;
                }
            }

            $this->entityManager->flush();
            $this->output->writeln("Healthcare services: {$count} new, {$updated} updated, {$errors} errors");
            $this->output->writeln('Healthcare services sync completed');
        } catch (\Exception $e) {
            $this->output->writeln('Failed to sync healthcare services: ' . $e->getMessage());
            throw $e;
        }
    }

    private function syncSchedules(): void
    {
        $this->output->writeln('Synchronizing schedules...');
        $this->output->writeln('Fetching schedules from FHIR API');

        try {
            $date = date('Y-m-d');
            $response = $this->apiClient->get('/api/HInterop/GetSchedules?date=' . $date);

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('No schedules found or invalid response format');
                return;
            }

            foreach ($response['entry'] as $entry) {
                if (!isset($entry['resource']) || $entry['resource']['resourceType'] !== 'Schedule') {
                    continue;
                }

                $schedule = $entry['resource'];
                $this->output->writeln('New entry');

                try {
                    $idHis = $schedule['id'] ?? null;

                    if (!$idHis) {
                        $this->output->writeln('Schedule without ID');
                        continue;
                    }

                    if (isset($schedule['actor']) && is_array($schedule['actor'])) {
                        foreach ($schedule['actor'] as $actor) {
                            $practitionerId = $actor['identifier']['value'];

                            $doctor = $this->userRepository->findOneBy(['idHis' => $practitionerId]);
                            if (!$doctor) {
                                $this->output->writeln('Schedule without valid doctor reference');
                                continue;
                            }

                            $date = new \DateTime('today');
                            if (isset($schedule['planningHorizon']['start'])) {
                                $date = new \DateTime($schedule['planningHorizon']['start']);
                            }

                            $doctorSchedule = $this->doctorScheduleRepository->findOneBy([
                                'idHis' => $idHis,
                            ]);

                            if (!$doctorSchedule) {
                                $doctorSchedule = new DoctorSchedule();
                                $doctorSchedule->setDoctor($doctor);
                                $doctorSchedule->setDate($date);
                                $doctorSchedule->setIdHis($idHis);
                            }

                            $this->entityManager->persist($doctorSchedule);
                            $this->entityManager->flush();
                        }
                    }

                } catch (\Exception $e) {
                    $this->output->writeln($e->getMessage());
                }
            }

            $this->output->writeln('Schedules sync completed');
        } catch (\Exception $e) {
            $this->output->writeln('Failed to sync schedules');
            throw $e;
        }
    }

    private function syncSlots(): void
    {
        $this->output->writeln('Synchronizing slots...');
        $this->output->writeln('Fetching slots from FHIR API');
        $schedules = $this->doctorScheduleRepository->findAll();

        /** @var DoctorSchedule $schedule */
        foreach ($schedules as $schedule) {
            if ($schedule->getIdHis() === null) {
                continue;
            }
            $this->output->writeln('Fetching slots from FHIR API for ' . $schedule->getIdHis());
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
                $this->output->writeln('Invalid FHIR bundle format: missing entries array');
                continue;
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
                        $this->output->writeln('Skipping Slot without ID');
                        $stats['skipped']++;
                        continue;
                    }

                    // Check if we already have this slot
                    $existingSlot
                        = $this->entityManager->getRepository(TimeSlot::class)
                        ->findOneBy(['idHis' => $hisId]);

                    $this->output->writeln("Slot ID: {$hisId} - Status: {$slotResource['status']} - {$slotResource['start']} | {$slotResource['end']}");
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
                            $this->output->writeln('Slot missing start/end times, cannot create new slot',
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
                        $this->entityManager->flush();
                        $stats['updated']++;
                    } else {
                        // We need to create a new slot - first find or create the schedule
                        $scheduleRef
                            = $slotResource['schedule']['identifier']['value']
                            ?? null;

                        if (!$scheduleRef) {
                            $this->output->writeln('Slot missing schedule reference, cannot create new slot',
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
                                $this->output->writeln('Cannot parse doctor ID from schedule reference',
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
                                $this->output->writeln('Doctor not found for this slot',
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
                            $this->entityManager->flush();
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
                        $this->entityManager->flush();
                        $stats['created']++;
                    }

                    $this->entityManager->flush();

                } catch (\Exception $e) {
                    $this->output->writeln('Error processing FHIR Slot');
                    $stats['errors']++;
                }
            }
        }
    }

    private function syncAppointments(): void
    {
        $this->output->writeln('Synchronizing appointments...');
        $this->output->writeln('Fetching appointments from FHIR API');
        $response = $this->apiClient->get('/api/HInterop/GetAppointments?status=pending');

        $stats = [
            'total' => count($response['entry'] ?? []),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        if (!isset($response['entry']) || !is_array($response['entry'])) {
            $this->output->writeln('Invalid FHIR bundle format: missing entries array');
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
                    $this->output->writeln('Skipping Appointment without ID');
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
                    $this->output->writeln('Appointment missing required information');
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
                    $this->output->writeln('Doctor or hospital service not found for appointment');
                    $stats['skipped']++;
                    continue;
                }

                // Find medical specialty through hospital service
                $medicalSpecialty = $hospitalService->getMedicalSpecialty();
                if (!$medicalSpecialty) {
                    $this->output->writeln('Hospital service has no medical specialty');
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
                    $this->output->writeln('Appointment missing start/end times');
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
                    $this->entityManager->flush();
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
                    $this->entityManager->flush();
                    $stats['created']++;
                }

                $this->entityManager->flush();

            } catch (\Exception $e) {
                $this->output->writeln('Error processing FHIR Appointment');
                $stats['errors']++;
            }
        }
    }
}