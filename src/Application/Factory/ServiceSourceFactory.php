<?php

namespace App\Application\Factory;

use App\Application\DataSource\ServiceSourceInterface;
use App\Infrastructure\DataSource\FhirServiceSource;
use App\Infrastructure\DataSource\LocalServiceSource;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ServiceSourceFactory
{
    public function __construct(
        private readonly ServiceLocator $appointmentSourceLocator,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): ServiceSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->appointmentSourceLocator->get(FhirServiceSource::class);
        }

        return $this->appointmentSourceLocator->get(LocalServiceSource::class);
    }
}