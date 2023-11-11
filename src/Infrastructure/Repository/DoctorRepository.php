<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\DoctorListRequestDto;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use function Doctrine\ORM\QueryBuilder;

class DoctorRepository extends ServiceEntityRepository implements DoctorRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($registry, Doctor::class);
    }

    public function addDoctor(UserCreateRequestDto $userData): int
    {
        $doctor = (new Doctor())
            ->setEmail($userData->getEmail())
            ->setFirstName($userData->getFirstName())
            ->setLastName($userData->getLastName())
            ->setCnp($userData->getCnp())
            ->setPhone($userData->getPhone())
            ->setRoles([Doctor::BASE_ROLE]);

        $newPassword = $this->passwordHasher->hashPassword(
            $doctor,
            $userData->getPassword()
        );

        $doctor->setPassword($newPassword);

        $this->getEntityManager()->persist($doctor);
        $this->getEntityManager()->flush();

        return $doctor->getId();
    }

    public function getDoctorByFilters(DoctorListRequestDto $requestDto): array
    {
        $qb = $this->createQueryBuilder('doctor');

        $qb->innerJoin('doctor.medicalSpecialties', 'specialty');
        // todo: join hospital & hospital services

        $qb->select('doctor')
            ->where(true);

        if ($requestDto->getHospitalId() !== null) {
            $qb->where(
                $qb->expr()->in(':hospitalId', 'doctor.hospitals')
            );

            $qb->setParameter('hospitalId', $requestDto->getHospitalId());
        }

        if ($requestDto->getSpecialtyId() !== null) {
            $qb->andWhere(
                $qb->expr()->in(':specialtyId', 'doctor.medicalSpecialties')
            );

            $qb->setParameter('specialtyId', $requestDto->getSpecialtyId());
        }

        if ($requestDto->getDoctorName() !== null) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('doctor.firstName', ':doctorName'),
                    $qb->expr()->eq('doctor.lastName', ':doctorName')
                )
            );

            $qb->setParameter('doctorName', $requestDto->getDoctorName());
        }

        if ($requestDto->getServiceId() !== null) {
            $qb->andWhere(
                $qb->expr()->eq('hospitalService.medicalService', ':serviceId')
            );

            $qb->setParameter('serviceId', $requestDto->getServiceId());
        }

        return $qb->getQuery()->getArrayResult();
    }
}
