<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\ManagerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManagerRepository::class)]
class Manager extends User
{
    public const USER_TYPE = 'manager';
}
