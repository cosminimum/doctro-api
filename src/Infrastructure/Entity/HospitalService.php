<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\HospitalServiceRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: HospitalServiceRepository::class)]
#[ORM\Table(name: 'hospital_services')]
#[ORM\HasLifecycleCallbacks]
class HospitalService
{
    use CreatedUpdatedTrait;

    public const LAB_MODE = "LABORATOR";
    public const AMB_MODE = "AMBULATOR";
    public const HOSPITALIZATION_MODE = "SPITALIZARE";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToMany(targetEntity: Doctor::class, mappedBy: 'hospitalServices')]
    private Collection $doctors;

    #[ORM\Column(type: 'string')]
    private string $name;
    #[ORM\Column(type: 'string')]
    private string $description;

    #[ORM\Column(type: 'string')]
    private string $price;

    #[ORM\Column(type: 'integer')]
    private string $duration;

    #[ORM\Column(type: 'string')]
    private $mode;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private string $isActive;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDoctors(): Collection
    {
        return $this->doctors;
    }

    public function addDoctor(UserInterface $doctor): self
    {
        if (!$this->doctors->contains($doctor)) {
            $this->doctors->add($doctor);
            $doctor->addHospitalService($this);
        }

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): HospitalService
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): HospitalService
    {
        $this->price = $price;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): HospitalService
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): HospitalService
    {
        $this->duration = $duration;

        return $this;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }
}
