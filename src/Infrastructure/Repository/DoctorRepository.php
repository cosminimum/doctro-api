<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Application\Repository\MedicalSpecialtyRepositoryInterface;
use App\Domain\Dto\DoctorCreateRequestDto;
use App\Infrastructure\Entity\Doctor;
use App\Infrastructure\Entity\DoctorDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DoctorRepository extends ServiceEntityRepository implements DoctorRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MedicalSpecialtyRepositoryInterface $medicalSpecialtyRepository
    ) {
        parent::__construct($registry, Doctor::class);
    }

    public function addDoctor(DoctorCreateRequestDto $userData): int
    {
        $doctor = (new Doctor())
            ->setEmail($userData->getEmail())
            ->setFirstName($userData->getFirstName())
            ->setLastName($userData->getLastName())
            ->setCnp($userData->getCnp())
            ->setPhone($userData->getPhone())
            ->setRoles($userData->getRoles());

        $doctorDetails = (new DoctorDetails())
            ->setDoctor($doctor)
            ->setStamp($userData->getStamp());

        $doctor->setDoctorDetails($doctorDetails);

        $doctor->addMedicalSpecialty(
            $this->medicalSpecialtyRepository->findByCode($userData->getSpecialtyCode())
        );

        $newPassword = $this->passwordHasher->hashPassword(
            $doctor,
            $userData->getPassword()
        );

        $doctor->setPassword($newPassword);

        $this->getEntityManager()->persist($doctor);
        $this->getEntityManager()->flush();

        return $doctor->getId();
    }
}
