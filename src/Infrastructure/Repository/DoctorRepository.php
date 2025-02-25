<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\DoctorDetailsDto;
use App\Domain\Dto\DoctorDto;
use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Factory\DoctorDetailsDtoFactory;
use App\Infrastructure\Factory\DoctorDtoFactory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DoctorRepository extends ServiceEntityRepository implements DoctorRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($registry, Doctor::class);
    }

    public function addDoctor(User $user, string $plainPassword): int
    {
        $doctor = (new Doctor())
            ->setEmail($user->getEmail())
            ->setFirstName($user->getFirstName())
            ->setLastName($user->getLastName())
            ->setCnp($user->getCnp())
            ->setPhone($user->getPhone())
            ->setRoles([Doctor::BASE_ROLE]);

        $newPassword = $this->passwordHasher->hashPassword(
            $doctor,
            $plainPassword
        );

        $doctor->setPassword($newPassword);

        $this->getEntityManager()->persist($doctor);
        $this->getEntityManager()->flush();

        return $doctor->getId();
    }

    /** @return DoctorDto[] */
    public function getDoctorListByFilters(?DoctorListRequestDto $requestDto): array
    {
        $qb = $this->createQueryBuilder('doctor');

        $qb->innerJoin('doctor.medicalSpecialties', 'specialty');
        $qb->innerJoin('doctor.hospitalServices', 'service');

        $qb->select('doctor');

        $qb->where($qb->expr()->eq(true, true));

        if ($requestDto?->getHospitalId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('service.hospital', ':hospitalId')
            );

            $qb->setParameter('hospitalId', $requestDto?->getHospitalId());
        }

        if ($requestDto?->getSpecialtyId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('specialty.id', ':specialtyId')
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

        if ($requestDto?->getServiceId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('service.medicalService', ':serviceId')
            );

            $qb->setParameter('serviceId', $requestDto?->getServiceId());
        }

        $qb->groupBy('doctor.id');

        /** @var Doctor[] $results */
        $results = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT);


        return array_map(static function ($doctor) {
            return DoctorDtoFactory::fromEntity($doctor);
        }, $results);
    }

    public function getDoctorDetails(int $doctorId): ?DoctorDetailsDto
    {
        $qb = $this->createQueryBuilder('doctor');

        $qb->innerJoin('doctor.medicalSpecialties', 'specialty');
        $qb->innerJoin('doctor.hospitalServices', 'service');

        $qb->select('doctor');

        $qb->where(
            $qb->expr()->eq('doctor.id', ':doctorId')
        );

        $qb->setParameter('doctorId', $doctorId);

        $result = $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT);

        return $result !== null
            ? DoctorDetailsDtoFactory::fromEntity($result)
            : null;
    }

    public function findDoctorsByService(int $serviceId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.hospitalServices', 'hs')
            ->where('hs.id = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getResult();
    }

    public function findDoctorsWithAvailability(
        int $serviceId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.hospitalServices', 'hs')
            ->leftJoin('d.schedules', 'ds')
            ->leftJoin('ds.timeSlots', 'ts')
            ->where('hs.id = :serviceId')
            ->andWhere('ds.date BETWEEN :startDate AND :endDate')
            ->andWhere('ts.isBooked = :isBooked')
            ->setParameter('serviceId', $serviceId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('isBooked', false)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
