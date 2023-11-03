<?php

namespace App\Application\Repository;

use App\Infrastructure\Entity\AccessToken;

interface AccessTokenRepositoryInterface
{
    public function getAccessToken(string $accessToken): ?AccessToken;
    public function getAccessTokenByUserIdentifier(string $userIdentifier): ?AccessToken;
    public function addAccessToken(string $email, string $tokenUuid): void;
    public function extendTokenValidity(AccessToken $accessToken): void;
}
