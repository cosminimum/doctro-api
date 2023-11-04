<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\UserRepositoryInterface;
use App\Domain\Dto\UserCreateRequestDto;
use App\Infrastructure\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements
    PasswordUpgraderInterface, UserRepositoryInterface
{
    use UserAuthEntityTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($registry, User::class);
    }

    public function addUser(UserCreateRequestDto $userData): int
    {
        $user = (new User())
            ->setEmail($userData->getEmail())
            ->setFirstName($userData->getFirstName())
            ->setLastName($userData->getLastName())
            ->setCnp($userData->getCnp())
            ->setPhone($userData->getPhone())
            ->setRoles($userData->getRoles());

        $newPassword = $this->passwordHasher->hashPassword(
            $user,
            $userData->getPassword()
        );

        $user->setPassword($newPassword);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user->getId();
    }
}
