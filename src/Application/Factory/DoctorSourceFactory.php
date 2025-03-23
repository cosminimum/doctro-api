<?php

namespace App\Application\Factory;

use App\Application\DataSource\DoctorSourceInterface;
use App\Infrastructure\DataSource\FhirDoctorSource;
use App\Infrastructure\DataSource\LocalDoctorSource;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class DoctorSourceFactory
{
    public function __construct(
        private readonly ServiceLocator $appointmentSourceLocator,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): DoctorSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->appointmentSourceLocator->get(FhirDoctorSource::class);
        }

        return $this->appointmentSourceLocator->get(LocalDoctorSource::class);
    }
}