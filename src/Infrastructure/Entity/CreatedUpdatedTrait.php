<?php

namespace App\Infrastructure\Entity;

use Doctrine\ORM\Mapping as ORM;

trait CreatedUpdatedTrait
{
    #[ORM\Column(type: 'datetime', columnDefinition: "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP")]
    private ?\DateTime $created;

    #[ORM\Column(type: 'datetime', columnDefinition: "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP")]
    private ?\DateTime $updated;

    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    public function getUpdated(): ?\DateTime
    {
        return $this->updated;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->created = $now;
        $this->updated = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $now = new \DateTime();
        $this->updated = $now;
    }
}
