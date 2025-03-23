<?php

namespace App\Application\Factory;

use App\Application\DataSource\AppointmentSourceInterface;
use App\Infrastructure\DataSource\FhirAppointmentSource;
use App\Infrastructure\DataSource\LocalAppointmentSource;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class AppointmentSourceFactory
{
    public function __construct(
        private readonly ServiceLocator $appointmentSourceLocator,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): AppointmentSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->appointmentSourceLocator->get(FhirAppointmentSource::class);
        }

        return $this->appointmentSourceLocator->get(LocalAppointmentSource::class);
    }
}