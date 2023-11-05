<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\HospitalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HospitalRepository::class)]
#[ORM\Table(name: 'hospitals')]
#[ORM\HasLifecycleCallbacks]
class Hospital
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'string')]
    private string $image;

    #[ORM\OneToMany(mappedBy: 'hospital', targetEntity: HospitalService::class)]
    private Collection $hospitalServices;

    public function __construct()
    {
        $this->hospitalServices = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): void
    {
        $this->image = $image;
    }

    public function getHospitalServices(): Collection
    {
        return $this->hospitalServices;
    }

    public function addHospitalService(HospitalService $hospitalService): self
    {
        $this->hospitalServices->add($hospitalService);
        return $this;
    }

    public function removeHospitalService(HospitalService $hospitalService): self
    {
        $this->hospitalServices->removeElement($hospitalService);
        return $this;
    }
}
