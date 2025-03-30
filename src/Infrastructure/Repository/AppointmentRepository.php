<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\AppointmentRepositoryInterface;
use App\Domain\Dto\AppointmentAddRequestDto;
use App\Domain\Dto\AppointmentDto;
use App\Domain\Dto\AppointmentListRequestDto;
use App\Infrastructure\Entity\Appointment;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\TimeSlot;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Factory\AppointmentDtoFactory;
use App\Infrastructure\Service\FhirApiClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRepository extends ServiceEntityRepository implements AppointmentRepositoryInterface
{
    private FhirApiClient $hirApiClient;
    public function __construct(
        ManagerRegistry $registry,
        FhirApiClient $hirApiClient
    ) {
        parent::__construct($registry, Appointment::class);
        $this->hirApiClient = $hirApiClient;

    }

    public function addAppointment(AppointmentAddRequestDto $requestDto, User $user): int
    {
        $patient = $this->getEntityManager()->find(Patient::class, $user->getId());
        $doctor = $this->getEntityManager()->find(Doctor::class, $requestDto->getDoctorId());
        $medicalSpecialty = $this->getEntityManager()->find(MedicalSpecialty::class, $requestDto->getSpecialtyId());
        $hospitalService = $this->getEntityManager()->find(HospitalService::class, $requestDto->getHospitalServiceId());
        $timeSlot = $this->getEntityManager()->find(TimeSlot::class, $requestDto->getTimeSlotId());

        if (!$patient || !$doctor || !$medicalSpecialty || !$hospitalService) {
            throw new \Exception('missing mandatory data on appointment add');
        }

        $appointment = (new Appointment())
            ->setPatient($patient)
            ->setDoctor($doctor)
            ->setMedicalSpecialty($medicalSpecialty)
            ->setHospitalService($hospitalService)
            ->setTimeSlot($timeSlot)
            ->setIsActive(false)
        ;

        $this->getEntityManager()->persist($appointment);
        $this->getEntityManager()->flush();
        try {
            $this->createFhirAppointment($appointment);
        } catch (\Exception $exception) {
            //
        }


        return $appointment->getId();
    }

    private function createFhirAppointment(Appointment $appointment): void
    {
        $timeSlot = $appointment->getTimeSlot();
        $schedule = $timeSlot->getSchedule();

        $appointmentDate = $schedule->getDate()->format('Y-m-d');
        $startTime = $timeSlot->getStartTime()->format('H:i:s');
        $endTime = $timeSlot->getEndTime()->format('H:i:s');

        // Format with seconds and timezone for proper ISO 8601 datetime
        $appointmentStart = $appointmentDate . 'T' . $startTime . '+00:00';
        $appointmentEnd = $appointmentDate . 'T' . $endTime . '+00:00';

        // Create XML using DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root element - Appointment
        $appointmentElem = $dom->createElement('Appointment');
        $appointmentElem->setAttribute('xmlns', 'http://hl7.org/fhir');
        $dom->appendChild($appointmentElem);

        // Status
        $status = $dom->createElement('status');
        $status->setAttribute('value', 'booked');
        $appointmentElem->appendChild($status);

        // Start and End times with correct format
        $start = $dom->createElement('start');
        $start->setAttribute('value', $appointmentStart);
        $appointmentElem->appendChild($start);

        $end = $dom->createElement('end');
        $end->setAttribute('value', $appointmentEnd);
        $appointmentElem->appendChild($end);

        // Contained Patient resource
        $contained = $dom->createElement('contained');
        $appointmentElem->appendChild($contained);

        $patient = $dom->createElement('Patient');
        $patient->setAttribute('id', 'pat1');
        $contained->appendChild($patient);

        // Patient name
        $name = $dom->createElement('name');
        $patient->appendChild($name);

        $family = $dom->createElement('family');
        $family->setAttribute('value', $appointment->getPatient()->getLastName());
        $name->appendChild($family);

        $given = $dom->createElement('given');
        $given->setAttribute('value', $appointment->getPatient()->getFirstName());
        $name->appendChild($given);

        // Patient identifier
        $identifier = $dom->createElement('identifier');
        $patient->appendChild($identifier);

        $system = $dom->createElement('system');
        $system->setAttribute('value', 'urn:oid:2.16.840.1.113883.3.666.5.1');
        $identifier->appendChild($system);

        $value = $dom->createElement('value');
        $value->setAttribute('value', $appointment->getPatient()->getCnp());
        $identifier->appendChild($value);

        // Patient telecom
        $telecom = $dom->createElement('telecom');
        $patient->appendChild($telecom);

        $telecomSystem = $dom->createElement('system');
        $telecomSystem->setAttribute('value', 'phone');
        $telecom->appendChild($telecomSystem);

        $telecomValue = $dom->createElement('value');
        $telecomValue->setAttribute('value', $appointment->getPatient()->getPhone());
        $telecom->appendChild($telecomValue);

        // Patient participant - reference to contained patient
        $participantPatient = $dom->createElement('participant');
        $appointmentElem->appendChild($participantPatient);

        $actorPatient = $dom->createElement('actor');
        $participantPatient->appendChild($actorPatient);

        $referencePatient = $dom->createElement('reference');
        $referencePatient->setAttribute('value', '#pat1');
        $actorPatient->appendChild($referencePatient);

        $statusPatient = $dom->createElement('status');
        $statusPatient->setAttribute('value', 'accepted');
        $participantPatient->appendChild($statusPatient);

        // Practitioner participant
        $participant = $dom->createElement('participant');
        $appointmentElem->appendChild($participant);

        $actor = $dom->createElement('actor');
        $participant->appendChild($actor);

        $reference = $dom->createElement('reference');
        $doctorId = $appointment->getDoctor()->getIdHis() ?: $appointment->getDoctor()->getId();
        $reference->setAttribute('value', 'Practitioner/' . $doctorId);
        $actor->appendChild($reference);

        $participantStatus = $dom->createElement('status');
        $participantStatus->setAttribute('value', 'accepted');
        $participant->appendChild($participantStatus);

        // Slot reference - add it after participants to match the example order
        $slotElem = $dom->createElement('slot');
        $appointmentElem->appendChild($slotElem);

        $slotReference = $dom->createElement('reference');

        // Generate a slot reference in the exact format from your example
        // Format: 2__42290000000004231__03__202515:0010__30
        $doctorIdPrefix = "2";  // This appears to be a prefix
        $patientIdHis = "42290000000004231";  // System-specific ID
        $day = $schedule->getDate()->format('d');
        $month = $schedule->getDate()->format('m');
        $year = $schedule->getDate()->format('Y');
        $time = $timeSlot->getStartTime()->format('H:i');
        $duration = $appointment->getHospitalService() ? $appointment->getHospitalService()->getDuration() : "30";
        $durationParts = ["10", "30"];  // Adjust these based on your requirements

        $slotValue = "{$doctorIdPrefix}__{$patientIdHis}__{$day}__{$month}__{$year}{$time}{$durationParts[0]}__{$durationParts[1]}";

        // Use existing ID if available, otherwise use the generated format
        $slotId = $timeSlot->getIdHis() ?: $slotValue;

        $slotReference->setAttribute('value', $slotId);
        $slotElem->appendChild($slotReference);

        // Get the XML string
        $xmlString = $dom->saveXML();

        // For debugging - uncomment these lines if you need to see the XML

        try {
            // Send the XML using the custom method
            $response = $this->sendFhirXmlRequest('/api/HInterop/CreateAppointment', $xmlString);

            // Parse the XML response to extract the ID
            if ($response && preg_match('/<id.*?value="([^"]*)"/', $response, $matches)) {
                $appointmentId = $matches[1];
                $appointment->setIdHis($appointmentId);
                $this->entityManager->persist($appointment);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            error_log('Failed to create appointment in FHIR: ' . $e->getMessage());
        }
    }

    private function sendFhirXmlRequest(string $endpoint, string $xmlData): string
    {
        // Get token from your existing API client
        $token = $this->fhirApiClient->getToken();

        // Get base URL from the API client if available, otherwise use a default
        $baseUrl = method_exists($this->fhirApiClient, 'getBaseUrl')
            ? $this->fhirApiClient->getBaseUrl()
            : 'https://your-fhir-server.com';

        // Setup cURL
        $ch = curl_init($baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/fhir+xml',
            'Accept: application/fhir+xml',
            'Content-Length: ' . strlen($xmlData)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new \Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception('HTTP error: ' . $httpCode . ' - ' . $response);
        }

        return $response;
    }

    /** @return AppointmentDto[] */
    public function getAppointmentListByFilters(?AppointmentListRequestDto $requestDto): array
    {
        $qb = $this->createQueryBuilder('appointment');

        $qb->innerJoin('appointment.hospitalService', 'hospitalService');
        $qb->innerJoin('appointment.doctor', 'doctor');

        $qb->select('appointment');

        $qb->where($qb->expr()->eq(true, true));

        if ($requestDto?->getHospitalId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('hospitalService.hospital', ':hospitalId')
            );

            $qb->setParameter('hospitalId', $requestDto?->getHospitalId());
        }

        if ($requestDto?->getDate() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('appointment.appointmentDate', ':appointmentDate')
            );

            $qb->setParameter('appointmentDate', $requestDto?->getDate());
        }

        if ($requestDto?->getSpecialtyId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('appointment.medicalSpecialty', ':specialtyId')
            );

            $qb->setParameter('specialtyId', $requestDto?->getSpecialtyId());
        }

        if ($requestDto?->getDoctorName() !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('doctor.firstName', ':doctorName'),
                    $qb->expr()->like('doctor.lastName', ':doctorName')
                )
            );

            $qb->setParameter('doctorName', '%'.$requestDto?->getDoctorName().'%');
        }

        if ($requestDto?->getStatus() !== null) {
            // todo: field & filter TBD
        }

        $qb->groupBy('appointment.id');

        /** @var Appointment[] $results */
        $results = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT);

        return array_map(static function ($appointment) {
            return AppointmentDtoFactory::fromEntity($appointment);
        }, $results);
    }
}
