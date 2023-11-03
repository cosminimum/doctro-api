<?php

namespace App\Presentation\Controller\Auth;

use App\Application\Repository\AccessTokenRepositoryInterface;
use App\Infrastructure\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenRepositoryInterface $accessTokenRepository
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(
                ['message' => 'missing credentials',],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $lastToken = $this->accessTokenRepository->getAccessTokenByUserIdentifier(
            $user->getUserIdentifier()
        );

        if ($lastToken !== null && $lastToken->isValid()) {
            $token = $lastToken->getToken();
        } else {
            $token = Uuid::v4()->toRfc4122();

            $this->accessTokenRepository->addAccessToken(
                $user->getEmail(),
                $token
            );
        }

        return $this->json([
            'user'  => $user->getUserIdentifier(),
            'token' => $token,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        // todo: add logout subscriber --> invalidate last active token
    }
}
