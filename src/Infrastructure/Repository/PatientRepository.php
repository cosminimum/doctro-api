<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\PatientRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\Patient;
use App\Infrastructure\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PatientRepository extends ServiceEntityRepository implements PatientRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($registry, Patient::class);
    }

    public function addPatient(UserCreateRequestDto $userData): User
    {
        $user = (new Patient())
            ->setEmail($userData->getEmail())
            ->setFirstName($userData->getFirstName())
            ->setLastName($userData->getLastName())
            ->setCnp($userData->getCnp())
            ->setPhone($userData->getPhone())
            ->setRoles([Patient::BASE_ROLE]);

        $newPassword = $this->passwordHasher->hashPassword(
            $user,
            $userData->getPassword()
        );

        $user->setPassword($newPassword);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

}
