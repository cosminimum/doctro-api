<?php

namespace App\Application\Factory;

use App\Application\Datasource\ServiceSourceInterface;
use App\Infrastructure\Datasource\FhirServiceSource;
use App\Infrastructure\Datasource\LocalServiceSource;
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