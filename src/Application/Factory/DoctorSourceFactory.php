<?php

namespace App\Application\Factory;

use App\Application\DataSource\DoctorSourceInterface;
use App\Infrastructure\DataSource\FhirDoctorSource;
use App\Infrastructure\DataSource\LocalDoctorSource;
use Psr\Container\ContainerInterface;

class DoctorSourceFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): DoctorSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->container->get(FhirDoctorSource::class);
        }

        return $this->container->get(LocalDoctorSource::class);
    }
}