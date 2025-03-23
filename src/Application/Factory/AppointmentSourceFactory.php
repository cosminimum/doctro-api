<?php

namespace App\Application\Factory;

use App\Application\DataSource\AppointmentSourceInterface;
use App\Infrastructure\DataSource\FhirAppointmentSource;
use App\Infrastructure\DataSource\LocalAppointmentSource;
use Psr\Container\ContainerInterface;

class AppointmentSourceFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): AppointmentSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->container->get(FhirAppointmentSource::class);
        }

        return $this->container->get(LocalAppointmentSource::class);
    }
}