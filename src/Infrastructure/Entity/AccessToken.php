<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\AccessTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table(name: 'access_tokens')]
#[ORM\HasLifecycleCallbacks]
class AccessToken
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 180)]
    private string $userIdentifier;

    #[ORM\Column(type: 'string', length: 180)]
    private string $token;

    #[ORM\Column(type: 'datetime', columnDefinition: "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")]
    private \DateTime $validUntil;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getValidUntil(): \DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTime $validUntil): self
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->validUntil >= new \DateTime();
    }
}
