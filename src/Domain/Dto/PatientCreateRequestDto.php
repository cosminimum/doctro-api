<?php

namespace App\Domain\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PatientCreateRequestDto
{
    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Email()]
        private readonly string $email,

        #[Assert\NotBlank()]
        private readonly string $firstName,

        #[Assert\NotBlank()]
        private readonly string $lastName,

        #[Assert\NotBlank()]
        #[Assert\Length(exactly: 13)]
        private readonly string $cnp,

        #[Assert\NotBlank()]
        private readonly string $phone,

        #[Assert\NotBlank()]
        #[Assert\Count(min: 1)]
        private readonly array $roles,

        #[Assert\NotBlank()]
        #[Assert\Length(min: 6)]
        private readonly string $password
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getCnp(): string
    {
        return $this->cnp;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
