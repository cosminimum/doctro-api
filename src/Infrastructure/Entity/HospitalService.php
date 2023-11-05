<?php

namespace App\Infrastructure\Entity;

use App\Infrastructure\Repository\HospitalServiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HospitalServiceRepository::class)]
#[ORM\Table(name: 'hospital_services')]
#[ORM\HasLifecycleCallbacks]
class HospitalService
{
    use CreatedUpdatedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Hospital::class, inversedBy: 'hospitalServices')]
    #[ORM\JoinColumn(name: 'hospital_id', referencedColumnName: 'id', nullable: false)]
    private Hospital $hospital;

    #[ORM\OneToOne(mappedBy: 'id', targetEntity: MedicalService::class)]
    #[ORM\JoinColumn(name: 'medical_service_id', referencedColumnName: 'id', nullable: false)]
    private MedicalService $medicalService;

    #[ORM\Column(type: 'string')]
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getHospital(): Hospital
    {
        return $this->hospital;
    }

    public function setHospital(Hospital $hospital): self
    {
        $this->hospital = $hospital;
        return $this;
    }

    public function getMedicalService(): MedicalService
    {
        return $this->medicalService;
    }

    public function setMedicalService(MedicalService $medicalService): self
    {
        $this->medicalService = $medicalService;
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
}
