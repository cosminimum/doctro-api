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

    public function __construct(
        EntityManagerInterface $entityManager,
        FhirApiClient $apiClient,
        LoggerInterface $logger,
        UserRepository $userRepository,
        DoctorScheduleRepository $doctorScheduleRepository,
        DoctorRepositoryInterface $doctorRepository,
        MedicalSpecialtyRepository $medicalSpecialtyRepository,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->apiClient = $apiClient;
        $this->userRepository = $userRepository;
        $this->doctorScheduleRepository = $doctorScheduleRepository;
        $this->doctorRepository = $doctorRepository;
        $this->medicalSpecialtyRepository = $medicalSpecialtyRepository;
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
            $response = $this->apiClient->get('/api/HInterop/GetPractitioners?active=true');

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
//            $response = $this->apiClient->get('/api/HInterop/GetPractitionerRoles?active=true');
            $response = '{"resourceType":"Bundle","type":"collection","entry":[{"fullUrl":"d426b5e4-f6ce-4744-9664-3c2324617131","resource":{"resourceType":"PractitionerRole","id":"FFDEA3A9-1386-46D1-9AC5-122DC852D547","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"824947"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"FFDEA3A9-1386-46D1-9AC5-122DC852D547"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"7601589d-cac8-419c-a038-d8a0bd2ae9aa","resource":{"resourceType":"PractitionerRole","id":"FF942B4E-5F67-466A-8C18-6FCF682A6AF9","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"788027"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"FF942B4E-5F67-466A-8C18-6FCF682A6AF9"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"fe697ae7-235e-4f0b-b9ea-fda7ed90f301","resource":{"resourceType":"PractitionerRole","id":"FAE91BA8-5DCA-4F88-A10F-2C5A14273E0C","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"812417"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"FAE91BA8-5DCA-4F88-A10F-2C5A14273E0C"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"6a612f44-57dd-4b33-94d1-c3b86a6a1d8b","resource":{"resourceType":"PractitionerRole","id":"F7F506F4-E07F-438C-A355-10477E754F36","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"795656"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"F7F506F4-E07F-438C-A355-10477E754F36"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"NEUROLOGIE"}],"text":"NEUROLOGIE"}]}},{"fullUrl":"b9ef8787-ed53-4ea8-aaee-7ab2c54ab82f","resource":{"resourceType":"PractitionerRole","id":"ED8EBFDA-3B55-4715-93D7-0C7AADE99E19","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"E69643"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"ED8EBFDA-3B55-4715-93D7-0C7AADE99E19"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"81442ab5-0e8a-4b02-bd0a-fdd6fa91238b","resource":{"resourceType":"PractitionerRole","id":"E9DDABE0-59B9-4A1E-8FDE-CEB5AA960FD1","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"050147"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"E9DDABE0-59B9-4A1E-8FDE-CEB5AA960FD1"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"e25036e6-2b14-4ab9-9753-a3861cd53124","resource":{"resourceType":"PractitionerRole","id":"E2D7ACA3-A12F-4E57-8C11-D79F2E1E7DF1","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C81728"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"E2D7ACA3-A12F-4E57-8C11-D79F2E1E7DF1"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"9e6f60fc-5f8b-41b9-a140-3146bfb9a972","resource":{"resourceType":"PractitionerRole","id":"E1961C2E-6387-45B0-BE41-76A4200EF216","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"638765"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"E1961C2E-6387-45B0-BE41-76A4200EF216"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"2d4369b1-c4c7-40f7-bea0-a6af8b32a584","resource":{"resourceType":"PractitionerRole","id":"DEBF8B5B-80B8-425A-A584-1589B94E10F9","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"827377"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"DEBF8B5B-80B8-425A-A584-1589B94E10F9"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"f2c1e7a1-a8a9-4e27-9066-449bf228a105","resource":{"resourceType":"PractitionerRole","id":"DA4AAA68-59D2-4255-B595-B6B6DDC1C26C","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"A51464"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"DA4AAA68-59D2-4255-B595-B6B6DDC1C26C"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"2a62227f-f878-49c1-9408-f14920800ce5","resource":{"resourceType":"PractitionerRole","id":"CE063D97-FB86-467D-9E59-10528BD72ED0","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D26998"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"CE063D97-FB86-467D-9E59-10528BD72ED0"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"bce2fa94-e858-42db-b6b9-2015171764b4","resource":{"resourceType":"PractitionerRole","id":"CD004091-F665-46FF-9B79-4026071A68BB","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C74277"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"CD004091-F665-46FF-9B79-4026071A68BB"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"ab18c6fc-80c0-401b-bbde-4a90888798e8","resource":{"resourceType":"PractitionerRole","id":"CB7CC3D6-78B6-4A90-80E6-1F76C9421572","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"997567"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"CB7CC3D6-78B6-4A90-80E6-1F76C9421572"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"4186e0bf-8e97-427d-af45-9ac52093db6f","resource":{"resourceType":"PractitionerRole","id":"CA6C82CF-E419-4B3B-8E0D-E899AB96A4C0","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"G82478"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"CA6C82CF-E419-4B3B-8E0D-E899AB96A4C0"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"fe43f5b1-b6ed-42f1-84c2-3ec239dd7abe","resource":{"resourceType":"PractitionerRole","id":"C8ACBF79-3BCF-4207-9C31-4147115050F5","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D04023"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"C8ACBF79-3BCF-4207-9C31-4147115050F5"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"fb907010-33fd-482b-80dd-00ae26f39cf1","resource":{"resourceType":"PractitionerRole","id":"C3838C96-E823-4033-AF19-F61798ECC371","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"146082"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"C3838C96-E823-4033-AF19-F61798ECC371"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"ae0862e8-8698-43cd-ae6f-977cb9904dff","resource":{"resourceType":"PractitionerRole","id":"C16C06C4-4B53-410A-BD91-0ABF91985D0B","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D19713"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"C16C06C4-4B53-410A-BD91-0ABF91985D0B"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"4d04a765-5f07-47b2-a02a-48dc9134a86c","resource":{"resourceType":"PractitionerRole","id":"BCB5A105-0D46-49E0-A46A-85EBA0E1E923","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"E31247"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"BCB5A105-0D46-49E0-A46A-85EBA0E1E923"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"822886d4-92cd-4a96-9dc8-89603bc86e2e","resource":{"resourceType":"PractitionerRole","id":"B9738533-46CA-4777-8D85-9C4ABD80537D","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C87463"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B9738533-46CA-4777-8D85-9C4ABD80537D"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"0fa4cbeb-2351-43e6-95dc-8f83e0ba36f5","resource":{"resourceType":"PractitionerRole","id":"B83AD020-81E8-4413-B06C-06E354FE40CB","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"F35363"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B83AD020-81E8-4413-B06C-06E354FE40CB"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"48ae6d79-812f-48df-9b00-247557a57d24","resource":{"resourceType":"PractitionerRole","id":"B6C30B6F-5BA0-408C-B923-11361C750A32","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"F28521"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B6C30B6F-5BA0-408C-B923-11361C750A32"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"bb5c4c25-d4c8-4c49-8cce-1ff511db7f6f","resource":{"resourceType":"PractitionerRole","id":"B6AF273A-35EC-426D-A38F-7F64CB18E2A5","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"360294"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B6AF273A-35EC-426D-A38F-7F64CB18E2A5"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"ENDOCRINOLOGIE"}],"text":"ENDOCRINOLOGIE"}]}},{"fullUrl":"267a9985-7ce6-45dd-b2d2-9ab4d897065c","resource":{"resourceType":"PractitionerRole","id":"B4DF57CC-C655-4E30-AC3F-92C683D0FA90","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"823663"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B4DF57CC-C655-4E30-AC3F-92C683D0FA90"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"5616ba01-21ab-42a4-a25c-f1053465ef30","resource":{"resourceType":"PractitionerRole","id":"B4A1D557-2771-480C-9EA1-4F7A5DBEC879","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C84832"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B4A1D557-2771-480C-9EA1-4F7A5DBEC879"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"de94414e-ed42-4933-8d61-bc25192fc6b2","resource":{"resourceType":"PractitionerRole","id":"B3BB1CD1-4545-444D-8D9B-7913C524BCE1","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"F06996"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B3BB1CD1-4545-444D-8D9B-7913C524BCE1"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"5d6cd2e3-7166-4472-8d63-fcb0d29d93d7","resource":{"resourceType":"PractitionerRole","id":"B3B270BA-C120-4313-81DE-6B6EDDEF144B","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C45844"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B3B270BA-C120-4313-81DE-6B6EDDEF144B"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"05c94f9b-6dc6-497e-8b51-2eef1ac19c99","resource":{"resourceType":"PractitionerRole","id":"B30990CB-AF22-4AB7-A07A-EE4C37EF34C2","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D66827"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"B30990CB-AF22-4AB7-A07A-EE4C37EF34C2"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"39e0b479-e6e9-4be4-a7dc-6a061edeaaee","resource":{"resourceType":"PractitionerRole","id":"AFC331C6-F8A7-4F0E-BB1D-93362F9F4747","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"663396"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"AFC331C6-F8A7-4F0E-BB1D-93362F9F4747"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"GASTROENTEROLOGIE"}],"text":"GASTROENTEROLOGIE"}]}},{"fullUrl":"df3ab3ca-db8f-4999-bfd1-222681dcca74","resource":{"resourceType":"PractitionerRole","id":"AE974075-FBE1-4996-A33E-94BA09FAB805","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D19625"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"AE974075-FBE1-4996-A33E-94BA09FAB805"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"f9d64f30-0d54-4eee-86de-93b9ade3a87b","resource":{"resourceType":"PractitionerRole","id":"AE94DE12-EC93-4C1D-8140-0D81E0EA0F9A","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"059444"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"AE94DE12-EC93-4C1D-8140-0D81E0EA0F9A"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"244286f1-528a-4ea6-a53b-a6b3f5073345","resource":{"resourceType":"PractitionerRole","id":"AAAEBD2E-6FFD-4AFA-8D7E-B46F5C6E8533","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"234927"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"AAAEBD2E-6FFD-4AFA-8D7E-B46F5C6E8533"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"CARDIOLOGIE"}],"text":"CARDIOLOGIE"}]}},{"fullUrl":"0f1d01e4-2f23-45d0-8eb2-b1f81405c984","resource":{"resourceType":"PractitionerRole","id":"A7062861-2F3A-4504-98E5-48D0B0B45E8D","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"023414"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"A7062861-2F3A-4504-98E5-48D0B0B45E8D"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"c79e7605-b698-47b7-8669-2195d7ab278c","resource":{"resourceType":"PractitionerRole","id":"A0C49D4F-936F-47FA-8033-F459BA0B54B6","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"886770"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"A0C49D4F-936F-47FA-8033-F459BA0B54B6"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"eec2a013-6ac7-477a-a2cb-786863e35994","resource":{"resourceType":"PractitionerRole","id":"A054633F-9E3A-495B-814C-E7828A1472AE","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"360176"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"A054633F-9E3A-495B-814C-E7828A1472AE"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"CARDIOLOGIE"}],"text":"CARDIOLOGIE"}]}},{"fullUrl":"b9aff0fc-a0e4-4e59-a0f2-b625d89276eb","resource":{"resourceType":"PractitionerRole","id":"9B9048EB-E43A-4E91-A184-4AC87630AF86","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"G60975"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"9B9048EB-E43A-4E91-A184-4AC87630AF86"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"9044a29e-33bc-4015-b4e5-91a06f1a7b01","resource":{"resourceType":"PractitionerRole","id":"9363A5B6-92E4-42A7-B8FF-D5C25B4917BC","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"973230"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"9363A5B6-92E4-42A7-B8FF-D5C25B4917BC"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"MEDICINA INTERNA"}],"text":"MEDICINA INTERNA"}]}},{"fullUrl":"11eb4206-4b49-4122-9999-18646ff3c557","resource":{"resourceType":"PractitionerRole","id":"8EF86A7F-1B91-467C-96F5-229744A53A84","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"955948"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"8EF86A7F-1B91-467C-96F5-229744A53A84"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"MEDICINA LABORATOR"}],"text":"MEDICINA LABORATOR"}]}},{"fullUrl":"b26a9d14-b679-4b86-990b-7dbdaa8865fe","resource":{"resourceType":"PractitionerRole","id":"8D45037F-6A05-4A6B-9154-C44690801594","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"278816"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"8D45037F-6A05-4A6B-9154-C44690801594"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"fc3f8ff8-9a89-4444-bde3-844f55de8e30","resource":{"resourceType":"PractitionerRole","id":"8CFE3DFB-B10F-42CB-AAD7-1C0D3245E277","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"A91287"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"8CFE3DFB-B10F-42CB-AAD7-1C0D3245E277"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"0f26b297-8f47-4301-b79e-f8ed884b6eb4","resource":{"resourceType":"PractitionerRole","id":"89886929-6078-4AB1-99ED-1D3D0F100F48","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"974104"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"89886929-6078-4AB1-99ED-1D3D0F100F48"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"MEDICINA INTERNA"}],"text":"MEDICINA INTERNA"}]}},{"fullUrl":"500ebffa-9a0e-408b-96a9-1fb168701e0a","resource":{"resourceType":"PractitionerRole","id":"845B4830-5BAB-4B36-977C-6EED0616F4CA","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D64813"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"845B4830-5BAB-4B36-977C-6EED0616F4CA"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"88bbb747-a1d8-4c08-a33a-a566c047d58a","resource":{"resourceType":"PractitionerRole","id":"83C7E574-088A-4673-9258-82A894E589E6","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"797598"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"83C7E574-088A-4673-9258-82A894E589E6"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"29af18e4-7913-4ebc-a76b-5bd9b3d75bbf","resource":{"resourceType":"PractitionerRole","id":"7F3D6035-32A8-4DEA-AD18-CE76C53A4053","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"G89271"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"7F3D6035-32A8-4DEA-AD18-CE76C53A4053"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"2fd8d1b2-c33a-4696-b580-10a60daca26d","resource":{"resourceType":"PractitionerRole","id":"7CBD77E9-2489-4FD1-933D-171381C7F81A","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"G61471"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"7CBD77E9-2489-4FD1-933D-171381C7F81A"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"b48f9d8f-2769-479d-8604-f234b6dfdf01","resource":{"resourceType":"PractitionerRole","id":"788C305D-44C9-4694-8924-17BC23E5EA0B","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"G22973"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"788C305D-44C9-4694-8924-17BC23E5EA0B"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"GASTROENTEROLOGIE"}],"text":"GASTROENTEROLOGIE"}]}},{"fullUrl":"b1676c0f-5f62-4f90-9db1-8e97e07ee009","resource":{"resourceType":"PractitionerRole","id":"781A5F2A-15FF-4D25-A210-64859E02C088","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"F67157"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"781A5F2A-15FF-4D25-A210-64859E02C088"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"EPIDEMIOLOGIE"}],"text":"EPIDEMIOLOGIE"}]}},{"fullUrl":"8e5c884c-7732-431d-a560-679cf877441c","resource":{"resourceType":"PractitionerRole","id":"7778BF62-B290-4020-B7F4-0DB55143D56A","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"341503"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"7778BF62-B290-4020-B7F4-0DB55143D56A"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"c714fbbe-111a-4d64-bc10-2234a092e77a","resource":{"resourceType":"PractitionerRole","id":"76E5C39C-F43C-4B89-B5DB-3AD270F14F8E","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"624994"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"76E5C39C-F43C-4B89-B5DB-3AD270F14F8E"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"28e90748-1223-4a60-a913-985350bd1b66","resource":{"resourceType":"PractitionerRole","id":"730DC0A6-4132-48DF-81AD-483E45072D9E","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"532081"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"730DC0A6-4132-48DF-81AD-483E45072D9E"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"5929fe42-edf5-4210-9379-61df49ac8a0b","resource":{"resourceType":"PractitionerRole","id":"71D9945C-12F9-4B26-A0EA-B4D2FD4D14F7","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"E85476"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"71D9945C-12F9-4B26-A0EA-B4D2FD4D14F7"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"87597662-1b70-4288-ab0a-c594bbc5819e","resource":{"resourceType":"PractitionerRole","id":"71C424DC-2DE6-4B6B-93C3-4E31F86342AF","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"873697"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"71C424DC-2DE6-4B6B-93C3-4E31F86342AF"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"MEDICINA LABORATOR"}],"text":"MEDICINA LABORATOR"}]}},{"fullUrl":"2dcbc51a-a8ce-4698-b892-32da2d4c3232","resource":{"resourceType":"PractitionerRole","id":"6D38CE74-3776-4C96-856B-C8E16607D502","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"858826"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"6D38CE74-3776-4C96-856B-C8E16607D502"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"a0652126-2761-4ae4-a890-601b6dfb382e","resource":{"resourceType":"PractitionerRole","id":"6B4E01EB-1A24-48C0-A713-04D298FD0985","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"503101"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"6B4E01EB-1A24-48C0-A713-04D298FD0985"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"fe220dcf-c206-4b50-ad08-e5d6d8c97952","resource":{"resourceType":"PractitionerRole","id":"633AD845-1464-4BEC-9557-EDDF34337A74","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"610563"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"633AD845-1464-4BEC-9557-EDDF34337A74"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"baf57f55-8e37-4639-9f77-00e024fd36fa","resource":{"resourceType":"PractitionerRole","id":"5E1BA3AC-6A1E-4F31-8F30-3DADA480E764","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D01097"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"5E1BA3AC-6A1E-4F31-8F30-3DADA480E764"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"f9879647-97ea-4503-b09e-a66e9996e292","resource":{"resourceType":"PractitionerRole","id":"5B2E1311-5F55-44D0-9A03-F16003738250","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"D42074"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"5B2E1311-5F55-44D0-9A03-F16003738250"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"OFTALMOLOGIE"}],"text":"OFTALMOLOGIE"}]}},{"fullUrl":"953fcb84-302f-4539-96c1-5a80cecb0084","resource":{"resourceType":"PractitionerRole","id":"5018BEAF-3345-4686-8049-B1B84E464CE3","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"11857"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"5018BEAF-3345-4686-8049-B1B84E464CE3"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"PSIHOLOG"}],"text":"PSIHOLOG"}]}},{"fullUrl":"8596fc17-ea63-46ab-a542-d05552795262","resource":{"resourceType":"PractitionerRole","id":"4C4817E0-F750-4D5E-8C66-222F47A25837","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"241857"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"4C4817E0-F750-4D5E-8C66-222F47A25837"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"51990956-2c7c-46a7-895e-45c6e03a7406","resource":{"resourceType":"PractitionerRole","id":"4BC1A288-DDB6-48D4-B981-0D1DBEBAB6A1","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C36201"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"4BC1A288-DDB6-48D4-B981-0D1DBEBAB6A1"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"NEUROLOGIE"}],"text":"NEUROLOGIE"}]}},{"fullUrl":"5476eb5d-4328-4d99-b526-4646f28e6fc5","resource":{"resourceType":"PractitionerRole","id":"4878D74D-EC51-4F98-9C9E-31D9ED681124","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"025372"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"4878D74D-EC51-4F98-9C9E-31D9ED681124"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"12cfd825-b0c7-401f-a9e4-9c995801e339","resource":{"resourceType":"PractitionerRole","id":"457BB7F4-9BCF-486C-A1AF-32DADA0745AE","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C99446"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"457BB7F4-9BCF-486C-A1AF-32DADA0745AE"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"1f8c561e-8593-4b52-a7e1-aa99812dc3ca","resource":{"resourceType":"PractitionerRole","id":"45537BDD-BAD0-4B10-831A-D6ED0E1324C0","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"488676"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"45537BDD-BAD0-4B10-831A-D6ED0E1324C0"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"OTORINOLARINGOLOGIE"}],"text":"OTORINOLARINGOLOGIE"}]}},{"fullUrl":"59e3503b-b497-4cf1-8187-8c66bbe285f3","resource":{"resourceType":"PractitionerRole","id":"45517967-5906-4AD7-8DAF-8A9BE60B205D","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"C58188"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"45517967-5906-4AD7-8DAF-8A9BE60B205D"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"83470866-ba04-48c8-97dc-e2f2d4d95227","resource":{"resourceType":"PractitionerRole","id":"40DC8447-5E06-4F96-98A0-2934018452AF","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"930135"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"40DC8447-5E06-4F96-98A0-2934018452AF"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"OFTALMOLOGIE"}],"text":"OFTALMOLOGIE"}]}},{"fullUrl":"8195aea5-2bee-48dd-ade9-ba17440c22fa","resource":{"resourceType":"PractitionerRole","id":"381B4E55-9FD4-4AFB-8927-1D7789D38F37","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"E69161"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"381B4E55-9FD4-4AFB-8927-1D7789D38F37"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"39b14b2f-d4ec-4192-bcad-b75e828f86fa","resource":{"resourceType":"PractitionerRole","id":"2FFCD3A2-15FB-4FB0-8C57-BD4D395D16C9","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"F66693"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"2FFCD3A2-15FB-4FB0-8C57-BD4D395D16C9"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"b3dd81a4-86e4-46f6-9448-4fc100e6fea4","resource":{"resourceType":"PractitionerRole","id":"2C1639E9-257F-4719-A098-85802D7F3BDE","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"E46904"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"2C1639E9-257F-4719-A098-85802D7F3BDE"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"2778916c-92b5-4824-b5f6-d831742ec436","resource":{"resourceType":"PractitionerRole","id":"2BC085B3-325D-4785-BE30-5FCD35ECED21","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"912692"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"2BC085B3-325D-4785-BE30-5FCD35ECED21"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"534444d1-e88d-43bb-b229-362312e2bfff","resource":{"resourceType":"PractitionerRole","id":"0F843B21-8795-4E8C-A5ED-BEB4E2851343","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"NA"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"0F843B21-8795-4E8C-A5ED-BEB4E2851343"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"PSIHOLOG"}],"text":"PSIHOLOG"}]}},{"fullUrl":"e70d5658-8699-4334-9fdd-314396550dad","resource":{"resourceType":"PractitionerRole","id":"0830A100-6B5E-460C-97A4-21BDBCB1D36F","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"742294"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"0830A100-6B5E-460C-97A4-21BDBCB1D36F"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}},{"fullUrl":"edf1cd0c-b625-4363-92fb-616fd4c4c091","resource":{"resourceType":"PractitionerRole","id":"03E71DB1-70FD-46CC-B759-B078956FBA9E","identifier":[{"use":"official","type":{"coding":[{"system":"http://snomed.info/sct","code":"MD"}]},"system":"http://snomed.info/sct","value":"756016"}],"active":true,"practitioner":{"type":"Practitioner","identifier":{"system":"http://snomed.info/sct","value":"03E71DB1-70FD-46CC-B759-B078956FBA9E"}},"code":[{"coding":[{"code":"doctor"}],"text":"doctor"}],"specialty":[{"coding":[{"code":"REABILITARE MEDICALA"}],"text":"REABILITARE MEDICALA"}]}}]}';
            $response = json_decode($response, true);

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
                    if (isset($role['code']) && is_array($role['code'])) {
                        foreach ($role['code'] as $code) {
                            if (isset($code['coding'][0]['code'])) {
                                $serviceCode = $code['coding'][0]['code'];
                                $serviceName = $code['coding'][0]['display'] ?? $serviceCode;

                                $hospitalService = $this->entityManager->getRepository(HospitalService::class)
                                    ->findOneBy(['code' => $serviceCode]);

                                // Create service if it doesn't exist
                                if (!$hospitalService) {
                                    $hospitalService = new HospitalService();
                                    $hospitalService->setCode($serviceCode);
                                    $hospitalService->setName($serviceName);
                                    $hospitalService->setDescription($serviceName);
                                    $hospitalService->setPrice('0');
                                    $hospitalService->setDuration('30');
                                    $hospitalService->setMode(HospitalService::AMB_MODE);
                                    $hospitalService->setIsActive(true);
                                    $hospitalService->setColor('#3788d8');

                                    // Try to associate with a specialty if possible
                                    if (count($doctor->getMedicalSpecialties()) > 0) {
                                        $firstSpecialty = $doctor->getMedicalSpecialties()->first();
                                        $hospitalService->setMedicalSpecialty($firstSpecialty);
                                    }

                                    $this->entityManager->persist($hospitalService);
                                    $this->entityManager->flush();
                                    $servicesCreated++;
                                    $this->output->writeln("Created new service: {$serviceName} with code {$serviceCode}");
                                }

                                if (!$doctor->getHospitalServices()->contains($hospitalService)) {
                                    $doctor->addHospitalService($hospitalService);
                                    $this->output->writeln("Added service {$serviceName} to doctor {$doctor->getFirstName()} {$doctor->getLastName()}");
                                }
                            }
                        }
                    }

                    $this->entityManager->persist($doctor);
                    $count++;

                    // Flush every 20 items to avoid memory issues
                    if ($count % 20 === 0) {
                        $this->entityManager->flush();
                    }

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
                    $this->output->writeln($e->getMessage());
                }
            }

            $this->entityManager->flush();
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
                $this->output->writeln('Error processing FHIR Appointment');
                $stats['errors']++;
            }
        }
    }
}