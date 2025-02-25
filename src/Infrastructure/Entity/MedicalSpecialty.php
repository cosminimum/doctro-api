<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\MedicalSpecialtyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MedicalSpecialtyRepository::class)]
#[ORM\Table(name: 'medical_specialties')]
#[ORM\HasLifecycleCallbacks]
class MedicalSpecialty
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $code;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\OneToMany(mappedBy: 'medicalSpecialty', targetEntity: HospitalService::class)]
    private Collection $hospitalServices;

    public function __construct()
    {
        $this->doctors = new ArrayCollection();
        $this->hospitalServices = new ArrayCollection();
    }

    public function getHospitalServices(): Collection
    {
        return $this->hospitalServices;
    }

    public function addHospitalService(HospitalService $hospitalService): self
    {
        if (!$this->hospitalServices->contains($hospitalService)) {
            $this->hospitalServices->add($hospitalService);
            $hospitalService->setMedicalSpecialty($this);
        }

        return $this;
    }

    public function removeHospitalService(HospitalService $hospitalService): self
    {
        if ($this->hospitalServices->removeElement($hospitalService)) {
            if ($hospitalService->getMedicalSpecialty() === $this) {
                // This would create an invalid state since the relationship is required
                // You might want to handle this differently based on your application logic
            }
        }

        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Doctor::class, mappedBy: 'medicalSpecialties')]
    private Collection $doctors;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): MedicalSpecialty
    {
        $this->isActive = $isActive;

        return $this;
    }
}
