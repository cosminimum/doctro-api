<?php

namespace App\Infrastructure\Repository;

use App\Application\Repository\AccessTokenRepositoryInterface;
use App\Infrastructure\Entity\AccessToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccessTokenRepository extends ServiceEntityRepository implements AccessTokenRepositoryInterface
{
    private const TOKEN_VALIDITY_MINUTES = 5;

    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct($registry, AccessToken::class);
    }

    public function getAccessToken(string $accessToken): ?AccessToken
    {
        return $this->findOneBy(['token' => $accessToken]);
    }

    public function getAccessTokenByUserIdentifier(string $userIdentifier): ?AccessToken
    {
        return $this->findOneBy(['userIdentifier' => $userIdentifier], ['id' => 'desc']);
    }

    public function addAccessToken(string $email, string $tokenUuid): void
    {
        $tokenValidity = (new \DateTime())
            ->modify(
                sprintf('+%s minutes', self::TOKEN_VALIDITY_MINUTES)
            );

        $accessToken = (new AccessToken())
            ->setUserIdentifier($email)
            ->setToken($tokenUuid)
            ->setValidUntil($tokenValidity);

        $this->getEntityManager()->persist($accessToken);
        $this->getEntityManager()->flush();
    }

    public function extendTokenValidity(AccessToken $accessToken): void
    {
        $newValidity = (new \DateTime())
            ->modify(
                sprintf('+%s minutes', self::TOKEN_VALIDITY_MINUTES)
            );

        $accessToken->setValidUntil($newValidity);

        $this->getEntityManager()->persist($accessToken);
        $this->getEntityManager()->flush();
    }
}
