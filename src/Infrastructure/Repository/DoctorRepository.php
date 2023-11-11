<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\DoctorRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
