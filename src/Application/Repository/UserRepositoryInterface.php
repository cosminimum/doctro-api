<?php

namespace App\Application\Repository;

use App\Domain\Dto\UserCreateRequestDto;

interface UserRepositoryInterface
{
    public function addUser(UserCreateRequestDto $userData): int;
}
