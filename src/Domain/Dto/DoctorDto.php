<?php

namespace App\Domain\Dto;

class DoctorDto
{
    protected const KEY_ID = 'id';
    protected const KEY_EMAIL = 'email';
    protected const KEY_FIRST_NAME = 'first_name';
    protected const KEY_LAST_NAME = 'last_name';
    protected const KEY_CNP = 'cnp';
    protected const KEY_PHONE = 'phone';

    protected int $id;
    protected string $email;
    protected string $firstName;
    protected string $lastName;
    protected string $cnp;
    protected string $phone;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function setCnp(string $cnp): self
    {
        $this->cnp = $cnp;
        return $this;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function toArray(): array
    {
        return [
            self::KEY_ID => $this->id,
            self::KEY_EMAIL => $this->email,
            self::KEY_FIRST_NAME => $this->firstName,
            self::KEY_LAST_NAME => $this->lastName,
            self::KEY_CNP => $this->cnp,
            self::KEY_PHONE => $this->phone
        ];
    }
}
