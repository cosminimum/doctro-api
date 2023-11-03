<?php

namespace App\Application\Security;

use App\Infrastructure\Repository\AccessTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly AccessTokenRepository $repository
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $theToken = $this->repository->getAccessToken($accessToken);

        if (null === $theToken || !$theToken->isValid()) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        $this->repository->extendTokenValidity($theToken);

        return new UserBadge($theToken->getUserIdentifier());
    }
}
