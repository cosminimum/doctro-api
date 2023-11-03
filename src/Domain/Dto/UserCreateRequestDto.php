<?php

namespace App\Domain\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserCreateRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'email is mandatory')]
        #[Assert\Email(message: 'email has wrong format')]
        private readonly string $email,

        #[Assert\NotBlank(message: 'roles are mandatory')]
        #[Assert\Count(min: 1, minMessage: 'minimum of 1 role is mandatory')]
        private readonly array $roles,

        #[Assert\NotBlank(message: 'password is mandatory')]
        #[Assert\Length(min: 6, max: 30, exactMessage: 'password must be between 6 and 30')]
        private readonly string $password
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
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
