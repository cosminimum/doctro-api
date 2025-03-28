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

        $appointmentStart = $appointmentDate . 'T' . $startTime;
        $appointmentEnd = $appointmentDate . 'T' . $endTime;

        $fhirAppointment = [
            'resourceType' => 'Appointment',
            'status' => 'booked',
            'start' => $appointmentStart,
            'end' => $appointmentEnd,
            'participant' => [
                [
                    'actor' => [
                        'reference' => 'Patient/' . ($appointment->getPatient()->getId())
                    ],
                    'status' => 'accepted'
                ],
                [
                    'actor' => [
                        'reference' => 'Practitioner/' . ($appointment->getDoctor()->getIdHis() ?? $appointment->getDoctor()->getId())
                    ],
                    'status' => 'accepted'
                ]
            ],
            'serviceCategory' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-category',
                            'code' => 'specialty-' . $appointment->getMedicalSpecialty()->getId(),
                            'display' => $appointment->getMedicalSpecialty()->getName()
                        ]
                    ]
                ]
            ],
            'serviceType' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-type',
                            'code' => 'service-' . $appointment->getHospitalService()->getId(),
                            'display' => $appointment->getHospitalService()->getName()
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = $this->fhirApiClient->post('/api/HInterop/CreateAppointment', $fhirAppointment);
            if (isset($response['id'])) {
                $appointment->setIdHis($response['id']);
                $this->entityManager->persist($appointment);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            error_log('Failed to create appointment in FHIR: ' . $e->getMessage());
        }
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
