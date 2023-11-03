<?php

namespace App\Application\Repository;

interface UserRepositoryInterface
{
    public function addUser(array $userData): int;
}
