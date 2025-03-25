<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\HospitalServiceRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: HospitalServiceRepository::class)]
#[ORM\Table(name: 'hospital_services')]
#[ORM\HasLifecycleCallbacks]
class HospitalService
{
    use CreatedUpdatedTrait;

    public const LAB_MODE = "LABORATOR";
    public const AMB_MODE = "AMBULATOR";
    public const HOSPITALIZATION_MODE = "SPITALIZARE";
    public const CONTINUOUS_HOSPITALIZATION_MODE = "SPITALIZARE_CONTINUA";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private string $idHis;

    #[ORM\ManyToMany(targetEntity: Doctor::class, mappedBy: 'hospitalServices')]
    private Collection $doctors;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'string')]
    private string $description;

    #[ORM\Column(type: 'string')]
    private string $code;

    #[ORM\Column(type: 'string')]
    private string $price;

    #[ORM\Column(type: 'integer')]
    private string $duration;

    #[ORM\Column(type: 'string')]
    private $mode;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private string $isActive;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $color = '#3788d8';

    #[ORM\ManyToOne(targetEntity: MedicalSpecialty::class, inversedBy: 'hospitalServices')]
    #[ORM\JoinColumn(nullable: false)]
    private MedicalSpecialty $medicalSpecialty;

    public function getMedicalSpecialty(): MedicalSpecialty
    {
        return $this->medicalSpecialty;
    }

    public function setMedicalSpecialty(MedicalSpecialty $medicalSpecialty): self
    {
        $this->medicalSpecialty = $medicalSpecialty;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdHis(): string
    {
        return $this->idHis;
    }

    public function setIdHis(string $idHis): HospitalService
    {
        $this->idHis = $idHis;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): HospitalService
    {
        $this->code = $code;

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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }
}