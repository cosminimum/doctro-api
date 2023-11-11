<?php

namespace App\Core\Controller;

use App\Domain\Dto\ApiResponseDto;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Repository\AccessTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        $response = new ApiResponseDto();

        if ($user === null) {
            $response->setErrors(['missing credentials'])
                ->setCode(Response::HTTP_UNAUTHORIZED);

            return $this->json(
                $response->toArray(),
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

        $response->setData([
            'user'  => $user->getUserIdentifier(),
            'token' => $token,
        ]);

        return $this->json(
            $response->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        // todo: add logout subscriber --> invalidate last active token
    }
}
