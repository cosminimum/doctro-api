<?php

namespace App\Command;

use App\Application\Repository\AppointmentRepositoryInterface;
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
use App\Infrastructure\Repository\TimeSlotRepository;
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
    private AppointmentRepositoryInterface $appointmentRepository;
    private TimeSlotRepository $timeSlotRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        FhirApiClient $apiClient,
        LoggerInterface $logger,
        UserRepository $userRepository,
        DoctorScheduleRepository $doctorScheduleRepository,
        DoctorRepositoryInterface $doctorRepository,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
        HospitalServiceRepository $hospitalServiceRepository,
        AppointmentRepositoryInterface $appointmentRepository,
        TimeSlotRepository $timeSlotRepository,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->apiClient = $apiClient;
        $this->userRepository = $userRepository;
        $this->doctorScheduleRepository = $doctorScheduleRepository;
        $this->doctorRepository = $doctorRepository;
        $this->medicalSpecialtyRepository = $medicalSpecialtyRepository;
        $this->hospitalServiceRepository = $hospitalServiceRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->timeSlotRepository = $timeSlotRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $startTime = microtime(true);

        $this->output->writeln('Starting FHIR resources synchronization');
        $this->output->writeln('Starting FHIR resources synchronization...');

        try {
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

                    $hospitalService = $this->hospitalServiceRepository->findOneBy(['idHis' => $idHis]);
                    $isNew = false;

                    if (!$hospitalService) {
                        $hospitalService = new HospitalService();
                        $hospitalService->setIdHis($idHis);
                        $hospitalService->setIsActive(true);
                        $hospitalService->setColor('#3788d8');
                        $hospitalService->setMode(HospitalService::AMB_MODE);
                        $isNew = true;
                    }

                    $name = $service['name'];
                    $description = '';

                    $code = $idHis;

                    $duration = 30; // Default duration
                    $price = '0'; // Default price

                    // Default specialty
                    $medicalSpecialty = $this->medicalSpecialtyRepository->findAll()[0];

                    $hospitalService->setName($name);
                    $hospitalService->setDescription($description);
                    $hospitalService->setCode($code);
                    $hospitalService->setDuration((string) $duration);
                    $hospitalService->setPrice($price);
                    $hospitalService->setMedicalSpecialty($medicalSpecialty);

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
//            $response = '{"resourceType":"Bundle","type":"collection","entry":[{"fullUrl":"9497b700-5afb-4c76-9c4a-5a7e7c495707","resource":{"resourceType":"Slot","id":"1","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__07:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T07:00:00+00:00","end":"2025-03-31T07:30:00+00:00"}},{"fullUrl":"5f030f83-5eda-4a25-b083-d6595f538f9c","resource":{"resourceType":"Slot","id":"2","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__07:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T07:30:00+00:00","end":"2025-03-31T08:00:00+00:00"}},{"fullUrl":"2d782d6e-fbea-45d0-8dfd-5cfe7d567103","resource":{"resourceType":"Slot","id":"3","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__08:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T08:00:00+00:00","end":"2025-03-31T08:30:00+00:00"}},{"fullUrl":"d859f91e-2533-4e66-b2a2-c728b7ff0ba9","resource":{"resourceType":"Slot","id":"4","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__08:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T08:30:00+00:00","end":"2025-03-31T09:00:00+00:00"}},{"fullUrl":"457b75a5-0597-41da-bba3-d5e6bbed3524","resource":{"resourceType":"Slot","id":"5","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__09:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T09:00:00+00:00","end":"2025-03-31T09:30:00+00:00"}},{"fullUrl":"69e524e3-fcce-4fb8-87c1-e79d4f332d9d","resource":{"resourceType":"Slot","id":"6","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__09:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T09:30:00+00:00","end":"2025-03-31T10:00:00+00:00"}},{"fullUrl":"05b25e8d-2109-41db-b261-79582d4cb983","resource":{"resourceType":"Slot","id":"7","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__10:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T10:00:00+00:00","end":"2025-03-31T10:30:00+00:00"}},{"fullUrl":"cfa02d9a-79a0-4839-89fa-0158d5317bff","resource":{"resourceType":"Slot","id":"8","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__10:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T10:30:00+00:00","end":"2025-03-31T11:00:00+00:00"}},{"fullUrl":"b94ea1ef-44fc-4687-b5c2-a1da0137352e","resource":{"resourceType":"Slot","id":"9","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__11:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T11:00:00+00:00","end":"2025-03-31T11:30:00+00:00"}},{"fullUrl":"4a5e4ad2-34cc-4b2a-847f-12733c1d8d4e","resource":{"resourceType":"Slot","id":"10","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__11:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T11:30:00+00:00","end":"2025-03-31T12:00:00+00:00"}},{"fullUrl":"b8351e7c-88e0-4f9b-ab49-7c3d0a3ddafe","resource":{"resourceType":"Slot","id":"11","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__12:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T12:00:00+00:00","end":"2025-03-31T12:30:00+00:00"}},{"fullUrl":"fc0c731a-cc12-4673-8451-8ef43e70aece","resource":{"resourceType":"Slot","id":"12","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__12:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T12:30:00+00:00","end":"2025-03-31T13:00:00+00:00"}},{"fullUrl":"87ddf50b-c3b1-4cdc-aa96-eef4ebf0802c","resource":{"resourceType":"Slot","id":"13","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__13:00__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T13:00:00+00:00","end":"2025-03-31T13:30:00+00:00"}},{"fullUrl":"098f2508-086a-46e6-93fc-72aa0a695499","resource":{"resourceType":"Slot","id":"14","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MR"}]},"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025__13:30__2__30"}],"serviceCategory":[{"coding":[{"code":"APPSERV"}],"text":"Servicii programari"}],"serviceType":[{"reference":{"type":"HealthcareService","identifier":{"system":"http://snomed.info/sct","value":"79831959-E760-435A-AC9A-775B27F8ACF2"}}}],"appointmentType":[{"coding":[{"code":"CHECKUP"}],"text":"CHECKUP"}],"schedule":{"type":"Schedule","identifier":{"system":"http://snomed.info/sct","value":"1__422900000000504__31_03_2025"}},"status":"free","start":"2025-03-31T13:30:00+00:00","end":"2025-03-31T14:00:00+00:00"}}]}';
//            $response = json_decode($response,true);

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
                    $hisId = $slotResource['identifier'][0]['value'] ?? null;

                    if (!$hisId) {
                        $this->output->writeln('Skipping Slot without ID');
                        $stats['skipped']++;
                        continue;
                    }

                    // Check if we already have this slot
                    $existingSlot = $this->entityManager->getRepository(TimeSlot::class)->findOneBy(['idHis' => $hisId]);

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
                            $this->output->writeln('Slot missing start/end times, cannot create new slot');
                            $stats['skipped']++;
                            continue;
                        }
                    }

                    // Get hospital service if available
                    $hospitalService = null;
                    if (isset($slotResource['serviceType'][0]['reference']['identifier']['value'])) {
                        $serviceHisId = $slotResource['serviceType'][0]['reference']['identifier']['value'];
                        $hospitalService = $this->hospitalServiceRepository->findOneBy(['id' => $serviceHisId]);
                        if (!$hospitalService) {
                            $this->output->writeln("Service not found: $serviceHisId");
                        }
                    } else {
                        $this->output->writeln("Slot missing serviceType, cannot create new slot for ID: {$hisId}");
                    }

                    if ($existingSlot) {
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
                        $schedule = $this->doctorScheduleRepository->findOneBy(['idHis' => $schedule->getIdHis()]);
                        $newSlot = new TimeSlot();
                        $newSlot->setIdHis($hisId);
                        $newSlot->setSchedule($schedule);
                        $newSlot->setIsBooked($isBooked);

                        if ($status) {
                            $newSlot->setStatus($status);
                        }

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

        $appointmentDates = [
            new \DateTime(),
            (new \DateTime())->modify('+1 day'),
            (new \DateTime())->modify('+2 day'),
        ];

        foreach ($appointmentDates as $appointmentDate) {
            $response = $this->apiClient->get('/api/HInterop/GetAppointments?date=' . $appointmentDate->format('Y-m-d'));

            $stats = [
                'total' => count($response['entry'] ?? []),
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ];

            if (!isset($response['entry']) || !is_array($response['entry'])) {
                $this->output->writeln('Invalid FHIR bundle format: missing entries array');
                continue;
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
                    $existingAppointment = $this->appointmentRepository->findOneBy(['idHis' => $hisId]);

                    // Extract appointment information
                    $status = $appointmentResource['status'] ?? 'pending';
                    $isActive = ($status === 'booked' || $status === 'pending');

                    // Extract patient, doctor and service information
                    $patientHisId = $appointmentResource['subject']['identifier']['value'];
                    $doctorHisId = $appointmentResource['participant'][0]['actor']['identifier']['value'];
                    $serviceHisId = $appointmentResource['serviceType'][0]['reference']['identifier']['value'];


                    // Check if we have the required information
                    if (!$patientHisId || !$doctorHisId || !$serviceHisId) {
                        $this->output->writeln('Appointment missing required information');
                        $stats['skipped']++;
                        continue;
                    }

                    // Find entities
                    $patient = $this->userRepository->findOneBy(['idHis' => $patientHisId]);
                    $doctor = $this->userRepository->findOneBy(['idHis' => $doctorHisId]);
                    $hospitalService = $this->hospitalServiceRepository->findOneBy(['idHis' => $serviceHisId]);

                    // Create patient if not found (simplified version - you may want to expand this)
                    $existingPatient = $patient;
                    if (!$patient) {
                        $this->output->writeln("Searching patient with ID: {$patientHisId}");
                        $response = $this->apiClient->get('/api/HInterop/GetPatients?idhis=' . $patientHisId);
                        if (!isset($response['entry']) || !is_array($response['entry'])) {
                            $this->output->writeln('No patients found or invalid response format');
                            continue;
                        }

                        $patientResource = $response['entry'][0]['resource'];

                        try {
                            $idHis = $patientResource['id'] ?? null;
                            if (!$idHis) {
                                $this->output->writeln('Patient without ID');
                                continue;
                            }

                            $existingPatient = new Patient();
                            $existingPatient->setIdHis($idHis);
                            $existingPatient->setRoles([Patient::BASE_ROLE]);
                            $existingPatient->setPassword(password_hash(uniqid('', true), PASSWORD_BCRYPT));

                            $email = '';
                            if (isset($patientResource['telecom']) && is_array($patientResource['telecom'])) {
                                foreach ($patientResource['telecom'] as $telecom) {
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
                            if (isset($patientResource['telecom']) && is_array($patientResource['telecom'])) {
                                foreach ($patientResource['telecom'] as $telecom) {
                                    if ($telecom['system'] === 'phone') {
                                        $phone = $telecom['value'];
                                        break;
                                    }
                                }
                            }

                            $firstName = '';
                            $lastName = '';
                            if (isset($patientResource['name'][0])) {
                                $name = $patientResource['name'][0];
                                if (isset($name['given']) && is_array($name['given'])) {
                                    $firstName = implode(' ', $name['given']);
                                } else {
                                    $firstName = $name['given'] ?? '';
                                }
                                $lastName = $name['family'] ?? '';
                            }

                            $cnp = '';
                            if (isset($patientResource['identifier']) && is_array($patientResource['identifier'])) {
                                foreach ($patientResource['identifier'] as $identifier) {
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
                        } catch (\Exception $e) {
                            $this->output->writeln('Error processing patient: ' . $e->getMessage());
                        }
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

                    // Find or create time slot
                    $timeSlot = null;
                    $this->output->writeln("Slot identifier: " . $slotIdentifier);
                    if ($slotIdentifier) {
                        // Try to find existing slot
                        $timeSlot = $this->timeSlotRepository->findOneBy(['idHis' => $slotIdentifier]);
                    }

                    if (!$timeSlot) {
                        $this->output->writeln('Timeslot unavailable');
                        $stats['skipped']++;
                        continue;
                    }

                    if ($existingAppointment) {
                        // Update existing appointment
                        $existingAppointment->setPatient($existingPatient);
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
                        $newAppointment->setPatient($existingPatient);
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
                    $this->output->writeln("Error processing FHIR Appointment: {$e->getMessage()}");
                    $stats['errors']++;
                }
            }
        }
    }
}